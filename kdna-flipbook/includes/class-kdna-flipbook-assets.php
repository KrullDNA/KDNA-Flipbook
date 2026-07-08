<?php
/**
 * Conditional asset loading for KDNA PDF Flipbook.
 *
 * Registers the bundled vendor libraries (PDF.js and StPageFlip) and the plugin's
 * own front-end JS and CSS, and enqueues them only where the viewer is present.
 *
 * While the Elementor widget does not exist yet, this class also outputs a
 * temporary viewer on the single view of a client entry, wired to that entry's
 * first flipbook, so the viewer can be tested. The temporary output is replaced
 * by the Elementor widget in a later stage.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets handler.
 */
class Kdna_Flipbook_Assets {

	/**
	 * Script and style handle prefix.
	 */
	const HANDLE = 'kdna-flipbook';

	/**
	 * Whether the assets have already been enqueued this request.
	 *
	 * @var bool
	 */
	protected static $enqueued = false;

	/**
	 * Constructor. Registers the asset and temporary-output hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_for_singular' ), 20 );

		// Temporary front-end output until the Elementor widget lands.
		add_filter( 'the_content', array( $this, 'render_temporary_output' ), 20 );
	}

	/**
	 * Register the vendor and plugin assets without enqueuing them.
	 */
	public function register_assets() {
		wp_register_script(
			self::HANDLE . '-pdfjs',
			KDNA_FLIPBOOK_URL . 'assets/vendor/pdfjs/pdf.min.js',
			array(),
			'3.11.174',
			true
		);

		wp_register_script(
			self::HANDLE . '-stpageflip',
			KDNA_FLIPBOOK_URL . 'assets/vendor/stpageflip/page-flip.browser.js',
			array(),
			'2.0.7',
			true
		);

		wp_register_script(
			self::HANDLE,
			KDNA_FLIPBOOK_URL . 'assets/js/kdna-flipbook.js',
			array( self::HANDLE . '-pdfjs', self::HANDLE . '-stpageflip' ),
			KDNA_FLIPBOOK_VERSION,
			true
		);

		wp_register_style(
			self::HANDLE,
			KDNA_FLIPBOOK_URL . 'assets/css/kdna-flipbook.css',
			array(),
			KDNA_FLIPBOOK_VERSION
		);
	}

