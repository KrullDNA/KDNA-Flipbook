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

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Register Elementor integrations at file load time.
	 *
	 * Per the KDNA conventions we hook Elementor at load time rather than inside
	 * an elementor/loaded callback. There are no Elementor pieces to register yet
	 * in this stage, so this is intentionally empty for now and filled in from the
	 * widget stage onwards.
	 */
	private function register_elementor_hooks() {
		// Widget and dynamic tag registration is added in later stages.
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'kdna-flipbook', false, dirname( KDNA_FLIPBOOK_BASENAME ) . '/languages' );
	}
}
