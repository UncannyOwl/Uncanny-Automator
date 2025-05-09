<?php
/**
 * This file initiate the various rest endpoints.
 *
 * @since 4.12
 */
namespace Uncanny_Automator\Rest\Log_Endpoint;

use Exception;
use WP_REST_Server;
use WP_REST_Request;
use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Resolver\Fields_Conditions_Resolver;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Automator_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Logs_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Action_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Loop_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Recipe_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Trigger_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Helpers\Conditions_Helper;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Loop_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Recipe_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Trigger_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;
use Uncanny_Automator\Rest\Endpoint\User_Endpoint;
use Uncanny_Automator\Services\Email;
use Uncanny_Automator\Services\Addons\Data\License_Summary;
use Uncanny_Automator\Services\Addons\Lists\Plan_List;
use Uncanny_Automator\Services\Addons\Plugins\Manager as Addons_Plugin_Manager;
use WP_HTTP_Response;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Attach the service on 'rest_api_init'.
 *
 * @since 4.12
 */
function rest_api_init( WP_REST_Server $wp_rest_server ) {

	require_once UA_ABSPATH . 'src/core/services/rest/auth/auth.php';

	// Overwride with a function that returns true to disable nonce check. This is done during development mode.
	$authentication = apply_filters(
		'automator_rest_authentication_service',
		array( \Uncanny_Automator\Rest\Auth\Auth::class, 'verify_permission' )
	);

	/**
	 * Registers the email endpoint for testing.
	 *
	 * #[Uncanny_Automator_Route('wp-json/automator/v1/user/:user_id')]
	 */
	register_rest_route(
		'automator/v1',
		'/email/test',
		array(
			'methods'             => 'POST',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {

				try {

					$email_sender = new Email\Tester( Email\Tester::generate_args( $request ) );

					if ( false === $email_sender->send() ) {
						throw new Exception( 'The system encountered an error while attempting to send the email. Ensure that your email server settings, such as SMTP configuration, are accurate.', 400 );
					}
				} catch ( Exception $e ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'error'   => $e->getMessage(),
						),
						400
					);
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'error'   => '',
					),
					200
				);
			},
		)
	);

	/**
	 * Registers the user endpoint.
	 *
	 * #[Uncanny_Automator_Route('wp-json/automator/v1/user/:user_id')]
	 */
	register_rest_route(
		'automator/v1',
		'/user/(?P<id>\d+)',
		array(
			// The permission callback.
			'methods'             => 'GET',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {
				// And instantiate when needed.
				return apply_filters(
					'automator_rest_routes_user_response',
					( new User_Endpoint() )->find_by_id( $request )
				);
			},
		)
	);

	/**
	 * Registers the log endpoint.
	 *
	 * #[Uncanny_Automator_Route('/wp-json/automator/v1/log')]
	 */
	register_rest_route(
		'automator/v1',
		'/log/recipe_id/(?P<recipe_id>\d+)/run_number/(?P<run_number>\d+)/recipe_log_id/(?P<recipe_log_id>\d+)',
		array(
			// The permission callback.
			'methods'             => 'GET',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {

				// Disable errors so JS won't break.
				if ( ! apply_filters( 'automator_rest_routes_log_display_notices_warnings', false ) ) {
					ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet
				}

				global $wpdb;
				// @todo Use DIC to simplify and autowire the following class compositions/dependencies.
				$utils = new Formatters_Utils();

				// Automator factory is the container for our Automator() functions and Automator_Status.
				$automator_factory = new Automator_Factory(
					Automator_Functions::get_instance(),
					new Automator_Status()
				);

				// These logs queries are dependencies for building our resources later.
				$recipe_logs_queries  = new Recipe_Logs_Queries( $wpdb );
				$trigger_logs_queries = new Trigger_Logs_Queries( $wpdb );
				$action_logs_queries  = new Action_Logs_Queries( $wpdb );
				$loop_logs_queries    = new Loop_Logs_Queries( $wpdb );

				// Logs resources are logic and mapping class that gets their data from *_Queries class.
				$recipe_logs_resources  = new Recipe_Logs_Resources( $recipe_logs_queries, $utils, $automator_factory );
				$trigger_logs_resources = new Trigger_Logs_Resources( $trigger_logs_queries, $utils, $automator_factory );
				$loops_logs_resources = new Loop_Logs_Resources( $loop_logs_queries, $utils, $automator_factory );
				$action_logs_resources  = new Action_Logs_Resources( $action_logs_queries, $utils, $automator_factory, $loops_logs_resources );

				// Require the class because its not part of the autoloaded directory.
				$fcr = new Fields_Conditions_Resolver();
				$action_logs_resources->set_field_conditions_resolver( $fcr );

				// Set the condition helper.
				$conditions = new Conditions_Helper();
				$action_logs_resources->set_conditions( $conditions );

				// Logs Factory is a class for retrieving various logs objects.
				$logs_factory = new Logs_Factory( $recipe_logs_resources, $trigger_logs_resources, $action_logs_resources, $loops_logs_resources );

				// The main endpoint controller.
				$log_endpoint = new Log_Endpoint( $automator_factory, $logs_factory );

				$log_endpoint->set_utils( $utils );

				// The wiring of the objects above can be simplified with DiC.
				$response = new WP_HTTP_Response( $log_endpoint->get_log( $request ), 200 );

				// If as_attachment query parameter is set, download as json file.
				if ( 'yes' === $request->get_param( 'as_attachment' ) ) {
					$file = sprintf(
						'attachment; filename=log-%d-%d-%d-%d.json',
						absint( $request->get_param( 'recipe_id' ) ),
						absint( $request->get_param( 'run_number' ) ),
						absint( $request->get_param( 'recipe_log_id' ) ),
						time()
					);
					$response->header( 'Content-disposition', $file, true );
				}

				return $response;
			},
		)
	);

	/**
	 * Registers the license summary endpoint.
	 *
	 * #[Uncanny_Automator_Route('/wp-json/automator/v1/license/summary')]
	 */
	register_rest_route(
		'automator/v1',
		'/license/summary/',
		array(
			// The permission callback.
			'methods'             => 'GET',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {
				$summary = ( new License_Summary() )->get_license_summary();
				return new WP_HTTP_Response(
					array(
						'success' => true,
						'data'    => $summary,
					),
					200
				);
			},
		)
	);

	/**
	 * Registers the Addons list endpoint.
	 *
	 * #[Uncanny_Automator_Route('/wp-json/automator/v1/addons/get-list/:list_id')]
	 */
	register_rest_route(
		'automator/v1',
		'/addons/get-list/(?P<list_code>\w+)',
		array(
			// The permission callback.
			'methods'             => 'GET',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {
				// Get the list code from the request.
				$list_code = $request->get_param( 'list_code' ); // plus || elite
				$addons    = ( new Plan_List() )->get_list( $list_code );
				if ( is_wp_error( $addons ) ) {
					return new WP_HTTP_Response(
						array(
							'success' => false,
							'error'   => $addons->get_error_message(),
						),
						400
					);
				}

				return new WP_HTTP_Response(
					array(
						'success' => true,
						'data'    => $addons,
					),
					200
				);
			},
		)
	);

	/**
	 * Registers the Addons Plugin Manager endpoint.
	 *
	 * #[Uncanny_Automator_Route('/wp-json/automator/v1/addons/plugin-manager/(?P<action>\w+)/(?P<addon_id>\d+)')]
	 */
	register_rest_route(
		'automator/v1',
		'/addons/plugin-manager/(?P<action>\w+)/(?P<addon_id>\d+)',
		array(
			'methods'             => 'POST',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {
				$manager  = new Addons_Plugin_Manager();
				$response = $manager->handle_rest_request( $request );
				return $response;
			},
		)
	);
}
