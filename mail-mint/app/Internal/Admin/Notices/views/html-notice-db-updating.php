<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pending_actions_url = admin_url( 'tools.php?page=action-scheduler&tab=action-scheduler&s=mail_mint_run_update_callback&status=pending' );
?>
<div id="message" class="notice notice-success">
	<p>
		<strong><?php esc_html_e( 'Mail Mint database update', 'mrm' ); ?></strong><br>
		<?php esc_html_e( 'Mail Mint is updating the database in the background. The database update process may take a little while, so please be patient.', 'mrm' ); ?>
		&nbsp;<a href="<?php echo esc_url( $pending_actions_url ); ?>"><?php echo __('View Progress', 'mrm') ?></a>
	</p>
</div>
