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
	 * Constructor. Registers the assets.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register the vendor and plugin assets and attach the localised data.
	 *
	 * Registration attaches the data so any consumer, including the Elementor
	 * widget's declared dependencies, gets it when the handle is enqueued.
	 */
	public function register_assets() {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

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

		wp_localize_script(
			self::HANDLE,
			'kdnaFlipbook',
			array(
				'workerSrc' => KDNA_FLIPBOOK_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'i18n'      => array(
					'loading'        => __( 'Loading', 'kdna-flipbook' ),
					'error'          => __( 'Sorry, this document could not be loaded.', 'kdna-flipbook' ),
					'fullscreen'     => __( 'Fullscreen', 'kdna-flipbook' ),
					'exitFullscreen' => __( 'Exit fullscreen', 'kdna-flipbook' ),
					'noContents'     => __( 'No contents in this document.', 'kdna-flipbook' ),
					'linkCopied'     => __( 'Link copied', 'kdna-flipbook' ),
					'soundOn'        => __( 'Flip sound on', 'kdna-flipbook' ),
					'soundOff'       => __( 'Flip sound off', 'kdna-flipbook' ),
					'gateError'      => __( 'That code is not correct. Please try again.', 'kdna-flipbook' ),
					'gateChecking'   => __( 'Checking', 'kdna-flipbook' ),
					'gateView'       => __( 'View', 'kdna-flipbook' ),
				),
			)
		);
	}

	/**
	 * Enqueue the viewer assets. Safe to call more than once.
	 *
	 * The Elementor widget calls this when it renders, and also declares the
	 * handles as dependencies, so assets load only where a viewer is present.
	 */
	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}

		self::$enqueued = true;

		// Make sure the handles exist if this runs before wp_enqueue_scripts.
		$plugin = kdna_flipbook();
		if ( isset( $plugin->assets ) ) {
			$plugin->assets->register_assets();
		}

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
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

			$icon_url = '';
			if ( ! empty( $row['icon_id'] ) ) {
				$icon_id  = (int) $row['icon_id'];
				$icon_url = wp_get_attachment_image_url( $icon_id, 'thumbnail' );
				// SVGs and other files with no thumbnail size fall back to the full file.
				if ( ! $icon_url ) {
					$icon_url = wp_get_attachment_url( $icon_id );
				}
			}

			$icon_key = ! empty( $row['icon_key'] ) ? sanitize_key( $row['icon_key'] ) : '';
			if ( $icon_key && ! self::is_builtin_icon( $icon_key ) ) {
				$icon_key = '';
			}

			$flipbooks[] = array(
				'name'     => isset( $row['name'] ) ? $row['name'] : '',
				'pdf_url'  => $pdf_url,
				'icon_url' => $icon_url ? $icon_url : '',
				'icon_key' => $icon_key,
			);
		}

		return $flipbooks;
	}

	/**
	 * The built-in icons a client can choose from, key to inner SVG markup.
	 *
	 * Kept as a small, safe set of stroke icons drawn in a 24 by 24 view box.
	 *
	 * @return array
	 */
	public static function builtin_icons() {
		return array(
			'document'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>',
			'text'         => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/>',
			'letter'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'book'         => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22.5V4.5Z"/><path d="M4 4.5A2.5 2.5 0 0 0 6.5 7H20"/>',
			'proposal'     => '<path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/>',
			'presentation' => '<path d="M2 3h20"/><path d="M3 3v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V3"/><path d="m8 21 4-4 4 4"/>',
			'image'        => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L5 21"/>',
			'folder'       => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
			'briefcase'    => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
			'globe'        => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
			'star'         => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
			'tag'          => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
		);
	}

	/**
	 * Is a key a known built-in icon.
	 *
	 * @param string $key Icon key.
	 * @return bool
	 */
	public static function is_builtin_icon( $key ) {
		return array_key_exists( $key, self::builtin_icons() );
	}

	/**
	 * Full inline SVG markup for a built-in icon key.
	 *
	 * @param string $key Icon key.
	 * @return string
	 */
	public static function builtin_icon_svg( $key ) {
		$icons = self::builtin_icons();
		if ( ! isset( $icons[ $key ] ) ) {
			return self::default_icon_svg();
		}

		return '<svg class="kdna-flipbook__item-svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">' . $icons[ $key ] . '</svg>';
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

		$config = self::get_config( $args );

		$active = isset( $args['active'] ) ? (int) $args['active'] : 0;
		if ( $active < 0 || $active >= count( $flipbooks ) ) {
			$active = 0;
		}

		$show_sidebar = ! empty( $config['sidebar'] );

		// Below the flipbook the toolbar never overlaps content, so it stays put.
		$position  = 'below' === $config['toolbar_position'] ? 'below' : 'over';
		$behaviour = 'below' === $position ? 'persistent' : $config['toolbar_behaviour'];

		// Work out the reader hint and where it sits.
		$hint_text     = isset( $config['hint_text'] ) ? trim( (string) $config['hint_text'] ) : '';
		$show_hint     = ! empty( $config['hint_show'] ) && '' !== $hint_text;
		$hint_position = 'below' === $config['hint_position'] ? 'below' : 'sidebar';
		// With no sidebar, a sidebar hint falls back to below the flipbook.
		if ( 'sidebar' === $hint_position && ! $show_sidebar ) {
			$hint_position = 'below';
		}
		$hint_html = $show_hint ? '<p class="kdna-flipbook__hint">' . esc_html( $hint_text ) . '</p>' : '';

		$classes = array( 'kdna-flipbook' );
		$classes[] = 'kdna-flipbook--theme-' . $config['theme'];
		$classes[] = 'kdna-flipbook--toolbar-' . $behaviour;
		$classes[] = 'kdna-flipbook--toolbar-pos-' . $position;
		if ( $show_sidebar ) {
			$classes[] = 'kdna-flipbook--has-sidebar';
		}

		$style = '';
		if ( 'custom' === $config['theme'] && ! empty( $config['custom_color'] ) ) {
			$style = ' style="--kdna-flipbook-chrome: ' . esc_attr( $config['custom_color'] ) . ';"';
		}

		// Front-end config the JS reads. Only booleans and short scalars.
		$js_config = array(
			'controls'  => array(
				'arrows'     => ! empty( $config['arrows'] ),
				'thumbnails' => ! empty( $config['thumbnails'] ),
				'zoom'       => ! empty( $config['zoom'] ),
				'fullscreen' => ! empty( $config['fullscreen'] ),
				'toc'        => ! empty( $config['toc'] ),
				'download'   => ! empty( $config['download'] ),
				'share'      => ! empty( $config['share'] ),
				'sound'      => ! empty( $config['sound'] ),
				'deeplink'   => ! empty( $config['deeplink'] ),
			),
			'behaviour' => $behaviour,
			'autoplay'  => ! empty( $config['autoplay'] ),
			'autoplayDelay' => max( 1, (int) $config['autoplay_delay'] ),
			'start'     => array(
				'flipbook' => $active,
				'page'     => isset( $args['start_page'] ) ? max( 1, (int) $args['start_page'] ) : 1,
			),
		);

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $style;
		$html .= " data-kdna-config='" . esc_attr( wp_json_encode( $js_config ) ) . "'>";
		$html .= '<div class="kdna-flipbook__layout">';

		if ( $show_sidebar ) {
			$sidebar_hint = ( 'sidebar' === $hint_position ) ? $hint_html : '';
			$html        .= self::render_sidebar( $flipbooks, $active, $sidebar_hint );
		}

		$html .= '<div class="kdna-flipbook__viewer">';
		$html .= '<div class="kdna-flipbook__stage"><div class="kdna-flipbook__book"></div></div>';
		$html .= '<div class="kdna-flipbook__zoom" hidden><canvas class="kdna-flipbook__zoom-canvas"></canvas></div>';

		if ( ! empty( $config['thumbnails'] ) ) {
			$html .= '<div class="kdna-flipbook__thumbs" hidden aria-label="' . esc_attr__( 'Page thumbnails', 'kdna-flipbook' ) . '"><div class="kdna-flipbook__thumbs-track"></div></div>';
		}

		if ( ! empty( $config['toc'] ) ) {
			$html .= '<div class="kdna-flipbook__toc" hidden aria-label="' . esc_attr__( 'Table of contents', 'kdna-flipbook' ) . '"><div class="kdna-flipbook__toc-head">' . esc_html__( 'Contents', 'kdna-flipbook' ) . '</div><div class="kdna-flipbook__toc-body"></div></div>';
		}

		$html .= self::render_toolbar( $config );
		$html .= '<div class="kdna-flipbook__toast" role="status" aria-live="polite" hidden></div>';
		$html .= '<div class="kdna-flipbook__overlay" aria-hidden="true"><span class="kdna-flipbook__spinner"></span></div>';
		$html .= '<div class="kdna-flipbook__message" role="alert" hidden>' . esc_html__( 'Sorry, this document could not be loaded.', 'kdna-flipbook' ) . '</div>';
		$html .= '</div>'; // .kdna-flipbook__viewer

		$html .= '</div>'; // .kdna-flipbook__layout

		if ( 'below' === $hint_position ) {
			$html .= $hint_html;
		}

		$html .= '</div>'; // .kdna-flipbook

		return $html;
	}

	/**
	 * Merge front-end config from the passed args over the saved defaults.
	 *
	 * @param array $args Render args, possibly carrying overrides.
	 * @return array
	 */
	public static function get_config( $args = array() ) {
		$config = Kdna_Flipbook_Settings::get_frontend();

		// Allow explicit overrides, used by the Elementor widget from Stage 7.
		foreach ( $config as $key => $value ) {
			if ( isset( $args[ $key ] ) ) {
				$config[ $key ] = $args[ $key ];
			}
		}

		return $config;
	}

	/**
	 * Build the sidebar list of flipbooks.
	 *
	 * @param array  $flipbooks List of flipbooks.
	 * @param int    $active    Active index.
	 * @param string $hint_html Optional hint markup shown above the list.
	 * @return string
	 */
	protected static function render_sidebar( $flipbooks, $active, $hint_html = '' ) {
		$html  = '<nav class="kdna-flipbook__sidebar" aria-label="' . esc_attr__( 'Flipbooks', 'kdna-flipbook' ) . '">';

		// Hint sits inside the sidebar, above the document list.
		$html .= $hint_html;

		$html .= '<ul class="kdna-flipbook__list">';

		foreach ( $flipbooks as $index => $flipbook ) {
			$is_active = ( (int) $index === (int) $active );
			$name      = '' !== $flipbook['name'] ? $flipbook['name'] : __( 'Untitled flipbook', 'kdna-flipbook' );

			$item_classes = 'kdna-flipbook__item' . ( $is_active ? ' is-active' : '' );

			$icon = '<span class="kdna-flipbook__item-icon">';
			if ( ! empty( $flipbook['icon_html'] ) ) {
				// Icon rendered by Elementor's icon library, already safe markup.
				$icon .= $flipbook['icon_html'];
			} elseif ( ! empty( $flipbook['icon_url'] ) ) {
				$icon .= '<img class="kdna-flipbook__item-img" src="' . esc_url( $flipbook['icon_url'] ) . '" alt="" />';
			} elseif ( ! empty( $flipbook['icon_key'] ) ) {
				$icon .= self::builtin_icon_svg( $flipbook['icon_key'] );
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
	 * Build the viewer toolbar from the enabled controls.
	 *
	 * @param array $config Front-end config.
	 * @return string
	 */
	protected static function render_toolbar( $config ) {
		$groups = array();

		// Navigation arrows.
		if ( ! empty( $config['arrows'] ) ) {
			$groups[] = self::toolbar_button( 'prev', __( 'Previous page', 'kdna-flipbook' ) )
				. '<span class="kdna-flipbook__page-count" aria-live="polite"></span>'
				. self::toolbar_button( 'next', __( 'Next page', 'kdna-flipbook' ) );
		}

		// Zoom.
		if ( ! empty( $config['zoom'] ) ) {
			$groups[] = self::toolbar_button( 'zoom-out', __( 'Zoom out', 'kdna-flipbook' ) )
				. '<span class="kdna-flipbook__zoom-level" aria-live="polite">100%</span>'
				. self::toolbar_button( 'zoom-in', __( 'Zoom in', 'kdna-flipbook' ) );
		}

		// View panels and fullscreen.
		$view = '';
		if ( ! empty( $config['thumbnails'] ) ) {
			$view .= self::toolbar_button( 'thumbnails', __( 'Page thumbnails', 'kdna-flipbook' ) );
		}
		if ( ! empty( $config['toc'] ) ) {
			$view .= self::toolbar_button( 'toc', __( 'Table of contents', 'kdna-flipbook' ) );
		}
		if ( ! empty( $config['fullscreen'] ) ) {
			$view .= self::toolbar_button( 'fullscreen', __( 'Fullscreen', 'kdna-flipbook' ) );
		}
		if ( '' !== $view ) {
			$groups[] = $view;
		}

		// Actions.
		$actions = '';
		if ( ! empty( $config['download'] ) ) {
			$actions .= self::toolbar_button( 'download', __( 'Download PDF', 'kdna-flipbook' ) );
		}
		if ( ! empty( $config['share'] ) ) {
			$actions .= self::toolbar_button( 'share', __( 'Share', 'kdna-flipbook' ) );
		}
		if ( ! empty( $config['sound'] ) ) {
			$actions .= self::toolbar_button( 'sound', __( 'Flip sound', 'kdna-flipbook' ) );
		}
		if ( '' !== $actions ) {
			$groups[] = $actions;
		}

		// Nothing to show.
		if ( empty( $groups ) ) {
			return '';
		}

		$html = '<div class="kdna-flipbook__toolbar" role="toolbar" aria-label="' . esc_attr__( 'Viewer controls', 'kdna-flipbook' ) . '">';
		foreach ( $groups as $group ) {
			$html .= '<div class="kdna-flipbook__toolbar-group">' . $group . '</div>';
		}
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
			'prev'            => '<polyline points="15 18 9 12 15 6"/>',
			'next'            => '<polyline points="9 18 15 12 9 6"/>',
			'zoom-in'         => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>',
			'zoom-out'        => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/>',
			'thumbnails'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
			'toc'             => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
			'fullscreen'      => '<path d="M4 9V5a1 1 0 0 1 1-1h4"/><path d="M20 9V5a1 1 0 0 0-1-1h-4"/><path d="M4 15v4a1 1 0 0 0 1 1h4"/><path d="M20 15v4a1 1 0 0 1-1 1h-4"/>',
			'fullscreen-exit' => '<path d="M9 4v4a1 1 0 0 1-1 1H4"/><path d="M15 4v4a1 1 0 0 0 1 1h4"/><path d="M9 20v-4a1 1 0 0 0-1-1H4"/><path d="M15 20v-4a1 1 0 0 1 1-1h4"/>',
			'download'        => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
			'share'           => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/>',
			'sound'           => '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>',
			'sound-off'       => '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/>',
			'lock'            => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		);

		$path = isset( $paths[ $name ] ) ? $paths[ $name ] : '';

		return $open . $path . $close;
	}
}
