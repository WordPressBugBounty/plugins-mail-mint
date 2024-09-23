<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/API/Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\ComplianceSettingController;
use Mint\MRM\Admin\API\Controllers\GeneralSettingController;
use Mint\MRM\Admin\API\Controllers\WCSettingController;
use Mint\MRM\Admin\API\Controllers\BusinessBasicSettingController;
use Mint\MRM\Admin\API\Controllers\BusinessSocialSettingController;
use Mint\MRM\Admin\API\Controllers\EmailSettingController;
use Mint\MRM\Admin\API\Controllers\OptinSettingController;
use Mint\MRM\Admin\API\Controllers\reCaptchaSettingController;
use Mint\MRM\Admin\API\Controllers\SMTPSettingController;

/**
 * [Manage double opt-in settings API routes]
 *
 * @desc Manage double opt-in settings API routes
 * @package /app/API/Routes
 * @since 1.0.0
 */
class SettingRoute {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $namespace = 'mrm/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $rest_base = 'settings';

	/**
	 * WCSettingController class instance variable
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $wc_controller;

	/**
	 * OptinSettingController class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $optin_controller;

	/**
	 * BusinessBasicSettingController class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $business_basic_controller;

	/**
	 * BusinessSocialSettingController class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $business_social_controller;

	/**
	 * EmailSettingController class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $email_controller;

	/**
	 * GeneralSettingController class instance variable
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $general_controller;

	/**
	 * SMTPSettingController class instance variable
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $smtp_controller;

	/**
	 * SMTPSettingController class instance variable
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $compliance_controller;

	/**
	 * SMTPSettingController class instance variable
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $openai_controller;

	/**
	 * reCaptchaSettingController class instance variable
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $recaptcha_controller;


	/**
	 * Register API endpoints routes for tags module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		// WCSettingController class instance.
		$this->wc_controller = new WCSettingController();

		// API routes for WooCommerce settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/wc/',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->wc_controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->wc_controller,
						'rest_permissions_check',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->wc_controller,
						'get',
					),
					'permission_callback' => array(
						$this->wc_controller,
						'rest_permissions_check',
					),
				),
			)
		);

		$this->email_controller = EmailSettingController::get_instance();
		/**
		 * Settings email endpoints
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/email',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->email_controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->email_controller,
						'rest_permissions_check',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->email_controller,
						'get',
					),
					'permission_callback' => array(
						$this->email_controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Register rest routes for double opt-in settings
		 *
		 * @since 1.0.0
		 */
		$this->optin_controller = OptinSettingController::get_instance();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/optin',
			array(

				// POST request for store on wp_options table.
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->optin_controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->optin_controller,
						'rest_permissions_check',
					),
				),

				// GET request for retrieving double opt-in settings.
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->optin_controller,
						'get',
					),
					'permission_callback' => array(
						$this->optin_controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Business basic settings controller
		 */
		$this->business_basic_controller = BusinessBasicSettingController::get_instance();
		/**
		 * Register rest routes for double Basic settings in business module.
		 *
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/business/basic',
			array(

				// POST request for store on wp_options table.
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->business_basic_controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->business_basic_controller,
						'rest_permissions_check',
					),
				),

				// GET request for retrieving Business settings.
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->business_basic_controller,
						'get',
					),
					'permission_callback' => array(
						$this->business_basic_controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Business social settings controller
		 */
		$this->business_social_controller = BusinessSocialSettingController::get_instance();
		/**
		 * Register rest routes for double Basic settings in business module.
		 *
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/business/social',
			array(

				// POST request for store on wp_options table.
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->business_social_controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->business_social_controller,
						'rest_permissions_check',
					),
				),

				// GET request for retrieving Business settings.
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->business_social_controller,
						'get',
					),
					'permission_callback' => array(
						$this->business_social_controller,
						'rest_permissions_check',
					),
				),
			)
		);

		// GeneralSettingController class instance.
		$this->general_controller = GeneralSettingController::get_instance();

		// API routes for General settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/general(?:/(?P<general_settings_key>[a-z-|_]+))?',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->general_controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->general_controller,
						'rest_permissions_check',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->general_controller,
						'get',
					),
					'permission_callback' => array(
						$this->general_controller,
						'rest_permissions_check',
					),
				),
			)
		);


        // compliance Setting
        $this->compliance_controller = ComplianceSettingController::get_instance();

        // API routes for compliance settings.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/gdpr-compliance',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array(
                        $this->compliance_controller,
                        'create_or_update',
                    ),
                    'permission_callback' => array(
                        $this->compliance_controller,
                        'rest_permissions_check',
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array(
                        $this->compliance_controller,
                        'get',
                    ),
                    'permission_callback' => array(
                        $this->compliance_controller,
                        'rest_permissions_check',
                    ),
                ),
            )
        );

		/**
		 * Register rest routes for recaptcha settings
		 *
		 * @since 1.0.0
		 */
		$this->recaptcha_controller = reCaptchaSettingController::get_instance();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/recaptcha',
			array(

				// POST request for store on wp_options table.
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->recaptcha_controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->recaptcha_controller,
						'rest_permissions_check',
					),
				),

				// GET request for retrieving double opt-in settings.
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->recaptcha_controller,
						'get',
					),
					'permission_callback' => array(
						$this->recaptcha_controller,
						'rest_permissions_check',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/create-contact',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->email_controller,
						'handle_contact_creation',
					),
					'permission_callback' => array(
						$this->email_controller,
						'rest_permissions_check',
					),
				)
			)
		);

	}
}
