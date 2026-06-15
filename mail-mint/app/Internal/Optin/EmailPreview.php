<?php
/**
 * Handles the "View in Browser" public email preview page.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.22.0
 */

namespace Mint\MRM\Internal\Optin;

use MailMint\App\Helper;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\EmailModel;
use Mint\MRM\DataBase\Repositories\CampaignRepository;
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use Mint\MRM\Internal\Parser\Parser;
use MRM\Common\MrmCommon;

/**
 * EmailPreview class.
 *
 * Serves individual broadcast emails as a styled web page when a contact
 * clicks a {{link.view_in_browser}} link.
 *
 * URL format: /?mrm=1&route=email_preview&hash={email_hash}
 *
 * @since 1.22.0
 */
class EmailPreview {

	/**
	 * Constructor.
	 *
	 * @since 1.22.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'handle_email_preview' ), 9999 );
	}

	/**
	 * Detect and handle the email preview route.
	 *
	 * Bails early when the request is not a preview route.
	 * Renders the email as a full HTML page and exits on success.
	 *
	 * @since 1.22.0
	 */
	public function handle_email_preview() {
		$get = MrmCommon::get_sanitized_get_post();
		$get = isset( $get['get'] ) ? $get['get'] : array();

		if ( ! isset( $get['mrm'] ) || ! isset( $get['route'] ) || 'email_preview' !== $get['route'] ) {
			return;
		}

		$hash = isset( $get['hash'] ) ? sanitize_text_field( $get['hash'] ) : '';

		if ( empty( $hash ) ) {
			wp_die( esc_html__( 'Invalid preview link.', 'mrm' ) );
		}

		// Look up broadcast email ID by hash.
		$broadcast_email_id = EmailModel::get_broadcast_email_by_hash( $hash );
		if ( empty( $broadcast_email_id ) ) {
			wp_die( esc_html__( 'This preview link is no longer valid.', 'mrm' ) );
		}

		$broadcast_email_id = (int) $broadcast_email_id;

		// Get full broadcast email row (campaign_id, email_id, contact_id).
		$broadcast_row = EmailModel::get_broadcast_email_row( $broadcast_email_id );
		if ( empty( $broadcast_row ) ) {
			wp_die( esc_html__( 'This preview link is no longer valid.', 'mrm' ) );
		}

		$campaign_id   = (int) ( $broadcast_row['campaign_id'] ?? 0 );
		$automation_id = (int) ( $broadcast_row['automation_id'] ?? 0 );
		$step_id       = $broadcast_row['step_id'] ?? '';
		$email_id      = (int) ( $broadcast_row['email_id'] ?? 0 );
		$email_type    = $broadcast_row['email_type'] ?? '';
		$contact_id    = (int) ( $broadcast_row['contact_id'] ?? 0 );

		$email_body    = '';
		$email_subject = '';

		if ( 'automation' === $email_type && $automation_id && $step_id ) {
			// Automation email: body and subject live in mint_automation_steps.settings JSON.
			global $wpdb;
			$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
			$data                  = $wpdb->get_row( //phpcs:ignore
				$wpdb->prepare( "SELECT `settings` FROM {$automation_step_table} WHERE step_id = %s", $step_id ), //phpcs:ignore
				ARRAY_A
			);
			$settings     = ! empty( $data['settings'] ) ? maybe_unserialize( $data['settings'] ) : array();
			$message_data = ! empty( $settings['message_data'] ) ? maybe_unserialize( $settings['message_data'] ) : array();
			$email_body    = $message_data['body'] ?? '';
			$email_subject = $message_data['subject'] ?? '';
		} elseif ( $campaign_id && $email_id ) {
			// Campaign email: body lives in mint_campaign_email_builder joined to mint_campaign_emails.
			$repository = new CampaignRepository();
			$email_row  = $repository->getEmailAttributes( $campaign_id, $email_id );

			if ( empty( $email_row ) ) {
				wp_die( esc_html__( 'This preview link is no longer valid.', 'mrm' ) );
			}

			$email_body    = $email_row['email_body'] ?? '';
			$email_subject = $email_row['email_subject'] ?? '';
		} else {
			wp_die( esc_html__( 'This preview link is no longer valid.', 'mrm' ) );
		}

		// Load contact and merge meta_fields for merge tag resolution.
		$contact = ContactModel::get( $contact_id );
		if ( ! empty( $contact ) && isset( $contact['meta_fields'] ) && is_array( $contact['meta_fields'] ) ) {
			$contact = array_merge( $contact, $contact['meta_fields'] );
			unset( $contact['meta_fields'] );
		}

		// Parse merge tags.
		$email_body    = Parser::parse( $email_body, $contact );
		$email_subject = Parser::parse( $email_subject, $contact );

		// The user is already viewing in browser — replace the tag with '#'.
		$email_body = str_replace( '{{link.view_in_browser}}', '#', $email_body );

		$data = array(
			'email_body'    => $email_body,
			'email_subject' => $email_subject,
		);

		/**
		 * Filter the data passed to the email browser view template.
		 *
		 * @since 1.22.0
		 *
		 * @param array $data               Template data (email_body, email_subject).
		 * @param int   $broadcast_email_id Broadcast email ID.
		 * @param int   $contact_id         Contact ID.
		 */
		$data = apply_filters( 'mail_mint_email_view_on_browser_data', $data, $broadcast_email_id, $contact_id );

		$this->render( $data );
		exit;
	}

	/**
	 * Include the browser view template.
	 *
	 * @param array $data Template data.
	 * @since 1.22.0
	 */
	private function render( array $data ) {
		include MRM_DIR_PATH . 'app/Views/email_browser_view.php';
	}
}
