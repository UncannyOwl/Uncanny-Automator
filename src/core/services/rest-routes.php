<?php
/**
 * This file initiate the various rest endpoints.
 *
 * @since 4.12
 */
namespace Uncanny_Automator\Rest\Log_Endpoint;

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
use WP_HTTP_Response;

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
		array( \Uncanny_Automator\Rest\Auth\Auth::class, 'verify_nonce' )
	);

	/**
	 * Orchestrates the user endpoint.
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
			'callback'            => function( WP_REST_Request $request ) {
				// Only include the file when needed.
				require_once UA_ABSPATH . 'src/core/services/rest/endpoint/user-endpoint.php';
				// And instantiate when needed.
				return apply_filters(
					'automator_rest_routes_user_response',
					( new User_Endpoint() )->find_by_id( $request )
				);
			},
		)
	);

	/**
	 * Orchestrates the log endpoint.
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
			'callback'            => function( WP_REST_Request $request ) {

				// Disable errors so JS won't break.
				if ( ! apply_filters( 'automator_rest_routes_log_display_notices_warnings', false ) ) {
					ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Blacklisted
				}

				global $wpdb;
				require_once __DIR__ . '/rest/autoload.php';

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
				require_once __DIR__ . '/resolver/fields-conditions-resolver.php';
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

};
