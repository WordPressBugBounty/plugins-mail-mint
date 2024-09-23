<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="message" class="notice notice-success" style="position: relative;">
	<a class="mm-notice-close notice-dismiss" style="
    position: absolute;
    top: 0;
    right: 1px;
    border: none;
    margin: 0;
    padding: 9px;
    background: none;
    color: #787c82;
    cursor: pointer;
    display: flex;
    text-align: center;
    text-decoration: none;
" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mm-hide-notice', 'database_update', remove_query_arg( 'do_update_mm_database' ) ), 'mm_hide_notices_nonce', 'mm_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'mrm' ); ?></a>

	<p><?php esc_html_e( 'Mail Mint database update complete. Thank you for updating to the latest version!', 'mrm' ); ?></p>
</div>
