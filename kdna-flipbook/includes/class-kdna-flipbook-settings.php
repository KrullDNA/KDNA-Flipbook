<?php
/**
 * Settings and first-run setup for KDNA PDF Flipbook.
 *
 * Owns the single option that stores the custom post type naming (singular label,
 * plural label and slug) and leaves room for front-end defaults added in later
 * stages. Also renders the Settings > KDNA PDF Flipbook screen and the first-run
 * setup screen shown straight after activation.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings handler.
 */
class Kdna_Flipbook_Settings {

	/**
	 * Option key that stores every plugin setting.
	 */
	const OPTION_KEY = 'kdna_flipbook_settings';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'kdna-flipbook-settings';

	/**
	 * First-run setup page slug.
	 */
	const SETUP_SLUG = 'kdna-flipbook-setup';

	/**
	 * Constructor. Registers the admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );
		add_action( 'admin_post_kdna_flipbook_save_setup', array( $this, 'handle_setup_save' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
	}

	/**
	 * Return the full settings array merged over the defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Default settings.
	 *
	 * The CPT falls back to Clients / Client / kdna-client until Nick names it.
	 * The defaults array is a placeholder for front-end defaults added in later
	 * stages.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'cpt_singular' => __( 'Client', 'kdna-flipbook' ),
			'cpt_plural'   => __( 'Clients', 'kdna-flipbook' ),
			'cpt_slug'     => 'kdna-client',
			'configured'   => false,
			// Front-end defaults. From Stage 7 the widget can override these per instance.
			'defaults'     => self::get_frontend_defaults(),
		);
	}

	/**
	 * Default front-end view options.
	 *
	 * Each control can be shown or hidden, the toolbar can fade or stay put, and
	 * the chrome theme is light, dark or a custom colour.
	 *
	 * @return array
	 */
	public static function get_frontend_defaults() {
		return array(
			'arrows'            => true,
			'thumbnails'        => true,
			'zoom'              => true,
			'wheel_zoom'        => false,
			'fullscreen'        => true,
			'toc'               => true,
			'download'          => true,
			'share'             => true,
			'sound'             => true,
			'deeplink'          => true,
			'sidebar'           => true,
			'autoplay'          => false,
			'autoplay_delay'    => 5,
			'toolbar_behaviour' => 'fade',
			'toolbar_position'  => 'over',
			'theme'             => 'light',
			'custom_color'      => '#2271b1',
			'hint_show'         => true,
			'hint_position'     => 'sidebar',
			'hint_text'         => __( 'Move your mouse off the document and the navigation will disappear.', 'kdna-flipbook' ),
		);
	}

	/**
	 * Read the merged front-end defaults.
	 *
	 * @return array
	 */
	public static function get_frontend() {
		$saved = self::get( 'defaults', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::get_frontend_defaults() );
	}

