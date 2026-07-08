<?php
/**
 * Configurable custom post type for KDNA PDF Flipbook.
 *
 * Registers a single custom post type whose labels and slug are chosen by Nick on
 * the setup screen and stored in the plugin settings. Each entry represents one
 * client page and, from Stage 1, holds the repeater of flipbooks.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom post type handler.
 */
class Kdna_Flipbook_Cpt {

	/**
	 * Constructor. Registers the CPT and handles deferred rewrite flushing.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite' ) );
	}

	/**
	 * Register the custom post type from the saved settings.
	 *
	 * Kept static so the activation hook can call it directly before the first
	 * rewrite flush.
	 */
	public static function register_post_type() {
		$singular = Kdna_Flipbook_Settings::get( 'cpt_singular', __( 'Client', 'kdna-flipbook' ) );
		$plural   = Kdna_Flipbook_Settings::get( 'cpt_plural', __( 'Clients', 'kdna-flipbook' ) );
		$slug     = Kdna_Flipbook_Settings::get( 'cpt_slug', 'kdna-client' );

		$slug = Kdna_Flipbook_Settings::sanitize_slug( $slug );
		if ( empty( $slug ) ) {
			$slug = 'kdna-client';
		}

		$labels = array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			/* translators: %s: plural post type label. */
			'menu_name'             => $plural,
			/* translators: %s: singular post type label. */
			'add_new_item'          => sprintf( __( 'Add new %s', 'kdna-flipbook' ), $singular ),
			'add_new'               => __( 'Add new', 'kdna-flipbook' ),
			/* translators: %s: singular post type label. */
			'edit_item'             => sprintf( __( 'Edit %s', 'kdna-flipbook' ), $singular ),
			/* translators: %s: singular post type label. */
			'new_item'              => sprintf( __( 'New %s', 'kdna-flipbook' ), $singular ),
			/* translators: %s: singular post type label. */
			'view_item'             => sprintf( __( 'View %s', 'kdna-flipbook' ), $singular ),
			/* translators: %s: plural post type label. */
			'view_items'            => sprintf( __( 'View %s', 'kdna-flipbook' ), $plural ),
			/* translators: %s: plural post type label. */
			'search_items'          => sprintf( __( 'Search %s', 'kdna-flipbook' ), $plural ),
			/* translators: %s: plural post type label (lowercase). */
			'not_found'             => sprintf( __( 'No %s found', 'kdna-flipbook' ), strtolower( $plural ) ),
			/* translators: %s: plural post type label (lowercase). */
			'not_found_in_trash'    => sprintf( __( 'No %s found in the bin', 'kdna-flipbook' ), strtolower( $plural ) ),
			/* translators: %s: plural post type label. */
			'all_items'             => sprintf( __( 'All %s', 'kdna-flipbook' ), $plural ),
			/* translators: %s: singular post type label. */
			'insert_into_item'      => sprintf( __( 'Insert into %s', 'kdna-flipbook' ), strtolower( $singular ) ),
			'featured_image'        => __( 'Featured image', 'kdna-flipbook' ),
			'set_featured_image'    => __( 'Set featured image', 'kdna-flipbook' ),
			'remove_featured_image' => __( 'Remove featured image', 'kdna-flipbook' ),
			'use_featured_image'    => __( 'Use as featured image', 'kdna-flipbook' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-book',
			'menu_position'      => 25,
			'hierarchical'       => false,
			'has_archive'        => false,
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'publicly_queryable' => true,
		);

		/**
		 * Filter the arguments used to register the client custom post type.
		 *
		 * @param array  $args CPT registration arguments.
		 * @param string $slug The post type key.
		 */
		$args = apply_filters( 'kdna_flipbook_cpt_args', $args, $slug );

		register_post_type( $slug, $args );
	}

	/**
	 * Return the current post type key.
	 *
	 * @return string
	 */
	public static function get_post_type() {
		$slug = Kdna_Flipbook_Settings::sanitize_slug( Kdna_Flipbook_Settings::get( 'cpt_slug', 'kdna-client' ) );

		return empty( $slug ) ? 'kdna-client' : $slug;
	}

	/**
	 * Flush rewrite rules once after the slug has changed.
	 */
	public function maybe_flush_rewrite() {
		if ( get_transient( 'kdna_flipbook_flush_rewrite' ) ) {
			delete_transient( 'kdna_flipbook_flush_rewrite' );
			flush_rewrite_rules();
		}
	}
}
