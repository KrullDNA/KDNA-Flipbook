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
			// Front-end defaults are stored here from later stages.
			'defaults'     => array(),
		);
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

		// Hidden page (no parent) that hosts the first-run setup screen.
		add_submenu_page(
			'',
			__( 'KDNA PDF Flipbook Setup', 'kdna-flipbook' ),
			__( 'KDNA PDF Flipbook Setup', 'kdna-flipbook' ),
			'manage_options',
			self::SETUP_SLUG,
			array( $this, 'render_setup_page' )
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
	}

	/**
	 * Intro copy for the CPT section.
	 */
	public function render_cpt_section_intro() {
		echo '<p>' . esc_html__( 'Name the custom post type that holds each client page. You can change these at any time.', 'kdna-flipbook' ) . '</p>';
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

		// Saving the settings marks the plugin as configured.
		$clean['configured'] = true;

		// Rewrite rules need refreshing after the slug may have changed.
		set_transient( 'kdna_flipbook_flush_rewrite', 1, 60 );

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

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETUP_SLUG ) );
		exit;
	}
}