	/**
	 * Read a single setting by key.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if not set.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Has the CPT been named by Nick yet.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return (bool) self::get( 'configured', false );
	}

	/**
	 * Register the settings page and the hidden setup page.
	 */
	public function register_menus() {
		add_options_page(
			__( 'KDNA PDF Flipbook', 'kdna-flipbook' ),
			__( 'KDNA PDF Flipbook', 'kdna-flipbook' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);

		// First-run setup screen. Registered under Settings so it is reliably
		// reachable, then removed from the menu so it stays hidden.
		add_submenu_page(
			'options-general.php',
			__( 'KDNA PDF Flipbook Setup', 'kdna-flipbook' ),
			__( 'KDNA PDF Flipbook Setup', 'kdna-flipbook' ),
			'manage_options',
			self::SETUP_SLUG,
			array( $this, 'render_setup_page' )
		);
		remove_submenu_page( 'options-general.php', self::SETUP_SLUG );
	}

	/**
	 * The URL of the first-run setup screen.
	 *
	 * @return string
	 */
	public static function setup_url() {
		return admin_url( 'options-general.php?page=' . self::SETUP_SLUG );
	}

	/**
	 * Prompt to complete setup until the CPT has been named.
	 *
	 * This is the reliable prompt. The activation redirect is a convenience on top
	 * of it, but can be swallowed by some hosts, so this notice always shows the
	 * way to the setup screen.
	 */
	public function setup_notice() {
		if ( self::is_configured() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Do not show it on the setup screen itself.
		if ( isset( $_GET['page'] ) && self::SETUP_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'KDNA PDF Flipbook', 'kdna-flipbook' ),
			esc_html__( 'Before you start, choose what to call the pages that hold your flipbooks.', 'kdna-flipbook' ),
			esc_url( self::setup_url() ),
			esc_html__( 'Name your pages', 'kdna-flipbook' )
		);
	}

	/**
	 * Register the settings, sections and fields using the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'kdna_flipbook_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'kdna_flipbook_cpt_section',
			__( 'Custom post type name', 'kdna-flipbook' ),
			array( $this, 'render_cpt_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'kdna_flipbook_cpt_singular',
			__( 'Singular label', 'kdna-flipbook' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'kdna_flipbook_cpt_section',
			array(
				'key'         => 'cpt_singular',
				'placeholder' => __( 'Client', 'kdna-flipbook' ),
				'description' => __( 'The singular name, for example Client, Pitch or Proposal.', 'kdna-flipbook' ),
			)
		);

		add_settings_field(
			'kdna_flipbook_cpt_plural',
			__( 'Plural label', 'kdna-flipbook' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'kdna_flipbook_cpt_section',
			array(
				'key'         => 'cpt_plural',
				'placeholder' => __( 'Clients', 'kdna-flipbook' ),
				'description' => __( 'The plural name shown in the admin menu, for example Clients, Pitches or Proposals.', 'kdna-flipbook' ),
			)
		);

		add_settings_field(
			'kdna_flipbook_cpt_slug',
			__( 'Slug', 'kdna-flipbook' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'kdna_flipbook_cpt_section',
			array(
				'key'         => 'cpt_slug',
				'placeholder' => 'kdna-client',
				'description' => __( 'Used in the post type key and the URL. Lowercase letters, numbers and hyphens, up to 20 characters. Changing this after entries exist will change their URLs.', 'kdna-flipbook' ),
			)
		);

		add_settings_section(
			'kdna_flipbook_frontend_section',
			__( 'Front-end defaults', 'kdna-flipbook' ),
			array( $this, 'render_frontend_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'kdna_flipbook_frontend',
			__( 'Viewer defaults', 'kdna-flipbook' ),
			array( $this, 'render_frontend_fields' ),
			self::PAGE_SLUG,
			'kdna_flipbook_frontend_section'
		);
	}

	/**
	 * Intro copy for the CPT section.
	 */
	public function render_cpt_section_intro() {
		echo '<p>' . esc_html__( 'Name the custom post type that holds each client page. You can change these at any time.', 'kdna-flipbook' ) . '</p>';
	}

	/**
	 * Intro copy for the front-end defaults section.
	 */
	public function render_frontend_section_intro() {
		echo '<p>' . esc_html__( 'Defaults for the viewer toolbar and chrome. From the Elementor widget stage these can be overridden per widget.', 'kdna-flipbook' ) . '</p>';
	}

	/**
	 * Render the front-end defaults fields.
	 */
	public function render_frontend_fields() {
		$config = self::get_frontend();
		$name   = self::OPTION_KEY . '[defaults]';

		$controls = array(
			'arrows'     => __( 'Previous and next arrows', 'kdna-flipbook' ),
			'thumbnails' => __( 'Page thumbnails or index strip', 'kdna-flipbook' ),
			'zoom'       => __( 'Zoom', 'kdna-flipbook' ),
			'fullscreen' => __( 'Fullscreen', 'kdna-flipbook' ),
			'toc'        => __( 'Table of contents', 'kdna-flipbook' ),
			'download'   => __( 'Download the original PDF', 'kdna-flipbook' ),
			'share'      => __( 'Share', 'kdna-flipbook' ),
			'sound'      => __( 'Flip sound', 'kdna-flipbook' ),
			'deeplink'   => __( 'Deep-linking', 'kdna-flipbook' ),
			'sidebar'    => __( 'Sidebar', 'kdna-flipbook' ),
		);

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Toolbar controls', 'kdna-flipbook' ) . '</legend>';

		foreach ( $controls as $key => $label ) {
			printf(
				'<label style="display:block;margin:2px 0;"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $key ),
				checked( ! empty( $config[ $key ] ), true, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';

		// Wheel zoom.
		echo '<p style="margin-top:12px;"><label><input type="checkbox" name="' . esc_attr( $name ) . '[wheel_zoom]" value="1" ' . checked( ! empty( $config['wheel_zoom'] ), true, false ) . ' /> <strong>' . esc_html__( 'Zoom with the mouse wheel', 'kdna-flipbook' ) . '</strong></label>';
		echo '<span class="description" style="display:block;">' . esc_html__( 'Off by default so scrolling over the flipbook scrolls the page. The zoom buttons and pinch on mobile still work.', 'kdna-flipbook' ) . '</span></p>';

		// Toolbar behaviour.
		echo '<p style="margin-top:12px;"><strong>' . esc_html__( 'Toolbar behaviour', 'kdna-flipbook' ) . '</strong></p>';
		echo '<select name="' . esc_attr( $name ) . '[toolbar_behaviour]">';
		foreach ( array(
			'fade'       => __( 'Fade away while reading', 'kdna-flipbook' ),
			'persistent' => __( 'Always visible', 'kdna-flipbook' ),
		) as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $config['toolbar_behaviour'], $value, false ), esc_html( $label ) );
		}
		echo '</select>';

		// Toolbar position.
		echo '<p style="margin-top:12px;"><strong>' . esc_html__( 'Toolbar position', 'kdna-flipbook' ) . '</strong></p>';
		echo '<select name="' . esc_attr( $name ) . '[toolbar_position]">';
		foreach ( array(
			'over'  => __( 'Over the document', 'kdna-flipbook' ),
			'above' => __( 'Above the flipbook', 'kdna-flipbook' ),
			'below' => __( 'Below the flipbook', 'kdna-flipbook' ),
		) as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $config['toolbar_position'], $value, false ), esc_html( $label ) );
		}
		echo '</select>';

		// Hint text.
		echo '<p style="margin-top:12px;"><label><input type="checkbox" name="' . esc_attr( $name ) . '[hint_show]" value="1" ' . checked( ! empty( $config['hint_show'] ), true, false ) . ' /> <strong>' . esc_html__( 'Show a hint for readers', 'kdna-flipbook' ) . '</strong></label></p>';
		echo '<select name="' . esc_attr( $name ) . '[hint_position]">';
		foreach ( array(
			'sidebar' => __( 'Top of the sidebar', 'kdna-flipbook' ),
			'below'   => __( 'Below the flipbook', 'kdna-flipbook' ),
		) as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $config['hint_position'], $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		printf(
			'<p><input type="text" name="%1$s[hint_text]" value="%2$s" class="large-text" /></p>',
			esc_attr( $name ),
			esc_attr( $config['hint_text'] )
		);
		echo '<p class="description">' . esc_html__( 'A short line of copy for readers, for example explaining that moving off the document hides the toolbar. Top of the sidebar shows it above the document list.', 'kdna-flipbook' ) . '</p>';

		// Chrome theme.
		echo '<p style="margin-top:12px;"><strong>' . esc_html__( 'Chrome theme', 'kdna-flipbook' ) . '</strong></p>';
		echo '<select name="' . esc_attr( $name ) . '[theme]">';
		foreach ( array(
			'light'  => __( 'Light', 'kdna-flipbook' ),
			'dark'   => __( 'Dark', 'kdna-flipbook' ),
			'custom' => __( 'Custom colour', 'kdna-flipbook' ),
		) as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $config['theme'], $value, false ), esc_html( $label ) );
		}
		echo '</select> ';
		printf(
			'<input type="text" name="%1$s[custom_color]" value="%2$s" placeholder="#2271b1" class="regular-text" style="max-width:120px;" />',
			esc_attr( $name ),
			esc_attr( $config['custom_color'] )
		);
		echo '<p class="description">' . esc_html__( 'The custom colour is used when the chrome theme is set to Custom colour.', 'kdna-flipbook' ) . '</p>';
	}

	/**
	 * Render a single text field on the settings page.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$settings = self::get_settings();
		$key      = isset( $args['key'] ) ? $args['key'] : '';
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s[%1$s]" value="%3$s" placeholder="%4$s" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value ),
			esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Sanitise the settings array before it is saved.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$existing = self::get_settings();
		$clean    = $existing;

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		if ( isset( $input['cpt_singular'] ) ) {
			$clean['cpt_singular'] = sanitize_text_field( $input['cpt_singular'] );
		}

		if ( isset( $input['cpt_plural'] ) ) {
			$clean['cpt_plural'] = sanitize_text_field( $input['cpt_plural'] );
		}

		if ( isset( $input['cpt_slug'] ) ) {
			$clean['cpt_slug'] = self::sanitize_slug( $input['cpt_slug'] );
		}

		// Fill any blanks from the defaults so the CPT always has usable labels.
		$defaults = self::get_defaults();
		foreach ( array( 'cpt_singular', 'cpt_plural', 'cpt_slug' ) as $field ) {
			if ( empty( $clean[ $field ] ) ) {
				$clean[ $field ] = $defaults[ $field ];
			}
		}

		// Front-end defaults.
		if ( isset( $input['defaults'] ) ) {
			$clean['defaults'] = self::sanitize_frontend( $input['defaults'] );
		}

		// Saving the settings marks the plugin as configured.
		$clean['configured'] = true;

		// Rewrite rules need refreshing after the slug may have changed.
		set_transient( 'kdna_flipbook_flush_rewrite', 1, 60 );

		return $clean;
	}

	/**
	 * Sanitise the front-end defaults.
	 *
	 * @param mixed $input Raw defaults input.
	 * @return array
	 */
	public static function sanitize_frontend( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$defaults = self::get_frontend_defaults();
		$clean    = array();

		// Toggle controls: a checkbox is present only when ticked.
		$toggles = array( 'arrows', 'thumbnails', 'zoom', 'wheel_zoom', 'fullscreen', 'toc', 'download', 'share', 'sound', 'deeplink', 'sidebar', 'hint_show' );
		foreach ( $toggles as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		$behaviour                  = isset( $input['toolbar_behaviour'] ) ? sanitize_key( $input['toolbar_behaviour'] ) : '';
		$clean['toolbar_behaviour'] = in_array( $behaviour, array( 'fade', 'persistent' ), true ) ? $behaviour : $defaults['toolbar_behaviour'];

		$position                  = isset( $input['toolbar_position'] ) ? sanitize_key( $input['toolbar_position'] ) : '';
		$clean['toolbar_position'] = in_array( $position, array( 'over', 'below', 'above' ), true ) ? $position : $defaults['toolbar_position'];

		$hint_position          = isset( $input['hint_position'] ) ? sanitize_key( $input['hint_position'] ) : '';
		$clean['hint_position'] = in_array( $hint_position, array( 'sidebar', 'below' ), true ) ? $hint_position : $defaults['hint_position'];

		$clean['hint_text'] = isset( $input['hint_text'] ) ? sanitize_text_field( $input['hint_text'] ) : $defaults['hint_text'];

		$theme          = isset( $input['theme'] ) ? sanitize_key( $input['theme'] ) : '';
		$clean['theme'] = in_array( $theme, array( 'light', 'dark', 'custom' ), true ) ? $theme : $defaults['theme'];

		$color                 = isset( $input['custom_color'] ) ? sanitize_hex_color( $input['custom_color'] ) : '';
		$clean['custom_color'] = $color ? $color : $defaults['custom_color'];

		return $clean;
	}

	/**
	 * Sanitise a post type slug: lowercase, safe characters, max 20 chars.
	 *
	 * @param string $slug Raw slug.
	 * @return string
	 */
	public static function sanitize_slug( $slug ) {
		$slug = sanitize_key( $slug );
		$slug = str_replace( '_', '-', $slug );

		return substr( $slug, 0, 20 );
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'KDNA PDF Flipbook', 'kdna-flipbook' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'kdna_flipbook_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the first-run setup page by handing off to the view file.
	 */
	public function render_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		require KDNA_FLIPBOOK_DIR . 'admin/setup-page.php';
	}

	/**
	 * Handle the first-run setup form submission.
	 */
	public function handle_setup_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'kdna-flipbook' ) );
		}

		check_admin_referer( 'kdna_flipbook_setup', 'kdna_flipbook_setup_nonce' );

		$settings = self::get_settings();

		$settings['cpt_singular'] = isset( $_POST['cpt_singular'] ) ? sanitize_text_field( wp_unslash( $_POST['cpt_singular'] ) ) : '';
		$settings['cpt_plural']   = isset( $_POST['cpt_plural'] ) ? sanitize_text_field( wp_unslash( $_POST['cpt_plural'] ) ) : '';
		$settings['cpt_slug']     = isset( $_POST['cpt_slug'] ) ? self::sanitize_slug( wp_unslash( $_POST['cpt_slug'] ) ) : '';

		// Fall back to sensible defaults for any blank field.
		$defaults = self::get_defaults();
		foreach ( array( 'cpt_singular', 'cpt_plural', 'cpt_slug' ) as $field ) {
			if ( empty( $settings[ $field ] ) ) {
				$settings[ $field ] = $defaults[ $field ];
			}
		}

		$settings['configured'] = true;

		update_option( self::OPTION_KEY, $settings );

		// The CPT slug may now exist, so refresh rewrite rules on the next load.
		set_transient( 'kdna_flipbook_flush_rewrite', 1, 60 );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . rawurlencode( $settings['cpt_slug'] ) . '&kdna-flipbook-setup=done' ) );
		exit;
	}

	/**
	 * After activation, send Nick to the setup screen once.
	 */
	public function maybe_redirect_to_setup() {
		if ( ! get_transient( 'kdna_flipbook_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'kdna_flipbook_activation_redirect' );

		// Do not hijack bulk activations or AJAX.
		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( self::is_configured() ) {
			return;
		}

		wp_safe_redirect( self::setup_url() );
		exit;
	}
}
