<?php
/**
 * Plugin Name:       KDNA PDF Flipbook
 * Plugin URI:        https://krulldna.com/
 * Description:       A self-contained PDF-to-flipbook plugin with a fully styleable Elementor widget. Turns a PDF uploaded in the backend into a responsive, page-flipping viewer on the front end.
 * Version:           1.0.8
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Krull Design & Advertising (KDNA)
 * Author URI:        https://krulldna.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdna-flipbook
 * Domain Path:       /languages
 *
 * KDNA PDF Flipbook is free software. You can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 2 of the licence, or (at your option) any
 * later version.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core plugin constants.
define( 'KDNA_FLIPBOOK_VERSION', '1.0.8' );
define( 'KDNA_FLIPBOOK_FILE', __FILE__ );
define( 'KDNA_FLIPBOOK_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDNA_FLIPBOOK_URL', plugin_dir_url( __FILE__ ) );
define( 'KDNA_FLIPBOOK_BASENAME', plugin_basename( __FILE__ ) );

// Load the bootstrap loader.
require_once KDNA_FLIPBOOK_DIR . 'includes/class-kdna-flipbook.php';

/**
 * Return the main plugin instance.
 *
 * @return Kdna_Flipbook
 */
function kdna_flipbook() {
	return Kdna_Flipbook::instance();
}

// Start the plugin.
kdna_flipbook();

/**
 * Activation routine.
 *
 * Flags a one-time redirect to the first-run setup screen so Nick can name the
 * custom post type before anything else happens. We do not force the redirect if
 * the CPT has already been named on a previous activation.
 */
function kdna_flipbook_activate() {
	if ( ! Kdna_Flipbook_Settings::is_configured() ) {
		set_transient( 'kdna_flipbook_activation_redirect', 1, 30 );
	}

	// Make sure the CPT is registered before we flush.
	Kdna_Flipbook_Cpt::register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'kdna_flipbook_activate' );

/**
 * Deactivation routine. Tidies rewrite rules on the way out.
 */
function kdna_flipbook_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'kdna_flipbook_deactivate' );
