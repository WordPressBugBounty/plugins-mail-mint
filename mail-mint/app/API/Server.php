<?php
/**
 * Mail Mint
 *
 * @package Mint\MRM\Admin\API
 * @since 1.0.0
 */

namespace Mint\MRM\Admin\API;

/**
 * Define ABSPATH
 */
defined( 'ABSPATH' ) || exit;

/**
 * Register REST API routes after plugin has been activated
 *
 * @desc Initialize API routes class
 */
class Server {


	/**
	 * REST API routes.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $routes = array();


	/**
	 * Hook into WordPress ready to init the REST API as needed.
	 */
	public function init() {
		// rest api endpoints.
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ), 10 );
	}


	/**
	 * Register REST API after plugin activation
	 *
	 * @since 1.0.0
	 */
	public function rest_api_init() {
		foreach ( $this->get_rest_namespaces() as $key => $namespaces ) {
			foreach ( $namespaces as $namespace => $routes ) {
				foreach ( $routes as $controller_name => $route_class ) {
					$route_class_name                               = '\Mint\\MRM\\' . ucfirst( $key ) . '\\API\\Routes\\' . $route_class;
					$this->routes[ $namespace ][ $controller_name ] = new $route_class_name();
					$this->routes[ $namespace ][ $controller_name ]->register_routes();
				}
			}
		}
	}


	/**
	 * Get API namespaces - new namespaces should be registered here.
	 *
	 * @return array List of Namespaces and controller classes.
	 * @since 1.0.0
	 */
	protected function get_rest_namespaces() {
		$routes = $this->get_routes();
		return array(
			'admin'    => array(
				'mrm/v1' => $routes['admin'],
			),
			'frontend' => array(
				'mint-mail/v1' => $routes['frontend'],
			),
		);
	}

	/**
	 * List of controllers classes.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function get_routes() {
		$admin_routes    = array(
			'lists'           => 'ListRoute',
			'tags'            => 'TagRoute',
			'contacts'        => 'ContactRoute',
			'field-groups'    => 'FieldGroupRoute',
			'campaigns'       => 'CampaignRoute',
			'campaign-emails' => 'CampaignEmailRoute',
			'contact-columns' => 'ContactColumnRoute',
			'forms'           => 'FormRoute',
			'settings'        => 'SettingRoute',
			'reports'         => 'DashboardRoute',
			'automation'      => 'AutomationRoute',
			'automation-log'  => 'AutomationLogRoute',
			'automation-step' => 'AutomationStepRoute',
			'automation-job'  => 'AutomationJobRoute',
			'wp-apis'         => 'WPRoute',
			'email-builder'   => 'EmailBuilderRoute',
			'general-fields'  => 'GeneralFieldRoute',
			'contact-import'  => 'ContactImportRoute',
			'contact-profile' => 'ContactProfileRoute',
			'email-template'  => 'TemplateRoute',
		);
		$frontend_routes = array(
			'form'       => 'FormRoute',
			'preference' => 'PreferenceRoute',
			'cookie'     => 'CookieRoute',
			'bounce'	 => 'BounceHandlerRoute',
		);

		return apply_filters(
			'mrm_rest_api_routes',
			array(
				'admin'    => $admin_routes,
				'frontend' => $frontend_routes,
			)
		);
	}

	/**
	 * Gets featured image
	 *
	 * @param object|array $object Image object.
	 * @param string       $field_name Field name in image object.
	 * @param array|object $request Request.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_rest_featured_image( $object, $field_name, $request ) {
		if ( $object[ 'featured_media' ] ) {
			$img = wp_get_attachment_image_src( $object[ 'featured_media' ], 'app-thumb' );

			return $img[ 0 ];
		}

		return false;
	}
}
