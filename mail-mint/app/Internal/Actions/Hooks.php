<?php
/**
 * Mail Mint WP actions and callback functions
 *
 * This class handles callback functions for each WP Actions.
 *
 * @author   Mail Mint Team
 * @category Action
 * @package  MRM
 * @since    1.0.0
 */

namespace MailMint\App\Actions;

use Mint\MRM\DataBase\Models\EmailModel;
use MailMint\App\Helper;
use Mint\MRM\DataBase\Models\ContactModel;
use MRM\Common\MrmCommon;

/**
 * Hooks class.
 */
class Hooks {

	/**
	 * Hook into WordPress ready to init.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_loaded', array( $this, 'handle_redirection_from_url' ) );
		add_action( 'woocommerce_new_order', array( $this, 'track_buying_product_through_link' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'track_revenue_on_order' ), 10, 3 );
		add_action( 'add_meta_boxes', array( $this, 'add_custom_meta_box' ) );
		add_action( 'edd_view_order_details_sidebar_before', array( $this, 'add_contact_details_for_edd' ) );
		add_filter( 'mail_mint_free_active', array( $this, 'mail_mint_free_active' ) );
		add_action( 'upgrader_process_complete', array( $this, 'remove_cache_from_mailmint' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'mailmint_plugin_row_meta' ), 10, 2 );
		add_action( 'admin_footer', array( $this, 'remove_jetpack_note_from_mail_mint' ) );
		add_action( 'init', array( $this, 'clear_litespeed_cache' ) );
		add_action( 'woocommerce_new_order', array( $this, 'insert_mint_woocommerce_customer_data' ), 10, 2 );
		add_action('action_scheduler_failed_action', array( $this, 'handle_failed_action' ), 10, 1);
		add_action('action_scheduler_failed_execution', array( $this, 'handle_failed_action' ), 10, 2);
		add_action('init', array($this, 'handle_email_open_tracking'));
		add_filter( 'mint_merge_tag_fallback', array( $this, 'mint_merge_tag_fallback' ), 10, 2 );
		add_action('woocommerce_order_status_changed', array($this, 'handlePaymentStatusChanged'), 100, 4);
	}

	public function mint_merge_tag_fallback( $value_key, $contact ) {
		switch ($value_key) {
			case '{{first_name}}':
				return isset($contact['first_name']) ? $contact['first_name'] : '';
			case '{{last_name}}':
				return isset($contact['last_name']) ? $contact['last_name'] : '';
			case '{{email}}':
				return isset($contact['email']) ? $contact['email'] : '';
			case '{{city}}':
				return isset($contact['city']) ? $contact['city'] : '';
			case '{{state}}':
				return isset($contact['state']) ? $contact['state'] : '';
			case '{{country}}':
				return isset($contact['country']) ? $contact['country'] : '';
			case '{{company}}':
				return isset($contact['company']) ? $contact['company'] : '';
			case '{{state}}':
				return isset($contact['designation']) ? $contact['designation'] : '';
			default:
				return $value_key;
		}
	}

	/**
	 * Handles email open tracking.
	 * 
	 * This function checks if the 'mint' and 'route' query parameters are set in the URL.
	 * If the 'route' parameter is set to 'open', it extracts the 'hash' parameter from the URL
	 * and retrieves the email ID associated with the hash. It then updates the email meta data
	 * to indicate that the email has been opened.
	 *
	 * @return void
	 * @since 1.14.1
	 */
	public function handle_email_open_tracking(){
		$sanitize_server = MrmCommon::get_sanitized_get_post();
		$get = isset($sanitize_server['get']) ? $sanitize_server['get'] : array();
		if (isset($get['mint']) && isset($get['route']) && 'open' === $get['route']) {
			$hash     = ! empty($get['hash']) ? $get['hash'] : '';
			$email_id = EmailModel::get_broadcast_email_by_hash($hash);

			if ($hash && $email_id) {
				EmailModel::insert_or_update_email_meta('is_open', 1, $email_id);
				EmailModel::insert_or_update_email_meta('user_open_agent', Helper::get_user_agent(), $email_id);
				$is_ip_store                = get_option('_mint_compliance');
				$is_ip_store                = ! empty($is_ip_store['anonymize_ip']) ? $is_ip_store['anonymize_ip'] : 'no';
				if ('no' === $is_ip_store) {
					EmailModel::insert_or_update_email_meta('user_open_ip', Helper::get_user_ip(), $email_id);
				}

				/*
				 * Fires after an email is opened.
				 *
				 * @param int $email_id The ID of the email that was opened.
				 * @since 1.14.1
				 */
				do_action('mailmint_after_email_open', $email_id);
			}

			MrmCommon::generate_gif();
		}
	}

