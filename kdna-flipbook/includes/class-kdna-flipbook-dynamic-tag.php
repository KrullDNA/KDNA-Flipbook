<?php
/**
 * Elementor dynamic tag for KDNA PDF Flipbook.
 *
 * Returns a client entry's flipbook PDF URL, so it can be pulled dynamically
 * elsewhere, for example as the link on a button or the source of another
 * element. Matches the dynamic-tag workflow used across the site.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flipbook PDF dynamic tag.
 */
class Kdna_Flipbook_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Tag slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-flipbook-pdf';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'KDNA Flipbook PDF', 'kdna-flipbook' );
	}

	/**
	 * Tag group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'kdna-flipbook';
	}

	/**
	 * Categories this tag can supply.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array(
			\Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
		);
	}

	/**
	 * Register the tag controls.
	 */
	protected function register_controls() {
		$this->add_control(
			'source',
			array(
				'label'   => __( 'From', 'kdna-flipbook' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current'  => __( 'Current page', 'kdna-flipbook' ),
					'specific' => __( 'A specific page', 'kdna-flipbook' ),
				),
			)
		);

		$this->add_control(
			'specific_post',
			array(
				'label'       => __( 'Choose page', 'kdna-flipbook' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $this->get_entry_options(),
				'label_block' => true,
				'condition'   => array( 'source' => 'specific' ),
			)
		);

		$this->add_control(
			'flipbook_index',
			array(
				'label'       => __( 'Which flipbook', 'kdna-flipbook' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'default'     => 1,
				'description' => __( 'The position in the list, so 1 is the first flipbook.', 'kdna-flipbook' ),
			)
		);
	}

	/**
	 * Build a list of client entries.
	 *
	 * @return array
	 */
	protected function get_entry_options() {
		$options = array();

		$posts = get_posts(
			array(
				'post_type'        => Kdna_Flipbook_Cpt::get_post_type(),
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			$options[ $post->ID ] = $post->post_title ? $post->post_title : sprintf( '#%d', $post->ID );
		}

		return $options;
	}

	/**
	 * Output the resolved PDF URL.
	 */
	public function render() {
		$settings = $this->get_settings();
		$source   = isset( $settings['source'] ) ? $settings['source'] : 'current';

		if ( 'specific' === $source && ! empty( $settings['specific_post'] ) ) {
			$post_id = (int) $settings['specific_post'];
		} else {
			$post_id = (int) get_the_ID();
			if ( ! $post_id ) {
				$post_id = (int) get_queried_object_id();
			}
		}

		if ( ! $post_id ) {
			return;
		}

		$rows      = Kdna_Flipbook_Meta::get_rows( $post_id );
		$flipbooks = Kdna_Flipbook_Assets::build_flipbooks_from_rows( $rows );

		if ( empty( $flipbooks ) ) {
			return;
		}

		$index = isset( $settings['flipbook_index'] ) ? max( 1, (int) $settings['flipbook_index'] ) - 1 : 0;
		if ( ! isset( $flipbooks[ $index ] ) ) {
			$index = 0;
		}

		echo esc_url( $flipbooks[ $index ]['pdf_url'] );
	}
}
