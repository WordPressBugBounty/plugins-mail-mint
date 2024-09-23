<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$update_url = wp_nonce_url(
	add_query_arg( 'do_update_mm_database', 'true', admin_url() ),
	'mm_db_update',
	'mm_db_update_nonce'
);

?>
<div id="message" class="notice notice-success">
	<p>
		<strong><?php esc_html_e( 'Mail Mint database update required', 'mrm' ); ?></strong>
	</p>
	<p>
		<?php
		esc_html_e( "We're delighted to announce the latest update for Mail Mint! In order to ensure a seamlessly optimized experience, we need to bring your database up to the latest version.", 'mrm' );

		printf( ' ' . esc_html__( 'The database update process runs in the background and may take a little while, so please be patient.', 'mrm' ));
		?>
	</p>
	<p class="submit">
		<a href="<?php echo esc_url( $update_url ); ?>" class="mm-update-now button-primary">
			<?php esc_html_e( 'Update Mail Mint Database', 'mrm' ); ?>
		</a>
	</p>
</div>