	/**
	 * Handles a failed action by rescheduling it if certain conditions are met.
	 *
	 * This function checks if the ActionScheduler class exists and if the specified
	 * action hook is 'mailmint_send_scheduled_emails'. It extracts the properties of 
	 * the action and reschedules it if it is not already scheduled.
	 *
	 * @param int $action_id The ID of the action to handle.
	 *
	 * @return void
	 * @since 1.14.6
	 */
	public function handle_failed_action( $action_id ){
		if (class_exists('ActionScheduler')) {
			$store = \ActionScheduler::store();
			$action = $store->fetch_action($action_id);

			$args = array();
			$group = '';
			if ('mailmint_send_scheduled_emails' === $action->get_hook()) {
				// Extract properties.
				$args  = $action->get_args();
				$group = $action->get_group();
			}

			// Check if the action is scheduled and reschedule if not.
			if (defined('MAILMINT_SEND_SCHEDULED_EMAILS') && !as_has_scheduled_action(MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group)) {
				as_schedule_single_action(time() + 120, MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group);
			}
		}
	}

	/**
	 * Adds custom row metadata for MailMint plugin.
	 *
	 * @param array  $links Array of existing row metadata links.
	 * @param string $file  The plugin file path.
	 *
	 * @return array Modified array of row metadata links.
	 *
	 * @since 1.0.0
	 */
	public function mailmint_plugin_row_meta( $links, $file ) {
		if ( 'mail-mint/mail-mint.php' === $file ) {
			$row_meta = array(
				'docs'           => '<a rel="noopener" href="https://getwpfunnels.com/docs/mail-mint/?utm_source=plugins-page-to-mm-doc-CTA&utm_medium=wp-plugins-page&utm_campaign=plugins-page-to-mm-doc&utm_id=plugins-page-to-mm-doc" style="color: #23c507;font-weight: 600;" aria-label="' . esc_attr( esc_html__( 'Documentation', 'mrm' ) ) . '" target="_blank">' . esc_html__( 'Docs & FAQs', 'mrm' ) . '</a>',
				'developer_docs' => '<a rel="noopener" href="https://developers.getwpfunnels.com/?utm_source=plugins-page-to-mm-dev-doc-CTA&utm_medium=wp-plugins-page&utm_campaign=plugins-page-to-mm-dev-doc&utm_id=plugins-page-to-mm-dev-doc" style="color: #23c507;font-weight: 600;" aria-label="' . esc_attr( esc_html__( 'Developer Docs', 'mrm' ) ) . '" target="_blank">' . esc_html__( 'Developer Docs', 'mrm' ) . '</a>',
			);
			return array_merge( $links, $row_meta );
		}
		return (array) $links;
	}


	/**
	 * Remove cache related to the MailMint plugin after it has been updated.
	 *
	 * @param object $upgrader_object The upgrader object responsible for the plugin update.
	 * @param array  $hook_extra      Additional information about the hook/action being performed.
	 *
	 * @return void
	 *
	 * @see rocket_clean_files()  For cleaning files cache using the WP Rocket plugin.
	 *                           Reference: https://docs.wp-rocket.me/article/91-rocketcleanfiles
	 * @see \LiteSpeed\Purge::purge_all()  For purging LiteSpeed Cache programmatically.
	 *                                     Reference: https://itchycode.com/purge-wordpress-litespeed-cache-programmatically-with-wordpress-schedule-event
	 */
	public function remove_cache_from_mailmint( $upgrader_object, $hook_extra ) {
		$action = ! empty( $hook_extra['action'] ) ? $hook_extra['action'] : '';
		$type   = ! empty( $hook_extra['type'] ) ? $hook_extra['type'] : '';

		if ( 'update' !== $action || 'plugin' !== $type || empty( $upgrader_object->result ) || is_wp_error( $upgrader_object->result ) ) {
			return;
		}

		$current_plugin_path_name = 'mail-mint/mail-mint.php';

		if ( ! empty( $hook_extra['plugins'] ) && in_array( $current_plugin_path_name, $hook_extra['plugins'], true ) ) {
			if ( function_exists( 'rocket_clean_files' ) ) {
				// Clean the files cache using the WP Rocket plugin.
				$file = admin_url( 'admin.php?page=mrm-admin' );
				rocket_clean_files( $file );
			}

			if ( class_exists( '\LiteSpeed\Purge' ) ) {
				// Purge LiteSpeed Cache programmatically.
				\LiteSpeed\Purge::purge_all();
			}
		}
	}

