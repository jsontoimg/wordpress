<?php

/**
 * Admin settings form.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/admin/partials
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

?>
<div class="wrap jsontoimg-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form action="options.php" method="post">
		<?php
		settings_fields( 'jsontoimg' );
		do_settings_sections( Jsontoimg_Settings::PAGE_SLUG );
		submit_button( __( 'Save changes', 'jsontoimg' ) );
		?>
	</form>
	<div class="jsontoimg-test-row">
		<button type="button" class="button" id="jsontoimg-test-connection">
			<?php esc_html_e( 'Test connection', 'jsontoimg' ); ?>
		</button>
		<span id="jsontoimg-test-result" class="jsontoimg-test-result" role="status"></span>
	</div>
</div>
