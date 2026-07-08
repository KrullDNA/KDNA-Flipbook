<?php
/**
 * Access gate for KDNA PDF Flipbook.
 *
 * When a client entry has an access code, the front-end viewer area shows a code
 * box instead of the flipbooks. The code is verified over admin-ajax with a
 * nonce. On success a short-lived, signed cookie is set so the reader is not
 * asked again while navigating the session.
 *
 * Users who can edit the post, and admins, always bypass the gate. When no code
 * is set the flipbooks show straight away.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Access gate handler.
 */
class Kdna_Flipbook_Access {

	/**
	 * Nonce action for the verify request.
	 */
	const NONCE = 'kdna_flipbook_access';

	/**
	 * Cookie name prefix. The post ID is appended.
	 */
	const COOKIE_PREFIX = 'kdna_flipbook_access_';

	/**
	 * How long the unlock is remembered, in seconds.
	 */
	const COOKIE_LIFETIME = 10800; // 3 hours.

	/**
	 * Constructor. Registers the AJAX handlers.
	 */
	public function __construct() {
		add_action( 'wp_ajax_kdna_flipbook_verify', array( $this, 'ajax_verify' ) );
		add_action( 'wp_ajax_nopriv_kdna_flipbook_verify', array( $this, 'ajax_verify' ) );
	}

	/**
	 * The access code stored on a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_code( $post_id ) {
		return Kdna_Flipbook_Meta::get_access_code( $post_id );
	}

	/**
	 * Can the current user bypass the gate for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function can_bypass( $post_id ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Signed token tying an unlock cookie to a post and its exact code.
	 *
	 * Changing the code invalidates any cookie made with the old code.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $code    Access code.
	 * @return string
	 */
	public static function token( $post_id, $code ) {
		return wp_hash( 'kdna-flipbook|' . $post_id . '|' . $code );
	}

	/**
	 * Has the visitor a valid unlock cookie for this post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $code    Access code.
	 * @return bool
	 */
	public static function has_cookie( $post_id, $code ) {
		$name = self::COOKIE_PREFIX . $post_id;

		if ( empty( $_COOKIE[ $name ] ) ) {
			return false;
		}

		$given    = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		$expected = self::token( $post_id, $code );

		return hash_equals( $expected, $given );
	}

	/**
	 * Should the flipbooks be shown to the current visitor.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_access( $post_id ) {
		$code = self::get_code( $post_id );

		// No code means the page is open to everyone.
		if ( '' === $code ) {
			return true;
		}

		if ( self::can_bypass( $post_id ) ) {
			return true;
		}

		return self::has_cookie( $post_id, $code );
	}

	/**
	 * Is this post gated for the current visitor.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_gated( $post_id ) {
		return '' !== self::get_code( $post_id ) && ! self::has_access( $post_id );
	}

	/**
	 * Verify a submitted code over admin-ajax.
	 */
	public function ajax_verify() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$entered = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please reload the page.', 'kdna-flipbook' ) ) );
		}

		$code = self::get_code( $post_id );

		// Already open, or the visitor can bypass: treat as success.
		if ( '' === $code || self::can_bypass( $post_id ) ) {
			wp_send_json_success();
		}

		if ( '' !== $entered && hash_equals( $code, $entered ) ) {
			$this->set_cookie( $post_id, $code );
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => __( 'That code is not correct. Please try again.', 'kdna-flipbook' ) ) );
	}

	/**
	 * Set the signed unlock cookie.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $code    Access code.
	 */
	protected function set_cookie( $post_id, $code ) {
		$name  = self::COOKIE_PREFIX . $post_id;
		$value = self::token( $post_id, $code );
		$path  = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';

		setcookie(
			$name,
			$value,
			array(
				'expires'  => time() + self::COOKIE_LIFETIME,
				'path'     => $path,
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Reflect it for the current request too.
		$_COOKIE[ $name ] = $value;
	}

	/**
	 * Render the flipbooks, or the code box when the post is gated.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $flipbooks Flipbooks for the post.
	 * @param array $args      Render args for the viewer.
	 * @return string
	 */
	public static function render_with_gate( $post_id, $flipbooks, $args = array() ) {
		if ( self::has_access( $post_id ) ) {
			return Kdna_Flipbook_Assets::render( $flipbooks, $args );
		}

		return self::render_gate_box( $post_id, $args );
	}

	/**
	 * Render the access code box.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Render args. May carry a theme for chrome styling.
	 * @return string
	 */
	public static function render_gate_box( $post_id, $args = array() ) {
		$config = Kdna_Flipbook_Assets::get_config( $args );

		$classes = array( 'kdna-flipbook-gate' );
		$classes[] = 'kdna-flipbook--theme-' . $config['theme'];

		$style = '';
		if ( 'custom' === $config['theme'] && ! empty( $config['custom_color'] ) ) {
			$style = ' style="--kdna-flipbook-chrome: ' . esc_attr( $config['custom_color'] ) . ';"';
		}

		$nonce = wp_create_nonce( self::NONCE );

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $style . ' data-post-id="' . esc_attr( $post_id ) . '">';
		$html .= '<form class="kdna-flipbook-gate__form">';
		$html .= '<div class="kdna-flipbook-gate__icon" aria-hidden="true">' . Kdna_Flipbook_Assets::icon( 'lock' ) . '</div>';
		$html .= '<h2 class="kdna-flipbook-gate__heading">' . esc_html__( 'This content is protected', 'kdna-flipbook' ) . '</h2>';
		$html .= '<p class="kdna-flipbook-gate__help">' . esc_html__( 'Enter the access code to view the flipbooks.', 'kdna-flipbook' ) . '</p>';
		$html .= '<div class="kdna-flipbook-gate__row">';
		$html .= '<input type="password" class="kdna-flipbook-gate__input" name="code" autocomplete="off" placeholder="' . esc_attr__( 'Access code', 'kdna-flipbook' ) . '" aria-label="' . esc_attr__( 'Access code', 'kdna-flipbook' ) . '" />';
		$html .= '<button type="submit" class="kdna-flipbook-gate__submit">' . esc_html__( 'View', 'kdna-flipbook' ) . '</button>';
		$html .= '</div>';
		$html .= '<div class="kdna-flipbook-gate__error" role="alert" hidden></div>';
		$html .= '<input type="hidden" class="kdna-flipbook-gate__nonce" value="' . esc_attr( $nonce ) . '" />';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}
}
