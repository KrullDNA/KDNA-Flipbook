<?php
/**
 * Elementor widget for KDNA PDF Flipbook.
 *
 * Built for Elementor's Atomic markup: a single wrapper div, no reliance on
 * .elementor-widget-container in CSS, and has_widget_inner_wrapper() returns
 * false when e_optimized_markup is active.
 *
 * The Content tab exposes the source, default view, every control toggle,
 * autoplay, toolbar behaviour, chrome theme and the sidebar. The Style tab is
 * added in the next stage.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flipbook widget.
 */
class Kdna_Flipbook_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-flipbook';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'KDNA PDF Flipbook', 'kdna-flipbook' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-book';
	}

	/**
	 * Widget categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'pdf', 'flipbook', 'kdna', 'book', 'viewer' );
	}

	/**
	 * Declare the front-end style dependency.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( Kdna_Flipbook_Assets::HANDLE );
	}

	/**
	 * Declare the front-end script dependency.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( Kdna_Flipbook_Assets::HANDLE );
	}

	/**
	 * Atomic markup: no inner wrapper when optimised markup is active.
	 *
	 * @return bool
	 */
	protected function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Register the Content tab controls.
	 */
	protected function register_controls() {
		$this->register_source_section();
		$this->register_start_section();
		$this->register_controls_section();
		$this->register_sidebar_section();
		$this->register_chrome_section();
	}

	/**
	 * Source section.
	 */
	protected function register_source_section() {
		$this->start_controls_section(
			'section_source',
			array(
				'label' => __( 'Source', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'Flipbooks come from', 'kdna-flipbook' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current'  => __( 'Current page', 'kdna-flipbook' ),
					'specific' => __( 'A specific page', 'kdna-flipbook' ),
					'dynamic'  => __( 'A dynamic tag', 'kdna-flipbook' ),
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
			'source_dynamic',
			array(
				'label'       => __( 'Page ID from a dynamic tag', 'kdna-flipbook' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'description' => __( 'Provide a page ID from a dynamic tag, for example the current post ID.', 'kdna-flipbook' ),
				'condition'   => array( 'source' => 'dynamic' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Default view section.
	 */
	protected function register_start_section() {
		$this->start_controls_section(
			'section_start',
			array(
				'label' => __( 'Default view', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'first_flipbook',
			array(
				'label'       => __( 'Which flipbook opens first', 'kdna-flipbook' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'default'     => 1,
				'description' => __( 'The position in the list, so 1 is the first flipbook.', 'kdna-flipbook' ),
			)
		);

		$this->add_control(
			'start_page',
			array(
				'label'   => __( 'Start page', 'kdna-flipbook' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'default' => 1,
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Autoplay', 'kdna-flipbook' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-flipbook' ),
				'label_off'    => __( 'Off', 'kdna-flipbook' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'autoplay_delay',
			array(
				'label'     => __( 'Autoplay delay (seconds)', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 1,
				'default'   => 5,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Toolbar controls section.
	 */
	protected function register_controls_section() {
		$this->start_controls_section(
			'section_controls',
			array(
				'label' => __( 'Controls', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$controls = array(
			'ctrl_arrows'     => __( 'Previous and next arrows', 'kdna-flipbook' ),
			'ctrl_thumbnails' => __( 'Page thumbnails or index', 'kdna-flipbook' ),
			'ctrl_zoom'       => __( 'Zoom', 'kdna-flipbook' ),
			'ctrl_fullscreen' => __( 'Fullscreen', 'kdna-flipbook' ),
			'ctrl_toc'        => __( 'Table of contents', 'kdna-flipbook' ),
			'ctrl_download'   => __( 'Download the original PDF', 'kdna-flipbook' ),
			'ctrl_share'      => __( 'Share', 'kdna-flipbook' ),
			'ctrl_sound'      => __( 'Flip sound', 'kdna-flipbook' ),
			'ctrl_deeplink'   => __( 'Deep-linking', 'kdna-flipbook' ),
		);

		foreach ( $controls as $key => $label ) {
			$this->add_control(
				$key,
				array(
					'label'        => $label,
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'kdna-flipbook' ),
					'label_off'    => __( 'Hide', 'kdna-flipbook' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * Sidebar section.
	 */
	protected function register_sidebar_section() {
		$this->start_controls_section(
			'section_sidebar',
			array(
				'label' => __( 'Sidebar', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_sidebar',
			array(
				'label'        => __( 'Sidebar', 'kdna-flipbook' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-flipbook' ),
				'label_off'    => __( 'Hide', 'kdna-flipbook' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'sidebar_mobile_note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'On mobile the sidebar moves above the flipbook.', 'kdna-flipbook' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Toolbar behaviour and chrome section.
	 */
	protected function register_chrome_section() {
		$this->start_controls_section(
			'section_chrome',
			array(
				'label' => __( 'Toolbar and chrome', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'toolbar_behaviour',
			array(
				'label'   => __( 'Toolbar behaviour', 'kdna-flipbook' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'fade',
				'options' => array(
					'fade'       => __( 'Fade away while reading', 'kdna-flipbook' ),
					'persistent' => __( 'Always visible', 'kdna-flipbook' ),
				),
			)
		);

		$this->add_control(
			'theme',
			array(
				'label'   => __( 'Chrome theme', 'kdna-flipbook' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'light',
				'options' => array(
					'light'  => __( 'Light', 'kdna-flipbook' ),
					'dark'   => __( 'Dark', 'kdna-flipbook' ),
					'custom' => __( 'Custom colour', 'kdna-flipbook' ),
				),
			)
		);

		$this->add_control(
			'custom_color',
			array(
				'label'     => __( 'Custom chrome colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#2271b1',
				'condition' => array( 'theme' => 'custom' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Build a list of client entries for the source select.
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
	 * Resolve the post the flipbooks are read from.
	 *
	 * @param array $settings Widget settings.
	 * @return int
	 */
	protected function resolve_post_id( $settings ) {
		$source = isset( $settings['source'] ) ? $settings['source'] : 'current';

		if ( 'specific' === $source && ! empty( $settings['specific_post'] ) ) {
			return (int) $settings['specific_post'];
		}

		if ( 'dynamic' === $source && ! empty( $settings['source_dynamic'] ) ) {
			return (int) $settings['source_dynamic'];
		}

		$current = get_the_ID();
		return $current ? (int) $current : (int) get_queried_object_id();
	}

	/**
	 * Render the widget.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$post_id  = $this->resolve_post_id( $settings );

		$rows      = $post_id ? Kdna_Flipbook_Meta::get_rows( $post_id ) : array();
		$flipbooks = Kdna_Flipbook_Assets::build_flipbooks_from_rows( $rows );

		if ( empty( $flipbooks ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="kdna-flipbook__notice">' . esc_html__( 'No flipbooks found for this page yet. Add some in the client entry, or choose a different source.', 'kdna-flipbook' ) . '</div>';
			}
			return;
		}

		$args = array(
			'active'            => max( 0, (int) $settings['first_flipbook'] - 1 ),
			'start_page'        => max( 1, (int) $settings['start_page'] ),
			'arrows'            => 'yes' === $settings['ctrl_arrows'],
			'thumbnails'        => 'yes' === $settings['ctrl_thumbnails'],
			'zoom'              => 'yes' === $settings['ctrl_zoom'],
			'fullscreen'        => 'yes' === $settings['ctrl_fullscreen'],
			'toc'               => 'yes' === $settings['ctrl_toc'],
			'download'          => 'yes' === $settings['ctrl_download'],
			'share'             => 'yes' === $settings['ctrl_share'],
			'sound'             => 'yes' === $settings['ctrl_sound'],
			'deeplink'          => 'yes' === $settings['ctrl_deeplink'],
			'sidebar'           => 'yes' === $settings['show_sidebar'],
			'autoplay'          => 'yes' === $settings['autoplay'],
			'autoplay_delay'    => max( 1, (int) $settings['autoplay_delay'] ),
			'toolbar_behaviour' => $settings['toolbar_behaviour'],
			'theme'             => $settings['theme'],
			'custom_color'      => ! empty( $settings['custom_color'] ) ? $settings['custom_color'] : '#2271b1',
		);

		Kdna_Flipbook_Assets::enqueue();

		// render_with_gate returns the single wrapper div, or the access code box.
		echo Kdna_Flipbook_Access::render_with_gate( $post_id, $flipbooks, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped within the renderer.
	}
}
