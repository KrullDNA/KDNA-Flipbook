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

		// Style tab.
		$this->register_style_viewer();
		$this->register_style_sidebar();
		$this->register_style_toolbar();
		$this->register_style_thumbs();
		$this->register_style_meta();
		$this->register_style_zoom();
		$this->register_style_spinner();
		$this->register_style_gate();
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

	/* -------------------------------------------------------------------------
	 * Style tab. Every control writes a scoped custom property or property on
	 * {{WRAPPER}}, so nothing leaks to other widgets.
	 * ---------------------------------------------------------------------- */

	/**
	 * Style: Viewer.
	 */
	protected function register_style_viewer() {
		$this->start_controls_section(
			'section_style_viewer',
			array(
				'label' => __( 'Viewer', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'viewer_max_width',
			array(
				'label'      => __( 'Maximum width', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array(
					'px' => array( 'min' => 320, 'max' => 2000 ),
					'%'  => array( 'min' => 20, 'max' => 100 ),
					'vw' => array( 'min' => 20, 'max' => 100 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'viewer_aspect_ratio',
			array(
				'label'       => __( 'Aspect ratio (optional)', 'kdna-flipbook' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '3 / 2',
				'description' => __( 'For example 3 / 2 or 16 / 9. Leave empty to size to the PDF.', 'kdna-flipbook' ),
				'selectors'   => array(
					'{{WRAPPER}} .kdna-flipbook__stage' => 'aspect-ratio: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'viewer_bg',
			array(
				'label'     => __( 'Background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'page_bg',
			array(
				'label'     => __( 'Page background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-page-bg: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'page_radius',
			array(
				'label'      => __( 'Page corner radius', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'page_shadow',
				'label'    => __( 'Page shadow', 'kdna-flipbook' ),
				'selector' => '{{WRAPPER}} .kdna-flipbook__page',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Sidebar.
	 */
	protected function register_style_sidebar() {
		$this->start_controls_section(
			'section_style_sidebar',
			array(
				'label'     => __( 'Sidebar', 'kdna-flipbook' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_sidebar' => 'yes' ),
			)
		);

		$this->add_control(
			'sidebar_bg',
			array(
				'label'     => __( 'Background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-sidebar-bg: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'sidebar_width',
			array(
				'label'      => __( 'Width', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 120, 'max' => 480 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-sidebar-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'sidebar_padding',
			array(
				'label'      => __( 'Padding', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook__sidebar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'sidebar_item_gap',
			array(
				'label'      => __( 'Item spacing', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-item-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'sidebar_item_typography',
				'label'    => __( 'Item typography', 'kdna-flipbook' ),
				'selector' => '{{WRAPPER}} .kdna-flipbook__item-name',
			)
		);

		$this->add_control(
			'sidebar_icon_color',
			array(
				'label'     => __( 'Icon colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-icon-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'sidebar_icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 64 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'sidebar_item_color',
			array(
				'label'     => __( 'Item text colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-item-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'sidebar_active_heading',
			array(
				'label'     => __( 'Active item', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'sidebar_active_bg',
			array(
				'label'     => __( 'Active background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-item-active-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'sidebar_active_color',
			array(
				'label'     => __( 'Active text colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-item-active-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'sidebar_hover_heading',
			array(
				'label'     => __( 'Hover', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'sidebar_hover_bg',
			array(
				'label'     => __( 'Hover background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-item-hover-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'sidebar_hover_color',
			array(
				'label'     => __( 'Hover text colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook__item:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'sidebar_divider_heading',
			array(
				'label'     => __( 'Divider', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'sidebar_divider_width',
			array(
				'label'      => __( 'Divider width', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 6 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook__list-item:not(:last-child)' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-bottom-style: solid; padding-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'sidebar_divider_color',
			array(
				'label'     => __( 'Divider colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook__list-item:not(:last-child)' => 'border-bottom-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Toolbar and buttons.
	 */
	protected function register_style_toolbar() {
		$this->start_controls_section(
			'section_style_toolbar',
			array(
				'label' => __( 'Toolbar and buttons', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'toolbar_bg',
			array(
				'label'     => __( 'Toolbar background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-toolbar-bg: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'toolbar_padding',
			array(
				'label'      => __( 'Toolbar padding', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook__toolbar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'toolbar_radius',
			array(
				'label'      => __( 'Toolbar radius', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-toolbar-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'toolbar_spacing',
			array(
				'label'      => __( 'Button spacing', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook__toolbar' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'toolbar_border',
				'selector' => '{{WRAPPER}} .kdna-flipbook__toolbar',
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Button colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-toolbar-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_bg',
			array(
				'label'     => __( 'Button hover background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-toolbar-hover-bg: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_size',
			array(
				'label'      => __( 'Button size', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 28, 'max' => 64 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-btn-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_radius',
			array(
				'label'      => __( 'Button radius', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-btn-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 12, 'max' => 32 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-toolbar-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Thumbnails.
	 */
	protected function register_style_thumbs() {
		$this->start_controls_section(
			'section_style_thumbs',
			array(
				'label'     => __( 'Thumbnails', 'kdna-flipbook' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'ctrl_thumbnails' => 'yes' ),
			)
		);

		$this->add_control(
			'thumbs_bg',
			array(
				'label'     => __( 'Strip background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-thumbs-bg: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'thumb_width',
			array(
				'label'      => __( 'Thumbnail size', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 48, 'max' => 200 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-thumb-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'thumb_gap',
			array(
				'label'      => __( 'Spacing', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-thumb-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'thumb_active',
			array(
				'label'     => __( 'Active border colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-thumb-active: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'thumb_hover_bg',
			array(
				'label'     => __( 'Hover background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-thumb-hover-bg: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'thumb_border',
				'selector' => '{{WRAPPER}} .kdna-flipbook__thumb',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Page numbers and captions.
	 */
	protected function register_style_meta() {
		$this->start_controls_section(
			'section_style_meta',
			array(
				'label' => __( 'Page numbers and captions', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'meta_color',
			array(
				'label'     => __( 'Colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-meta-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'meta_typography',
				'selector' => '{{WRAPPER}} .kdna-flipbook__page-count, {{WRAPPER}} .kdna-flipbook__zoom-level',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Zoom and fullscreen.
	 */
	protected function register_style_zoom() {
		$this->start_controls_section(
			'section_style_zoom',
			array(
				'label' => __( 'Zoom and fullscreen', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'zoom_overlay_bg',
			array(
				'label'     => __( 'Zoom overlay background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-zoom-overlay-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'fullscreen_bg',
			array(
				'label'     => __( 'Fullscreen background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-fullscreen-bg: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Loading spinner.
	 */
	protected function register_style_spinner() {
		$this->start_controls_section(
			'section_style_spinner',
			array(
				'label' => __( 'Loading spinner', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'spinner_color',
			array(
				'label'     => __( 'Colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-spinner-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'spinner_size',
			array(
				'label'      => __( 'Size', 'kdna-flipbook' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 16, 'max' => 96 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-flipbook' => '--kdna-flipbook-spinner-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Access code box.
	 */
	protected function register_style_gate() {
		$this->start_controls_section(
			'section_style_gate',
			array(
				'label' => __( 'Access code box', 'kdna-flipbook' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'gate_bg',
			array(
				'label'     => __( 'Box background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate' => '--kdna-flipbook-gate-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'gate_heading_color',
			array(
				'label'     => __( 'Heading colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate__heading' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'gate_heading_typography',
				'label'    => __( 'Heading typography', 'kdna-flipbook' ),
				'selector' => '{{WRAPPER}} .kdna-flipbook-gate__heading',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'gate_help_typography',
				'label'    => __( 'Helper text typography', 'kdna-flipbook' ),
				'selector' => '{{WRAPPER}} .kdna-flipbook-gate__help',
			)
		);

		$this->add_control(
			'gate_input_heading',
			array(
				'label'     => __( 'Input', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'gate_input_bg',
			array(
				'label'     => __( 'Input background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate' => '--kdna-flipbook-gate-input-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'gate_input_border',
			array(
				'label'     => __( 'Input border colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate' => '--kdna-flipbook-gate-input-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'gate_button_heading',
			array(
				'label'     => __( 'Button', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'gate_button_bg',
			array(
				'label'     => __( 'Button background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate' => '--kdna-flipbook-gate-button-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'gate_button_color',
			array(
				'label'     => __( 'Button text colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate' => '--kdna-flipbook-gate-button-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'gate_button_hover_bg',
			array(
				'label'     => __( 'Button hover background', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate' => '--kdna-flipbook-gate-button-hover-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'gate_error_color',
			array(
				'label'     => __( 'Error message colour', 'kdna-flipbook' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .kdna-flipbook-gate' => '--kdna-flipbook-gate-error-color: {{VALUE}};',
				),
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