	/**
	 * Enqueue the viewer assets. Safe to call more than once.
	 *
	 * Later stages, including the Elementor widget, call this to declare that a
	 * viewer is present on the page.
	 */
	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}

		self::$enqueued = true;

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		wp_localize_script(
			self::HANDLE,
			'kdnaFlipbook',
			array(
				'workerSrc' => KDNA_FLIPBOOK_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
				'i18n'      => array(
					'loading'        => __( 'Loading', 'kdna-flipbook' ),
					'error'          => __( 'Sorry, this document could not be loaded.', 'kdna-flipbook' ),
					'fullscreen'     => __( 'Fullscreen', 'kdna-flipbook' ),
					'exitFullscreen' => __( 'Exit fullscreen', 'kdna-flipbook' ),
				),
			)
		);
	}

	/**
	 * Enqueue assets on the single view of a client entry that has a flipbook.
	 *
	 * This is the temporary test path. Once the widget exists, enqueueing is driven
	 * by the widget being present instead.
	 */
	public function maybe_enqueue_for_singular() {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		self::enqueue();
	}

	/**
	 * Should the viewer assets load on this request.
	 *
	 * Keyed off the queried object so it can run in the header, before the loop.
	 *
	 * @return bool
	 */
	protected function should_load_assets() {
		if ( is_admin() ) {
			return false;
		}

		if ( ! is_singular( Kdna_Flipbook_Cpt::get_post_type() ) ) {
			return false;
		}

		$rows = Kdna_Flipbook_Meta::get_rows( get_queried_object_id() );

		return (bool) $this->first_flipbook_with_pdf( $rows );
	}

	/**
	 * Default document icon shown when a flipbook has no custom icon.
	 *
	 * @return string Inline SVG markup.
	 */
	public static function default_icon_svg() {
		return '<svg class="kdna-flipbook__item-svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" fill="currentColor" fill-opacity="0.12"/><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 3v5h5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';
	}

	/**
	 * Build a clean list of flipbooks from saved repeater rows.
	 *
	 * @param array $rows Flipbook rows from post meta.
	 * @return array List of flipbooks, each with name, pdf_url and icon_url.
	 */
	public static function build_flipbooks_from_rows( $rows ) {
		$flipbooks = array();

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return $flipbooks;
		}

		foreach ( $rows as $row ) {
			if ( empty( $row['pdf_id'] ) ) {
				continue;
			}

			$pdf_url = wp_get_attachment_url( (int) $row['pdf_id'] );
			if ( empty( $pdf_url ) ) {
				continue;
			}

			$icon_url = ! empty( $row['icon_id'] ) ? wp_get_attachment_image_url( (int) $row['icon_id'], 'thumbnail' ) : '';

			$flipbooks[] = array(
				'name'     => isset( $row['name'] ) ? $row['name'] : '',
				'pdf_url'  => $pdf_url,
				'icon_url' => $icon_url ? $icon_url : '',
			);
		}

		return $flipbooks;
	}

	/**
	 * Build the full viewer markup, including the sidebar of flipbooks.
	 *
	 * Shared so the Elementor widget can reuse it later. Each sidebar item carries
	 * its PDF URL as a data attribute, which the front-end JS reads to switch the
	 * viewer without a full page reload.
	 *
	 * @param array $flipbooks List of flipbooks, each with name, pdf_url, icon_url.
	 * @param array $args      Optional. active index and show_sidebar flag.
	 * @return string HTML, or an empty string if there are no flipbooks.
	 */
	public static function render( $flipbooks, $args = array() ) {
		if ( empty( $flipbooks ) || ! is_array( $flipbooks ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'active'       => 0,
				'show_sidebar' => true,
			)
		);

		$active = (int) $args['active'];
		if ( $active < 0 || $active >= count( $flipbooks ) ) {
			$active = 0;
		}

		$classes = array( 'kdna-flipbook' );
		if ( $args['show_sidebar'] ) {
			$classes[] = 'kdna-flipbook--has-sidebar';
		}

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<div class="kdna-flipbook__layout">';

		if ( $args['show_sidebar'] ) {
			$html .= self::render_sidebar( $flipbooks, $active );
		}

		$html .= '<div class="kdna-flipbook__viewer">';
		$html .= '<div class="kdna-flipbook__stage"><div class="kdna-flipbook__book"></div></div>';
		$html .= '<div class="kdna-flipbook__zoom" hidden><canvas class="kdna-flipbook__zoom-canvas"></canvas></div>';
		$html .= self::render_toolbar();
		$html .= '<div class="kdna-flipbook__overlay" aria-hidden="true"><span class="kdna-flipbook__spinner"></span></div>';
		$html .= '<div class="kdna-flipbook__message" role="alert" hidden>' . esc_html__( 'Sorry, this document could not be loaded.', 'kdna-flipbook' ) . '</div>';
		$html .= '</div>'; // .kdna-flipbook__viewer

		$html .= '</div>'; // .kdna-flipbook__layout
		$html .= '</div>'; // .kdna-flipbook

		return $html;
	}

	/**
	 * Build the sidebar list of flipbooks.
	 *
	 * @param array $flipbooks List of flipbooks.
	 * @param int   $active    Active index.
	 * @return string
	 */
	protected static function render_sidebar( $flipbooks, $active ) {
		$html  = '<nav class="kdna-flipbook__sidebar" aria-label="' . esc_attr__( 'Flipbooks', 'kdna-flipbook' ) . '">';
		$html .= '<ul class="kdna-flipbook__list">';

		foreach ( $flipbooks as $index => $flipbook ) {
			$is_active = ( (int) $index === (int) $active );
			$name      = '' !== $flipbook['name'] ? $flipbook['name'] : __( 'Untitled flipbook', 'kdna-flipbook' );

			$item_classes = 'kdna-flipbook__item' . ( $is_active ? ' is-active' : '' );

			$icon = '<span class="kdna-flipbook__item-icon">';
			if ( ! empty( $flipbook['icon_url'] ) ) {
				$icon .= '<img class="kdna-flipbook__item-img" src="' . esc_url( $flipbook['icon_url'] ) . '" alt="" />';
			} else {
				$icon .= self::default_icon_svg();
			}
			$icon .= '</span>';

			$html .= '<li class="kdna-flipbook__list-item">';
			$html .= '<button type="button" class="' . esc_attr( $item_classes ) . '"';
			$html .= ' data-index="' . esc_attr( $index ) . '"';
			$html .= ' data-pdf-url="' . esc_url( $flipbook['pdf_url'] ) . '"';
			$html .= ' data-name="' . esc_attr( $flipbook['name'] ) . '"';
			$html .= $is_active ? ' aria-current="true"' : '';
			$html .= '>';
			$html .= $icon;
			$html .= '<span class="kdna-flipbook__item-name">' . esc_html( $name ) . '</span>';
			$html .= '</button>';
			$html .= '</li>';
		}

		$html .= '</ul>';
		$html .= '</nav>';

		return $html;
	}

	/**
	 * Build the viewer toolbar.
	 *
	 * This stage adds zoom and fullscreen. The remaining controls arrive in a
	 * later stage, and their visibility becomes widget toggles then.
	 *
	 * @return string
	 */
	protected static function render_toolbar() {
		$html  = '<div class="kdna-flipbook__toolbar" role="toolbar" aria-label="' . esc_attr__( 'Viewer controls', 'kdna-flipbook' ) . '">';

		$html .= '<div class="kdna-flipbook__toolbar-group kdna-flipbook__toolbar-group--zoom">';
		$html .= self::toolbar_button( 'zoom-out', __( 'Zoom out', 'kdna-flipbook' ) );
		$html .= '<span class="kdna-flipbook__zoom-level" aria-live="polite">100%</span>';
		$html .= self::toolbar_button( 'zoom-in', __( 'Zoom in', 'kdna-flipbook' ) );
		$html .= '</div>';

		$html .= '<div class="kdna-flipbook__toolbar-group kdna-flipbook__toolbar-group--view">';
		$html .= self::toolbar_button( 'fullscreen', __( 'Fullscreen', 'kdna-flipbook' ) );
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Build a single toolbar button.
	 *
	 * @param string $action Button action, used as the icon name and data hook.
	 * @param string $label  Accessible label.
	 * @return string
	 */
	protected static function toolbar_button( $action, $label ) {
		$html  = '<button type="button" class="kdna-flipbook__btn kdna-flipbook__btn--' . esc_attr( $action ) . '"';
		$html .= ' data-action="' . esc_attr( $action ) . '"';
		$html .= ' title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '">';
		$html .= self::icon( $action );
		$html .= '</button>';

		return $html;
	}

	/**
	 * Return inline SVG markup for a named icon.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function icon( $name ) {
		$open  = '<svg class="kdna-flipbook__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">';
		$close = '</svg>';

		$paths = array(
			'zoom-in'      => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>',
			'zoom-out'     => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/>',
			'fullscreen'   => '<path d="M4 9V5a1 1 0 0 1 1-1h4"/><path d="M20 9V5a1 1 0 0 0-1-1h-4"/><path d="M4 15v4a1 1 0 0 0 1 1h4"/><path d="M20 15v4a1 1 0 0 1-1 1h-4"/>',
			'fullscreen-exit' => '<path d="M9 4v4a1 1 0 0 1-1 1H4"/><path d="M15 4v4a1 1 0 0 0 1 1h4"/><path d="M9 20v-4a1 1 0 0 0-1-1H4"/><path d="M15 20v-4a1 1 0 0 1 1-1h4"/>',
		);

		$path = isset( $paths[ $name ] ) ? $paths[ $name ] : '';

		return $open . $path . $close;
	}

	/**
	 * Append the temporary viewer to the client entry content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function render_temporary_output( $content ) {
		if ( ! $this->is_temporary_output_context() ) {
			return $content;
		}

		$rows      = Kdna_Flipbook_Meta::get_rows( get_the_ID() );
		$flipbooks = self::build_flipbooks_from_rows( $rows );

		if ( empty( $flipbooks ) ) {
			return $content;
		}

		$notice = '<p class="kdna-flipbook__temp-note">' . esc_html__( 'Temporary preview: showing this entry\'s flipbooks. This is replaced by the Elementor widget.', 'kdna-flipbook' ) . '</p>';
		$viewer = self::render( $flipbooks, array( 'active' => 0 ) );

		return $content . $notice . $viewer;
	}

	/**
	 * Find the first flipbook row that has a usable PDF.
	 *
	 * @param array $rows Flipbook rows.
	 * @return array|null
	 */
	protected function first_flipbook_with_pdf( $rows ) {
		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( ! empty( $row['pdf_id'] ) ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Are we in the temporary single-entry preview context.
	 *
	 * @return bool
	 */
	protected function is_temporary_output_context() {
		if ( ! in_the_loop() || ! is_main_query() ) {
			return false;
		}

		if ( get_the_ID() !== get_queried_object_id() ) {
			return false;
		}

		return $this->should_load_assets();
	}
}
