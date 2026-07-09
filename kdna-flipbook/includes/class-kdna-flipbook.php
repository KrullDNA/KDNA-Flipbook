<?php
/**
 * Bootstrap and loader for KDNA PDF Flipbook.
 *
 * Loads dependencies, wires up the pieces, and registers hooks. Kept as a simple
 * singleton so the rest of the plugin can reach shared services in a predictable
 * way.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class Kdna_Flipbook {

	/**
	 * Single shared instance.
	 *
	 * @var Kdna_Flipbook|null
	 */
	private static $instance = null;

	/**
	 * Settings handler.
	 *
	 * @var Kdna_Flipbook_Settings
	 */
	public $settings;

	/**
	 * Custom post type handler.
	 *
	 * @var Kdna_Flipbook_Cpt
	 */
	public $cpt;

	/**
	 * Metabox handler.
	 *
	 * @var Kdna_Flipbook_Meta
	 */
	public $meta;

	/**
	 * Access gate handler.
	 *
	 * @var Kdna_Flipbook_Access
	 */
	public $access;

	/**
	 * Assets handler.
	 *
	 * @var Kdna_Flipbook_Assets
	 */
	public $assets;

	/**
	 * SVG upload handler.
	 *
	 * @var Kdna_Flipbook_Svg
	 */
	public $svg;

	/**
	 * Return the shared instance, creating it on first call.
	 *
	 * @return Kdna_Flipbook
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor. Loads files and registers hooks.
	 */
	private function __construct() {
		$this->includes();
		$this->init();
		$this->register_elementor_hooks();
	}

	/**
	 * Load class files.
	 */
	private function includes() {
		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-settings.php';
		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-cpt.php';
		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-meta.php';
		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-access.php';
		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-assets.php';
		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-svg.php';
	}

	/**
	 * Instantiate the pieces and wire the core hooks.
	 */
	private function init() {
		$this->settings = new Kdna_Flipbook_Settings();
		$this->cpt      = new Kdna_Flipbook_Cpt();
		$this->meta     = new Kdna_Flipbook_Meta();
		$this->access   = new Kdna_Flipbook_Access();
		$this->assets   = new Kdna_Flipbook_Assets();
		$this->svg      = new Kdna_Flipbook_Svg();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Register Elementor integrations at file load time.
	 *
	 * Per the KDNA conventions we hook Elementor at load time rather than inside
	 * an elementor/loaded callback. The callbacks require the Elementor base
	 * classes, which exist by the time these hooks fire.
	 */
	private function register_elementor_hooks() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tag' ) );
	}

	/**
	 * Register the flipbook widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 */
	public function register_widget( $widgets_manager ) {
		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-widget.php';
		$widgets_manager->register( new Kdna_Flipbook_Widget() );
	}

	/**
	 * Register the flipbook PDF dynamic tag and its group.
	 *
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags Dynamic tags manager.
	 */
	public function register_dynamic_tag( $dynamic_tags ) {
		$dynamic_tags->register_group(
			'kdna-flipbook',
			array(
				'title' => __( 'KDNA PDF Flipbook', 'kdna-flipbook' ),
			)
		);

		require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook-dynamic-tag.php';
		$dynamic_tags->register( new Kdna_Flipbook_Dynamic_Tag() );
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'kdna-flipbook', false, dirname( KDNA_FLIPBOOK_BASENAME ) . '/languages' );
	}
}
