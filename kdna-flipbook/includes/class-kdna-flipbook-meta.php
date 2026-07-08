<?php
/**
 * Metaboxes for KDNA PDF Flipbook client entries.
 *
 * Adds two metaboxes to the client custom post type:
 *
 * 1. Flipbooks: a drag-to-reorder repeater. Each row has a name, a PDF chosen
 *    with the standard WordPress media uploader, an optional icon, and a hidden
 *    sort-order value.
 * 2. Access code: a single field. Empty means the page is open.
 *
 * Both are stored as post meta. Input is sanitised, output is escaped, and the
 * save is protected with a nonce.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metabox handler.
 */
class Kdna_Flipbook_Meta {

	/**
	 * Meta key for the flipbook repeater.
	 */
	const META_ROWS = '_kdna_flipbook_rows';

	/**
	 * Meta key for the access code.
	 */
	const META_ACCESS_CODE = '_kdna_flipbook_access_code';

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'kdna_flipbook_save_meta';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'kdna_flipbook_meta_nonce';

	/**
	 * Constructor. Registers the metabox, save and asset hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the metaboxes on the client custom post type.
	 */
	public function add_meta_boxes() {
		$post_type = Kdna_Flipbook_Cpt::get_post_type();

		add_meta_box(
			'kdna_flipbook_flipbooks',
			__( 'Flipbooks', 'kdna-flipbook' ),
			array( $this, 'render_flipbooks_metabox' ),
			$post_type,
			'normal',
			'high'
		);

		add_meta_box(
			'kdna_flipbook_access',
			__( 'Access code', 'kdna-flipbook' ),
			array( $this, 'render_access_metabox' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Read the saved flipbook rows for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of rows, each with name, pdf_id, icon_id, sort.
	 */
	public static function get_rows( $post_id ) {
		$rows = get_post_meta( $post_id, self::META_ROWS, true );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return $rows;
	}

	/**
	 * Read the saved access code for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_access_code( $post_id ) {
		$code = get_post_meta( $post_id, self::META_ACCESS_CODE, true );

		return is_string( $code ) ? $code : '';
	}

	/**
	 * Render the Flipbooks repeater metabox.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_flipbooks_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$rows = self::get_rows( $post->ID );
		?>
		<div class="kdna-flipbook-repeater" id="kdna-flipbook-repeater">
			<p class="kdna-flipbook-repeater__intro">
				<?php esc_html_e( 'Add each PDF you want to show on this page. Drag the rows by the handle to change their order. The first row appears first.', 'kdna-flipbook' ); ?>
			</p>

			<ul class="kdna-flipbook-rows" id="kdna-flipbook-rows">
				<?php
				if ( ! empty( $rows ) ) {
					$index = 0;
					foreach ( $rows as $row ) {
						$this->render_row( $index, $row );
						$index++;
					}
				}
				?>
			</ul>

			<p class="kdna-flipbook-repeater__actions">
				<button type="button" class="button button-secondary" id="kdna-flipbook-add-row">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add flipbook', 'kdna-flipbook' ); ?>
				</button>
			</p>

			<p class="kdna-flipbook-empty" id="kdna-flipbook-empty" <?php echo empty( $rows ) ? '' : 'style="display:none;"'; ?>>
				<?php esc_html_e( 'No flipbooks yet. Click Add flipbook to add your first PDF.', 'kdna-flipbook' ); ?>
			</p>
		</div>

		<?php
		// The template used by the JS to add new rows. Kept out of the form flow
		// so its fields are never submitted until cloned into the list.
		?>
		<script type="text/html" id="kdna-flipbook-row-template">
			<?php $this->render_row( '__INDEX__', array() ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single repeater row.
	 *
	 * Used both to print existing rows and, with a placeholder index, to build the
	 * JS template. The index is only a form-array key, the real order is carried by
	 * the hidden sort field and re-sorted on save.
	 *
	 * @param int|string $index Row index or the __INDEX__ placeholder.
	 * @param array      $row   Saved row data.
	 */
	protected function render_row( $index, $row ) {
		$name     = isset( $row['name'] ) ? $row['name'] : '';
		$pdf_id   = isset( $row['pdf_id'] ) ? (int) $row['pdf_id'] : 0;
		$icon_id  = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
		$icon_key = isset( $row['icon_key'] ) ? sanitize_key( $row['icon_key'] ) : '';
		$sort     = isset( $row['sort'] ) ? (int) $row['sort'] : 0;

		if ( $icon_key && ! Kdna_Flipbook_Assets::is_builtin_icon( $icon_key ) ) {
			$icon_key = '';
		}

		$pdf_name = $pdf_id ? get_the_title( $pdf_id ) : '';
		$pdf_url  = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';

		$icon_url = '';
		if ( $icon_id ) {
			$icon_url = wp_get_attachment_image_url( $icon_id, 'thumbnail' );
			if ( ! $icon_url ) {
				$icon_url = wp_get_attachment_url( $icon_id );
			}
		}

		$has_pdf   = $pdf_id && $pdf_url;
		$has_upload = $icon_id && $icon_url;
		$has_builtin = ! $has_upload && $icon_key;
		$has_icon  = $has_upload || $has_builtin;
		$field     = 'kdna_flipbook_rows[' . $index . ']';
		?>
		<li class="kdna-flipbook-row" data-index="<?php echo esc_attr( $index ); ?>">
			<span class="kdna-flipbook-row__handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'kdna-flipbook' ); ?>" aria-hidden="true"></span>

			<span class="kdna-flipbook-row__body">
				<span class="kdna-flipbook-field kdna-flipbook-field--name">
					<label class="kdna-flipbook-field__label">
						<?php esc_html_e( 'Name', 'kdna-flipbook' ); ?>
						<input type="text" class="kdna-flipbook-input-name widefat" name="<?php echo esc_attr( $field ); ?>[name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'For example, Welcome letter', 'kdna-flipbook' ); ?>" />
					</label>
				</span>

				<span class="kdna-flipbook-field kdna-flipbook-field--pdf">
					<span class="kdna-flipbook-field__label"><?php esc_html_e( 'PDF', 'kdna-flipbook' ); ?></span>
					<input type="hidden" class="kdna-flipbook-input-pdf-id" name="<?php echo esc_attr( $field ); ?>[pdf_id]" value="<?php echo esc_attr( $pdf_id ); ?>" />
					<span class="kdna-flipbook-pdf-name" <?php echo $has_pdf ? '' : 'style="display:none;"'; ?>><span class="dashicons dashicons-media-document" aria-hidden="true"></span><span class="kdna-flipbook-pdf-name__text"><?php echo esc_html( $pdf_name ); ?></span></span>
					<button type="button" class="button kdna-flipbook-choose-pdf"><?php echo $has_pdf ? esc_html__( 'Change PDF', 'kdna-flipbook' ) : esc_html__( 'Choose PDF', 'kdna-flipbook' ); ?></button>
					<button type="button" class="button-link kdna-flipbook-remove-pdf" <?php echo $has_pdf ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'kdna-flipbook' ); ?></button>
				</span>

				<span class="kdna-flipbook-field kdna-flipbook-field--icon">
					<span class="kdna-flipbook-field__label"><?php esc_html_e( 'Icon (optional)', 'kdna-flipbook' ); ?></span>
					<input type="hidden" class="kdna-flipbook-input-icon-id" name="<?php echo esc_attr( $field ); ?>[icon_id]" value="<?php echo esc_attr( $icon_id ); ?>" />
					<input type="hidden" class="kdna-flipbook-input-icon-key" name="<?php echo esc_attr( $field ); ?>[icon_key]" value="<?php echo esc_attr( $icon_key ); ?>" />
					<span class="kdna-flipbook-icon-preview" <?php echo $has_icon ? '' : 'style="display:none;"'; ?>>
						<?php
						if ( $has_upload ) {
							echo '<img src="' . esc_url( $icon_url ) . '" alt="" />';
						} elseif ( $has_builtin ) {
							echo Kdna_Flipbook_Assets::builtin_icon_svg( $icon_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, safe inline SVG.
						}
						?>
					</span>
					<button type="button" class="button kdna-flipbook-toggle-iconpicker" aria-expanded="false"><?php echo $has_icon ? esc_html__( 'Change icon', 'kdna-flipbook' ) : esc_html__( 'Choose icon', 'kdna-flipbook' ); ?></button>
					<button type="button" class="button-link kdna-flipbook-remove-icon" <?php echo $has_icon ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'kdna-flipbook' ); ?></button>

					<span class="kdna-flipbook-iconpicker" hidden>
						<span class="kdna-flipbook-iconpicker__label"><?php esc_html_e( 'Choose a built-in icon', 'kdna-flipbook' ); ?></span>
						<span class="kdna-flipbook-iconpicker__grid">
							<?php foreach ( Kdna_Flipbook_Assets::builtin_icons() as $icon_slug => $icon_paths ) : ?>
								<button type="button" class="kdna-flipbook-iconpick<?php echo $icon_slug === $icon_key ? ' is-selected' : ''; ?>" data-icon="<?php echo esc_attr( $icon_slug ); ?>" title="<?php echo esc_attr( $icon_slug ); ?>">
									<?php echo Kdna_Flipbook_Assets::builtin_icon_svg( $icon_slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, safe inline SVG. ?>
								</button>
							<?php endforeach; ?>
						</span>
						<span class="kdna-flipbook-iconpicker__upload">
							<button type="button" class="button kdna-flipbook-choose-icon"><?php esc_html_e( 'Upload SVG or image', 'kdna-flipbook' ); ?></button>
						</span>
					</span>
				</span>

				<input type="hidden" class="kdna-flipbook-input-sort" name="<?php echo esc_attr( $field ); ?>[sort]" value="<?php echo esc_attr( $sort ); ?>" />
			</span>

			<button type="button" class="kdna-flipbook-row__remove button-link" title="<?php esc_attr_e( 'Remove this flipbook', 'kdna-flipbook' ); ?>">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Remove this flipbook', 'kdna-flipbook' ); ?></span>
			</button>
		</li>
		<?php
	}

	/**
	 * Render the Access code metabox.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_access_metabox( $post ) {
		// The Flipbooks metabox prints the shared save nonce, which covers this
		// metabox too since both render on the same edit form.
		$code = self::get_access_code( $post->ID );
		?>
		<p>
			<label for="kdna_flipbook_access_code">
				<?php esc_html_e( 'Set a code to gate this page. Leave empty to keep it open to everyone.', 'kdna-flipbook' ); ?>
			</label>
		</p>
		<p>
			<input type="text" class="widefat" id="kdna_flipbook_access_code" name="kdna_flipbook_access_code" value="<?php echo esc_attr( $code ); ?>" autocomplete="off" placeholder="<?php esc_attr_e( 'For example, PITCH2026', 'kdna-flipbook' ); ?>" />
		</p>
		<p class="description">
			<?php esc_html_e( 'Visitors must enter this code to view the flipbooks. Admins and editors always bypass it.', 'kdna-flipbook' ); ?>
		</p>
		<?php
	}

	/**
	 * Save the metaboxes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save( $post_id, $post ) {
		// Only handle our own post type.
		if ( Kdna_Flipbook_Cpt::get_post_type() !== $post->post_type ) {
			return;
		}

		// Skip autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Verify the nonce.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save_rows( $post_id );
		$this->save_access_code( $post_id );
	}

	/**
	 * Sanitise and save the flipbook rows.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function save_rows( $post_id ) {
		$raw = isset( $_POST['kdna_flipbook_rows'] ) ? wp_unslash( $_POST['kdna_flipbook_rows'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised per field below.

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$clean = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name     = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$pdf_id   = isset( $row['pdf_id'] ) ? absint( $row['pdf_id'] ) : 0;
			$icon_id  = isset( $row['icon_id'] ) ? absint( $row['icon_id'] ) : 0;
			$icon_key = isset( $row['icon_key'] ) ? sanitize_key( $row['icon_key'] ) : '';
			$sort     = isset( $row['sort'] ) ? absint( $row['sort'] ) : 0;

			// Only keep a known built-in icon key.
			if ( $icon_key && ! Kdna_Flipbook_Assets::is_builtin_icon( $icon_key ) ) {
				$icon_key = '';
			}

			// An uploaded icon takes precedence over a built-in one.
			if ( $icon_id ) {
				$icon_key = '';
			}

			// Drop rows that carry no name and no PDF, they are empty.
			if ( '' === $name && 0 === $pdf_id ) {
				continue;
			}

			$clean[] = array(
				'name'     => $name,
				'pdf_id'   => $pdf_id,
				'icon_id'  => $icon_id,
				'icon_key' => $icon_key,
				'sort'     => $sort,
			);
		}

		// Order by the hidden sort value, then reindex sequentially.
		usort(
			$clean,
			static function ( $a, $b ) {
				return $a['sort'] <=> $b['sort'];
			}
		);

		$ordered = array();
		foreach ( $clean as $position => $row ) {
			$row['sort'] = $position;
			$ordered[]   = $row;
		}

		if ( empty( $ordered ) ) {
			delete_post_meta( $post_id, self::META_ROWS );
		} else {
			update_post_meta( $post_id, self::META_ROWS, $ordered );
		}
	}

	/**
	 * Sanitise and save the access code.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function save_access_code( $post_id ) {
		$code = isset( $_POST['kdna_flipbook_access_code'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_flipbook_access_code'] ) ) : '';

		if ( '' === $code ) {
			delete_post_meta( $post_id, self::META_ACCESS_CODE );
		} else {
			update_post_meta( $post_id, self::META_ACCESS_CODE, $code );
		}
	}

	/**
	 * Enqueue the repeater assets on the client edit screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Kdna_Flipbook_Cpt::get_post_type() !== $screen->post_type ) {
			return;
		}

		// The media uploader powers the PDF and icon pickers.
		wp_enqueue_media();

		wp_enqueue_style(
			'kdna-flipbook-admin-repeater',
			KDNA_FLIPBOOK_URL . 'admin/admin-repeater.css',
			array(),
			KDNA_FLIPBOOK_VERSION
		);

		wp_enqueue_script(
			'kdna-flipbook-admin-repeater',
			KDNA_FLIPBOOK_URL . 'admin/admin-repeater.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			KDNA_FLIPBOOK_VERSION,
			true
		);

		wp_localize_script(
			'kdna-flipbook-admin-repeater',
			'kdnaFlipbookRepeater',
			array(
				'i18n' => array(
					'choosePdf'     => __( 'Choose PDF', 'kdna-flipbook' ),
					'changePdf'     => __( 'Change PDF', 'kdna-flipbook' ),
					'selectPdf'     => __( 'Select a PDF', 'kdna-flipbook' ),
					'usePdf'        => __( 'Use this PDF', 'kdna-flipbook' ),
					'chooseIcon'    => __( 'Choose icon', 'kdna-flipbook' ),
					'changeIcon'    => __( 'Change icon', 'kdna-flipbook' ),
					'selectIcon'    => __( 'Select an icon', 'kdna-flipbook' ),
					'useIcon'       => __( 'Use this icon', 'kdna-flipbook' ),
					'confirmRemove' => __( 'Remove this flipbook?', 'kdna-flipbook' ),
				),
			)
		);
	}
}