	/**
	 * Clear LiteSpeed Cache.
	 *
	 * @since 1.13.1
	 */
	public function clear_litespeed_cache() {
		if (strpos($_SERVER['REQUEST_URI'], 'mrm') !== FALSE) {
            if ( class_exists( '\LiteSpeed\Purge' ) ) {
				// Purge LiteSpeed Cache programmatically.
				do_action( 'litespeed_control_set_nocache', 'no cache for Mail Mint' );
			}
        }
	}

	/**
	 * Handles redirection based on URL parameters.
	 *
	 * This function retrieves sanitized GET and SERVER parameters, checks if the 'action' parameter
	 * is set to 'mint_action', and if so, calls the redirection handler to perform the redirection.
	 *
	 * @return void
	 * @since 1.14.0
	 */
	public function handle_redirection_from_url() {
		$get_server = MrmCommon::get_sanitized_get_post();
		$get        = isset( $get_server[ 'get' ] ) ? $get_server[ 'get' ] : array();
		$server     = isset( $get_server[ 'server' ] ) ? $get_server[ 'server' ] : array();
		if ( isset( $get['action'] ) && 'mint_action' === $get['action'] ) {
			MM()->redirection_handler->redirect( $get, $server );
		}
	}


	/**
	 * Callback function for woocommerce_new_order hooks
	 *
	 * @param mixed $order_id WooCommerce order ID.
	 * @param mixed $order WooCommerce order object.
	 */
	public function track_buying_product_through_link( $order_id, $order ) {
		$cookie = MrmCommon::get_sanitized_get_post();
		$cookie = !empty( $cookie[ 'cookie' ] ) ? $cookie[ 'cookie' ] : array();
		if ( isset( $cookie['mail_mint_link_trigger'] ) ) {
			// Get mail mint email id from hash.
			$hash     = !empty( $cookie['mail_mint_link_trigger'] ) ? $cookie['mail_mint_link_trigger'] : '';
			$email_id = EmailModel::get_broadcast_email_by_hash( $hash );
			// Insert email meta table to track order.
			EmailModel::insert_email_meta( 'order_id', $order_id, $email_id );

			// Delete on unset cookie.
			setcookie( 'mail_mint_link_trigger', '', time() -3600 );
			unset( $cookie['mail_mint_link_trigger'] );
		}
	}

