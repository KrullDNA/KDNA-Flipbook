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
					'loading' => __( 'Loading', 'kdna-flipbook' ),
					'error'   => __( 'Sorry, this document could not be loaded.', 'kdna-flipbook' ),
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
	 * Build the viewer markup for a single PDF.
	 *
	 * Shared so the Elementor widget can reuse it later. The container carries the
	 * PDF URL as a data attribute, which the front-end JS reads on init.
	 *
	 * @param string $pdf_url PDF file URL.
	 * @param array  $args    Optional. name for the flipbook, plus future options.
	 * @return string HTML, or an empty string if there is no PDF URL.
	 */
	public static function render_viewer( $pdf_url, $args = array() ) {
		if ( empty( $pdf_url ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'name' => '',
			)
		);

		$html  = '<div class="kdna-flipbook" data-pdf-url="' . esc_url( $pdf_url ) . '"';
		$html .= ' data-name="' . esc_attr( $args['name'] ) . '">';
		$html .= '<div class="kdna-flipbook__viewer">';
		$html .= '<div class="kdna-flipbook__stage"><div class="kdna-flipbook__book"></div></div>';
		$html .= '<div class="kdna-flipbook__overlay" aria-hidden="true"><span class="kdna-flipbook__spinner"></span></div>';
		$html .= '<div class="kdna-flipbook__message" role="alert" hidden>' . esc_html__( 'Sorry, this document could not be loaded.', 'kdna-flipbook' ) . '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
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

		$rows    = Kdna_Flipbook_Meta::get_rows( get_the_ID() );
		$first   = $this->first_flipbook_with_pdf( $rows );
		$pdf_url = $first ? wp_get_attachment_url( $first['pdf_id'] ) : '';

		if ( empty( $pdf_url ) ) {
			return $content;
		}

		$notice  = '<p class="kdna-flipbook__temp-note">' . esc_html__( 'Temporary preview: showing the first flipbook. This is replaced by the Elementor widget.', 'kdna-flipbook' ) . '</p>';
		$viewer  = self::render_viewer(
			$pdf_url,
			array(
				'name' => isset( $first['name'] ) ? $first['name'] : '',
			)
		);

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
