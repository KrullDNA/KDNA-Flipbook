<?php
/**
 * First-run setup screen for KDNA PDF Flipbook.
 *
 * Shown once straight after activation so Nick can name the custom post type
 * before creating any client pages. The form posts to admin-post.php where the
 * settings class sanitises and saves it.
 *
 * @package Kdna_Flipbook
 *
 * @var array $settings Current plugin settings, passed in by the render callback.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_singular = isset( $settings['cpt_singular'] ) ? $settings['cpt_singular'] : '';
$kdna_plural   = isset( $settings['cpt_plural'] ) ? $settings['cpt_plural'] : '';
$kdna_slug     = isset( $settings['cpt_slug'] ) ? $settings['cpt_slug'] : '';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Welcome to KDNA PDF Flipbook', 'kdna-flipbook' ); ?></h1>

	<p style="max-width: 40em;">
		<?php esc_html_e( 'Before you start, choose what to call the pages that hold your flipbooks. Each entry is one client page, so you might call these Clients, Pitches or Proposals. You can change this later under Settings, KDNA PDF Flipbook.', 'kdna-flipbook' ); ?>
	</p>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="kdna_flipbook_save_setup" />
		<?php wp_nonce_field( 'kdna_flipbook_setup', 'kdna_flipbook_setup_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cpt_singular"><?php esc_html_e( 'Singular label', 'kdna-flipbook' ); ?></label>
					</th>
					<td>
						<input name="cpt_singular" type="text" id="cpt_singular" value="<?php echo esc_attr( $kdna_singular ); ?>" placeholder="<?php esc_attr_e( 'Client', 'kdna-flipbook' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'The singular name, for example Client.', 'kdna-flipbook' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cpt_plural"><?php esc_html_e( 'Plural label', 'kdna-flipbook' ); ?></label>
					</th>
					<td>
						<input name="cpt_plural" type="text" id="cpt_plural" value="<?php echo esc_attr( $kdna_plural ); ?>" placeholder="<?php esc_attr_e( 'Clients', 'kdna-flipbook' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'The plural name shown in the admin menu, for example Clients.', 'kdna-flipbook' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cpt_slug"><?php esc_html_e( 'Slug', 'kdna-flipbook' ); ?></label>
					</th>
					<td>
						<input name="cpt_slug" type="text" id="cpt_slug" value="<?php echo esc_attr( $kdna_slug ); ?>" placeholder="kdna-client" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Used in the URL and the post type key. Lowercase letters, numbers and hyphens, up to 20 characters.', 'kdna-flipbook' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save and continue', 'kdna-flipbook' ) ); ?>
	</form>
</div>