	/**
	 * Adds a custom meta box based on the context.
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function add_custom_meta_box() {
		// Load the global $post.
		global $post;

		if ( isset( $post->ID ) ) {
			/**
			 * Retrieves the order ID from the global $post.
			 *
			 * @var int $order_id The order ID.
			 */
			$order_id = $post->ID;
			$this->add_custom_meta_box_for_regular( $order_id );
		} else {
			$this->add_custom_meta_box_for_hpos();
		}
	}

	/**
	 * Adds a custom meta box for regular orders.
	 *
	 * @param int $order_id The order ID.
	 * @return void
	 *
	 * @since 2.0.0
	 */
	public function add_custom_meta_box_for_regular( $order_id ) {
		$this->add_custom_meta_box_for_wc( $order_id, 'shop_order', 'add_contact_details_for_regular_wc' );
	}


	/**
	 * Adds a custom meta box for HPOS.
	 *
	 * @return void
	 *
	 * @since 2.0.0
	 */
	public function add_custom_meta_box_for_hpos() {
		/**
		 * Retrieves the sanitized GET and POST data.
		 *
		 * @var array $get_post The sanitized GET and POST data.
		 */
		$get_post = MrmCommon::get_sanitized_get_post();

		/**
		 * Retrieves the page ID from the sanitized GET and POST data.
		 *
		 * @var string $page_id The page ID.
		 */
		$page_id = isset( $get_post['get']['page'] ) ? $get_post['get']['page'] : '';

		if ( isset( $get_post['get']['id'], $get_post['get']['page'], $get_post['get']['action'] ) && 'wc-orders' === $page_id ) {
			/**
			 * Retrieves the order ID from the sanitized GET data.
			 *
			 * @var int $order_id The order ID.
			 */
			$order_id = isset( $get_post['get']['id'] ) ? $get_post['get']['id'] : 0;

			$this->add_custom_meta_box_for_wc( $order_id, 'woocommerce_page_wc-orders', 'add_contact_details_for_hpos_wc' );
		}
	}

	/**
	 * Adds a custom meta box for WooCommerce orders.
	 *
	 * @param int    $order_id   The order ID.
	 * @param string $screen_id  The screen ID where the meta box should be displayed.
	 * @param string $callback   The callback function to render the meta box content.
	 * @return void
	 * @since 2.0.0
	 */
	public function add_custom_meta_box_for_wc( $order_id, $screen_id, $callback ) {
		if ( $order_id ) {
			/**
			 * Retrieves the email associated with the WooCommerce order.
			 *
			 * @var string $email The email address.
			 */
			$email = MrmCommon::get_email_from_wc_order( $order_id );

			/**
			 * Checks the contact availability on Mail Mint.
			 *
			 * @var bool $is_exist Whether the contact exists.
			 */
			$is_exist = ContactModel::is_contact_exist( $email );

			if ( $is_exist ) {
				/**
				 * Adds a meta box for the contact details.
				 *
				 * @see $callback
				 */
				add_meta_box( 'contact_details_meta_box', esc_html__( 'Contact Profile', 'mrm' ), array( $this, $callback ), $screen_id, 'side', 'core' );
			}
		}
	}



	/**
	 * Callback function for woocommerce_order_status_changed hooks
	 *
	 * @param mixed $order_id WooCommerce order ID.
	 * @param mixed $old_status WooCommerce order previous status.
	 * @param mixed $new_status WooCommerce order updated status.
	 * @return void
	 * @since 1.0.0
	 */
	public function track_revenue_on_order( $order_id, $old_status, $new_status ) {
		if ( 'completed' === $new_status ) {
			// Get order object via order_id.
			$order    = wc_get_order( $order_id );
			$meta_arr = EmailModel::get_broadcast_email_meta( 'order_id', $order_id );

			if ( $meta_arr ) {
				$email_id = isset( $meta_arr['mint_email_id'] ) ? $meta_arr['mint_email_id'] : '';
				// Insert email meta table to track order.
				EmailModel::insert_email_meta( 'order_total', $order->get_total(), $email_id );
			}
		}
	}

	/**
	 * Extract contact information from order id and render contact details markups
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function add_contact_details_for_regular_wc() {
		// Load the global $post.
		global $post;

		// Get the post ID and customer email.
		$order_id = ! empty( $post->ID ) ? $post->ID : 0;
		if ( $order_id ) {
			$contact = $this->get_contact_information( $order_id );
			if ( $contact ) {
				$this->render_mail_mint_contact_details( $contact );
			}
		}
	}

	/**
	 * Adds the contact details meta box for HPOS WooCommerce orders.
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function add_contact_details_for_hpos_wc() {
		/**
		 * Retrieves the sanitized GET and POST data.
		 *
		 * @var array $get_post The sanitized GET and POST data.
		 */
		$get_post = MrmCommon::get_sanitized_get_post();

		/**
		 * Retrieves the page ID from the sanitized GET and POST data.
		 *
		 * @var string $page_id The page ID.
		 */
		$page_id = isset( $get_post['get']['page'] ) ? $get_post['get']['page'] : '';

		if ( isset( $get_post['get']['id'], $get_post['get']['page'], $get_post['get']['action'] ) && 'wc-orders' === $page_id ) {
			/**
			 * Retrieves the order ID from the sanitized GET data.
			 *
			 * @var int $order_id The order ID.
			 */
			$order_id = !empty( $get_post['get']['id'] ) ? $get_post['get']['id'] : 0;

			if ( $order_id ) {
				$contact = $this->get_contact_information( $order_id );

				if ( $contact ) {
					$this->render_mail_mint_contact_details( $contact );
				}
			}
		}
	}

	/**
	 * Get contact information based on order ID.
	 *
	 * @param int $order_id The ID of the order.
	 * @return array|null An array containing the contact information, or null if not found.
	 * @since 2.0.0
	 */
	public function get_contact_information( $order_id ) {
		$contact = array();
		if ( $order_id ) {
			$email = $order_id ? MrmCommon::get_email_from_wc_order( $order_id ) : null;
			if ( $email ) {
				$contact = $email ? ContactModel::get_instance()->contact_information_to_shop_order( $email, 'wc' ) : array();
			}
		}
		return $contact;
	}



	/**
	 * Free Plugin active.
	 *
	 * @return true
	 */
	public function mail_mint_free_active() {
		return true;
	}

	/**
	 * Add contact details in EDD order details page
	 *
	 * @param string|int $order_id EDD order id.
	 *
	 * @return void
	 */
	public function add_contact_details_for_edd( $order_id ) {
		if ( !$order_id ) {
			return;
		}

		$order = edd_get_order( $order_id );
		$email = 'EDD\Orders\Order' === get_class( $order ) && !empty( $order->email ) ? $order->email : null;

		if ( ContactModel::is_contact_exist( $email ) ) {
			$contact = $email ? ContactModel::get_instance()->contact_information_to_shop_order( $email, 'edd' ) : null;

			if ( $contact ) {
				?>
				<div class="postbox">
					<h2 class="hndle">
						<span><?php esc_html_e( 'Contact Profile', 'mrm' ); ?></span>
					</h2>

					<div class="inside">
						<div class="edd-admin-box">
							<?php $this->render_mail_mint_contact_details( $contact ); ?>
						</div>
					</div>
				</div>
				<?php
			}
		}
	}

	/**
	 * Adding Meta field in the meta container admin shop_order pages
	 *
	 * @param array $contact Mail Mint contact details.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_mail_mint_contact_details( $contact ) {
		$contact_id  = ! empty( $contact[ 'id' ] ) ? $contact[ 'id' ] : '';
		$tags        = ! empty( $contact[ 'tags' ] ) ? $contact[ 'tags' ] : array();
		$lists       = ! empty( $contact[ 'lists' ] ) ? $contact[ 'lists' ] : array();
		$profile_url = admin_url( '/admin.php?page=mrm-admin#/contacts/' . $contact_id . '/profile' );
		ob_start();
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<style>
			.mintmrm-contact-profile .contact-profile-head {
				background: #F6F8FA;
				padding: 30px 14px;
				margin-top: 11px;
				text-align: center;
			}
			.mintmrm-contact-profile * {
				box-sizing: border-box;
			}
			.mintmrm-contact-profile .contact-profile-photo {
				height: 100px;
				width: 100px;
				border-radius: 100%;
				margin: 0 auto
			}
			.mintmrm-contact-profile .contact-profile-photo img {
				width: 100%;
				height: 100%;
				border-radius: 100%;
			}
			.mintmrm-contact-profile .contact-name {
				font-size: 20px;
				line-height: 1.2;
				font-weight: 600;
				letter-spacing: -0.02em;
				color: #2D3149;
				margin: 14px 0 0 0;
				padding: 0;
				text-transform: capitalize;
			}
			.mintmrm-contact-profile .join-date {
				font-size: 14px;
				font-weight: 500;
				line-height: 1.3;
				color: #2D3149;
				margin: 8px 0 22px 0;
			}
			.mintmrm-contact-profile .view-contact {
				font-weight: 500;
				font-size: 15px;
				line-height: 1.2;
				letter-spacing: -0.01em;
				color: #573BFF;
				text-decoration: none;
				display: inline-block;
				background: #FFFFFF;
				border: 1px solid #573BFF;
				padding: 10px 26px;
				border-radius: 50px;
				transition: all 0.3s ease;
				box-shadow: none;
			}
			.mintmrm-contact-profile .view-contact:hover {
				color: #fff;
				background: #573BFF;
			}

			.mintmrm-contact-profile .contact-profile-body {
				margin-top: 30px;
			}
			.mintmrm-contact-profile .single-info {
				display: flex;
				align-items: center;
				margin-bottom: 16px;
			}
			.mintmrm-contact-profile .single-info:last-child {
				margin-bottom: 0;
			}
			.mintmrm-contact-profile .single-info .info-label {
				font-weight: 400;
				font-size: 14px;
				line-height: 1.2;
				text-transform: capitalize;
				color: #9398A5;
				width: 94px;
				padding-right: 5px;
			}
			.mintmrm-contact-profile .single-info .info-value {
				font-weight: 500;
				font-size: 14px;
				line-height: 1.2;
				color: #2D3149;
				width: calc(100% - 94px);
			}
			.mintmrm-contact-profile .single-info.status .info-value {
				font-weight: 600;
				font-size: 12px;
				line-height: 1.2;
				color: #239654;
				background: rgba(35, 150, 84, 0.1);
				text-transform: capitalize;
				padding: 5px 15px;
				width: auto;
				text-align: center;
				border-radius: 50px;
				display: block;
			}
			.mintmrm-contact-profile .single-info.status .info-value.pending {
				color: #f7900a;
				background: rgba(247, 144, 10, 0.1);
			}
			.mintmrm-contact-profile .single-info.status .info-value.unsubscribed {
				color: #fff;
				background-color: #ec5956;
			}
			.mintmrm-contact-profile .mrm-tags {
				font-weight: 600;
				font-size: 10px;
				line-height: 1.2;
				color: #2D3149;
				background: #e9e9e9;
				text-transform: capitalize;
				padding: 4px 10px;
				width: auto;
				text-align: center;
				border-radius: 50px;
				display: inline-block;
				margin: 1px;
			}
		</style>

		<div class="mintmrm-contact-profile">
			<div class="contact-profile-head">
				<div class="contact-profile-photo">
					<img src=<?php echo esc_url( $contact['avatar_url'] ); ?> alt="Contact Profile photo" />
				</div>
				<h4 class="contact-name"><?php echo esc_html( $contact['first_name'] ); ?> <?php echo esc_html( $contact['last_name'] ); ?></h4>
				<p class="join-date">Joined on <time><?php echo esc_html( $contact['created_at'] ); ?></time> </p>
				<a href=<?php echo esc_url( $profile_url ); ?> target="_blank" class="view-contact">View contact</a>
			</div>

			<div class="contact-profile-body">
				<div class="single-info status">
					<span class="info-label">Status : </span>
					<span class="info-value <?php echo esc_html( $contact['status'] ); ?>">
						<?php echo esc_html( $contact['status'] ); ?>
					</span>
				</div>

				<div class="single-info orders">
					<span class="info-label">Orders : </span>
					<span class="info-value">
						<?php echo esc_html( $contact['total_orders'] ); ?>
					</span>
				</div>

				<div class="single-info total-spend">
					<span class="info-label">Total spend : </span>
					<span class="info-value">
						<?php echo esc_html( $contact['total_spent'] ); ?>
					</span>
				</div>

				<div class="single-info aov">
					<span class="info-label">AOV : </span>
					<span class="info-value">
						<?php echo esc_html( $contact['aov'] ); ?>
					</span>
				</div>

				<div class="single-info tags">
					<span class="info-label">Tags : </span>
					<span class="info-value">
						<?php
						foreach ( $tags as $tag ) {
							echo '<span class="mrm-tags">' . esc_html( $tag->title ) . '</span>';
						}
						?>
					</span>
				</div>

				<div class="single-info lists">
					<span class="info-label">Lists : </span>
					<span class="info-value">
						<?php
						foreach ( $lists as $list ) {
							echo '<span class="mrm-tags">' . esc_html( $list->title ) . '</span>';
						}
						?>
					</span>
				</div>
			</div>
		</div>

		<?php
		echo ob_get_clean();
	}

	/**
	 * Removes the Jetpack note from the Mail Mint top-level page in the admin area.
	 *
	 * This function checks if the current admin screen is the top-level page with the slug 'mrm-admin'.
	 * If it is, it removes the element with the ID 'wp-admin-bar-notes' from the WordPress admin bar.
	 *
	 * @since 1.4.5
	 */
	public function remove_jetpack_note_from_mail_mint() {
		$current = get_current_screen();
		if ( isset( $current->base ) && ( 'toplevel_page_mrm-admin' === $current->base || 'mail-mint_page_mint-mail-automation-editor' === $current->base ) ) {
			?>
			<script>
				document.addEventListener('DOMContentLoaded', () => {
					let notes = document.getElementById('wp-admin-bar-notes');
					if(notes){
						notes.remove()
					}
				});

				if(window.location.href.includes('setup-wizard')){
					document.documentElement.classList.add('mrm-setup-wizard');

					// Select all elements with the 'notice' and 'notice-success' classes
    				const notices = document.querySelectorAll('.notice-success');

					// Loop through each element and apply styles
					notices.forEach(notice => {
						notice.style.display = 'none';
					});
				}
			</script>
			<?php
		}
	}

	/**
	 * Insert or update Mail Mint WooCommerce customer data.
	 * This function is called when a new order is placed in WooCommerce.
	 * 
	 * @param int $order_id The order ID.
	 * @param object $order The WooCommerce order object.
	 * @since 1.14.0
	 */
	public function insert_mint_woocommerce_customer_data( $order_id, $order ) {
		if ( ! empty( $order ) ) {
			$customer = Helper::getDbCustomerFromOrder($order);
			$email_address = $customer->email;
			$order_date    = $order->get_date_created()->format('Y-m-d H:i:s');
			$total_value   = $order->get_total();
			$items         = $order->get_items();

			// Retrieve existing data for the email.
			global $wpdb;
			$mint_wc_table = $wpdb->prefix . 'mint_wc_customers';
			$existing_data = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $mint_wc_table WHERE email_address = %s", $email_address), ARRAY_A );

			// Initialize or update customer data.
			if ( $existing_data ) {
				// Update existing data
				$existing_data['l_order_date'] = max( $existing_data['l_order_date'], $order_date );
				$existing_data['f_order_date'] = min( $existing_data['f_order_date'], $order_date );

				// Only count parent orders for `total_order_count`
				if ( $order->get_parent_id() == 0 ) {
					$existing_data['total_order_count'] += 1;
				}

				$existing_data['total_order_value'] += $total_value;

				$existing_products = json_decode( $existing_data['purchased_products'], true );
				$existing_cats     = json_decode( $existing_data['purchased_products_cats'], true );
				$existing_tags     = json_decode( $existing_data['purchased_products_tags'], true );
				$existing_coupons  = json_decode( $existing_data['used_coupons'], true );

				foreach ( $items as $item ) {
					$product = $item->get_product();
					if ( $product ) {
						$existing_products[] = $product->get_id();
						$product_cats  = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
						$product_tags  = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );
						$existing_cats = array_merge( $existing_cats, $product_cats );
						$existing_tags = array_merge( $existing_tags, $product_tags );
					}
				}

				$existing_coupons = array_merge( $existing_coupons, $order->get_coupon_codes() );

				// Remove duplicates
				$existing_data['purchased_products']      = array_values( array_unique( $existing_products ) );
				$existing_data['purchased_products_cats'] = array_values( array_unique( $existing_cats ) );
				$existing_data['purchased_products_tags'] = array_values( array_unique( $existing_tags ) );
				$existing_data['used_coupons']            = array_values( array_unique( $existing_coupons ) );

				// Calculate AOV (Average Order Value)
				if ( $existing_data['total_order_count'] > 0 ) {
					$existing_data['aov'] = number_format((float) ($existing_data['total_order_value'] / $existing_data['total_order_count']), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
				} else {
					$existing_data['aov'] = number_format((float) (0), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
				}

				// Update the custom table.
				if ($existing_data['total_order_count'] > 0) {
					$wpdb->update(
						$mint_wc_table,
						array(
							'l_order_date'            => $existing_data['l_order_date'],
							'f_order_date'            => $existing_data['f_order_date'],
							'total_order_count'       => $existing_data['total_order_count'],
							'total_order_value'       => $existing_data['total_order_value'],
							'aov'                     => $existing_data['aov'],
							'purchased_products'      => wp_json_encode( $existing_data['purchased_products'] ),
							'purchased_products_cats' => wp_json_encode( $existing_data['purchased_products_cats'] ),
							'purchased_products_tags' => wp_json_encode( $existing_data['purchased_products_tags'] ),
							'used_coupons'            => wp_json_encode( $existing_data['used_coupons'] ),
						),
						array( 'email_address' => $email_address )
					);
				}
			} else {
				// Initialize new data.
				$purchased_products      = array();
				$purchased_products_cats = array();
				$purchased_products_tags = array();
				foreach ( $items as $item ) {
					$product = $item->get_product();
					if ( $product ) {
						$purchased_products[]    = $product->get_id();
						$product_cats            = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
						$product_tags            = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );
						$purchased_products_cats = array_merge( $purchased_products_cats, $product_cats );
						$purchased_products_tags = array_merge( $purchased_products_tags, $product_tags );
					}
				}

				// Collecting used coupons.
				$used_coupons = $order->get_coupon_codes();

				// Calculate AOV (Average Order Value)
				$aov = ($order->get_parent_id() == 0) ? number_format((float) ($total_value), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator()) : number_format((float) (0), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());

				// Insert new data into the custom table.
				if ($order->get_parent_id() == 0) {
					$wpdb->insert(
						$mint_wc_table,
						array(
							'email_address'           => $email_address,
							'l_order_date'            => $order_date,
							'f_order_date'            => $order_date,
							'total_order_count'       => 1,
							'total_order_value'       => $total_value,
							'aov'                     => $aov,
							'purchased_products'      => wp_json_encode(array_values(array_unique($purchased_products))),
							'purchased_products_cats' => wp_json_encode(array_values(array_unique($purchased_products_cats))),
							'purchased_products_tags' => wp_json_encode(array_values(array_unique($purchased_products_tags))),
							'used_coupons'            => wp_json_encode(array_values(array_unique($used_coupons))),
						)
					);
				}
			}
		}
	}

	public function handlePaymentStatusChanged($paymentId, $oldStatus, $newStatus, $order)
	{
		if ($oldStatus == $newStatus) {
			return false;
		}

		$paidStatuses= array('wc-completed', 'wc-processing', 'wc-on-hold');
		if (in_array($oldStatus, $paidStatuses) && in_array($newStatus, $paidStatuses)) {
			return false;
		}

		$requireSync = false;
		if (!in_array($newStatus, $paidStatuses)) {
			// It maybe a refund so we have sync all the orders. Sorry!
			$requireSync = true;
		}

		return $this->syncWooOrder($order, $requireSync);
	}

	private function syncWooOrder($order, $requireSync = false)
	{
		$customer = Helper::getDbCustomerFromOrder($order);
		if (!$customer) {
			return false;
		}
		$email_address = $customer->email;
		$order_date    = $order->get_date_created()->format('Y-m-d H:i:s');
		$total_value   = $order->get_total();
		$items         = $order->get_items();

		// Retrieve existing data for the email.
		global $wpdb;
		$mint_wc_table = $wpdb->prefix . 'mint_wc_customers';
		$existing_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $mint_wc_table WHERE email_address = %s", $email_address), ARRAY_A);

		// Initialize or update customer data.
		if ($existing_data) {
			// Update existing data
			$existing_data['l_order_date'] = max($existing_data['l_order_date'], $order_date);
			$existing_data['f_order_date'] = min($existing_data['f_order_date'], $order_date);

			// Only count parent orders for `total_order_count`
			if ($order->get_parent_id() == 0) {
				$existing_data['total_order_count'] -= 1;
			}

			$existing_data['total_order_value'] -= $total_value;

			$existing_products = json_decode($existing_data['purchased_products'], true);
			$existing_cats     = json_decode($existing_data['purchased_products_cats'], true);
			$existing_tags     = json_decode($existing_data['purchased_products_tags'], true);
			$existing_coupons  = json_decode($existing_data['used_coupons'], true);

			foreach ($items as $item) {
				$product = $item->get_product();
				if ($product) {
					$existing_products[] = $product->get_id();
					$product_cats  = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
					$product_tags  = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'ids'));
					$existing_cats = array_merge($existing_cats, $product_cats);
					$existing_tags = array_merge($existing_tags, $product_tags);
				}
			}

			$existing_coupons = array_merge($existing_coupons, $order->get_coupon_codes());

			// Remove duplicates
			$existing_data['purchased_products']      = array_values(array_unique($existing_products));
			$existing_data['purchased_products_cats'] = array_values(array_unique($existing_cats));
			$existing_data['purchased_products_tags'] = array_values(array_unique($existing_tags));
			$existing_data['used_coupons']            = array_values(array_unique($existing_coupons));

			// Calculate AOV (Average Order Value)
			if ($existing_data['total_order_count'] > 0) {
				$existing_data['aov'] = number_format((float) ($existing_data['total_order_value'] / $existing_data['total_order_count']), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
			} else {
				$existing_data['aov'] = number_format((float) (0), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
			}

			// Update the custom table.
			if ($existing_data['total_order_count'] > 0) {
				$wpdb->update(
					$mint_wc_table,
					array(
						'l_order_date'            => $existing_data['l_order_date'],
						'f_order_date'            => $existing_data['f_order_date'],
						'total_order_count'       => $existing_data['total_order_count'],
						'total_order_value'       => $existing_data['total_order_value'],
						'aov'                     => $existing_data['aov'],
						'purchased_products'      => wp_json_encode($existing_data['purchased_products']),
						'purchased_products_cats' => wp_json_encode($existing_data['purchased_products_cats']),
						'purchased_products_tags' => wp_json_encode($existing_data['purchased_products_tags']),
						'used_coupons'            => wp_json_encode($existing_data['used_coupons']),
					),
					array('email_address' => $email_address)
				);
			}
		} else {
			// Initialize new data.
			$purchased_products      = array();
			$purchased_products_cats = array();
			$purchased_products_tags = array();
			foreach ($items as $item) {
				$product = $item->get_product();
				if ($product) {
					$purchased_products[]    = $product->get_id();
					$product_cats            = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
					$product_tags            = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'ids'));
					$purchased_products_cats = array_merge($purchased_products_cats, $product_cats);
					$purchased_products_tags = array_merge($purchased_products_tags, $product_tags);
				}
			}

			// Collecting used coupons.
			$used_coupons = $order->get_coupon_codes();

			// Calculate AOV (Average Order Value)
			$aov = ($order->get_parent_id() == 0) ? number_format((float) ($total_value), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator()) : number_format((float) (0), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());

			// Insert new data into the custom table.
			if ($order->get_parent_id() == 0) {
				$wpdb->insert(
					$mint_wc_table,
					array(
						'email_address'           => $email_address,
						'l_order_date'            => $order_date,
						'f_order_date'            => $order_date,
						'total_order_count'       => 1,
						'total_order_value'       => $total_value,
						'aov'                     => $aov,
						'purchased_products'      => wp_json_encode(array_values(array_unique($purchased_products))),
						'purchased_products_cats' => wp_json_encode(array_values(array_unique($purchased_products_cats))),
						'purchased_products_tags' => wp_json_encode(array_values(array_unique($purchased_products_tags))),
						'used_coupons'            => wp_json_encode(array_values(array_unique($used_coupons))),
					)
				);
			}
		}

		return true;
	}
}
