<?php
/**
 * This file initiate the various rest endpoints.
 *
 * @since 4.12
 */
namespace Uncanny_Automator\Rest\Log_Endpoint;

use Exception;
use Uncanny_Automator\App\Infrastructure\Database\Database;
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
					// phpcs:ignore PluginCheck.CodeAnalysis.PHPErrorReporting.IniDirectiveDisplay_errors, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.IniSet -- Hides PHP notices for this REST response only, so the JSON stays valid; the filter above lets a site keep them.
					ini_set( 'display_errors', '0' );
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

				// Allow for customizing the logs factory.
				$logs_factory = apply_filters( 'automator_rest_routes_log_endpoint_logs_factory', $logs_factory );

				// The main endpoint controller.
				$log_endpoint = new Log_Endpoint( $automator_factory, $logs_factory );

				$log_endpoint->set_utils( $utils );

				// The wiring of the objects above can be simplified with DiC.
				$log = $log_endpoint->get_log( $request );

				// A run that is not there, or not under this recipe and run number, is a 404.
				$response = new WP_HTTP_Response( $log, ! empty( $log['success'] ) ? 200 : 404 );

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
	 * Registers the loop runs pagination endpoint.
	 *
	 * Returns only the runs for a specific loop action, avoiding the overhead
	 * of re-fetching the entire log structure.
	 *
	 * #[Uncanny_Automator_Route('/wp-json/automator/v1/log/loop-runs/:recipe_id/:run_number/:recipe_log_id/:action_id')]
	 */
	register_rest_route(
		'automator/v1',
		'/log/loop-runs/(?P<recipe_id>\d+)/(?P<run_number>\d+)/(?P<recipe_log_id>\d+)/(?P<action_id>\d+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {

				if ( ! apply_filters( 'automator_rest_routes_log_display_notices_warnings', false ) ) {
					// phpcs:ignore PluginCheck.CodeAnalysis.PHPErrorReporting.IniDirectiveDisplay_errors, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.IniSet -- Hides PHP notices for this REST response only, so the JSON stays valid; the filter above lets a site keep them.
					ini_set( 'display_errors', '0' );
				}

				global $wpdb;

				$params = array(
					'recipe_id'     => absint( $request->get_param( 'recipe_id' ) ),
					'recipe_log_id' => absint( $request->get_param( 'recipe_log_id' ) ),
					'run_number'    => absint( $request->get_param( 'run_number' ) ),
				);

				$action_id = absint( $request->get_param( 'action_id' ) );
				$page      = max( 1, absint( $request->get_param( 'page' ) ) );
				$per_page  = min( absint( $request->get_param( 'per_page' ) ), 500 );

				if ( 0 === $per_page ) {
					$per_page = Loop_Logs_Resources::DEFAULT_RUNS_PER_PAGE;
				}

				$utils             = new Formatters_Utils();
				$automator_factory = new Automator_Factory(
					Automator_Functions::get_instance(),
					new Automator_Status()
				);

				$loop_logs_queries   = new Loop_Logs_Queries( $wpdb );
				$loops_logs_resources = new Loop_Logs_Resources( $loop_logs_queries, $utils, $automator_factory );
				$loops_logs_resources->set_runs_pagination( $per_page, $page );

				// Get the loop entries for this recipe run.
				$loops_log = $loop_logs_queries->get_recipe_loops_logs( $params );

				if ( empty( $loops_log ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'error'   => 'No loop entries found',
						),
						404
					);
				}

				// Find the loop containing this action and return its runs.
				foreach ( $loops_log as $log ) {
					$flow = (array) automator_safe_unserialize( $log['flow'] );

					$loop_index = array_search( absint( $log['loop_id'] ), array_column( $flow['items'] ?? array(), 'id' ), true );

					if ( false === $loop_index ) {
						continue;
					}

					$loop = $flow['items'][ $loop_index ];

					// Search for the action inside this loop.
					foreach ( (array) $loop['items'] as $item ) {

						// Direct action inside the loop.
						if ( 'action' === $item['type'] && absint( $item['id'] ) === $action_id ) {
							$runs = $loops_logs_resources->get_runs( $loop, $item, $params, $action_id, $log );

							return new WP_HTTP_Response(
								array(
									'success'   => true,
									'action_id' => $action_id,
									'runs'      => $runs,
								),
								200
							);
						}

						// Action inside a filter (action condition).
						if ( 'filter' === $item['type'] && ! empty( $item['items'] ) ) {
							foreach ( (array) $item['items'] as $filter_item ) {
								if ( absint( $filter_item['id'] ) === $action_id ) {
									$runs = $loops_logs_resources->get_runs( $loop, $item, $params, $action_id, $log );

									return new WP_HTTP_Response(
										array(
											'success'   => true,
											'action_id' => $action_id,
											'runs'      => $runs,
										),
										200
									);
								}
							}
						}
					}
				}

				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'Action not found in any loop',
					),
					404
				);
			},
		)
	);

	/**
	 * Registers the replay endpoint.
	 *
	 * Re-executes a previous recipe run using its stored snapshot.
	 * Creates a new recipe log with the same trigger data.
	 *
	 * #[Uncanny_Automator_Route('/wp-json/automator/v1/replay/:recipe_log_id')]
	 */
	register_rest_route(
		'automator/v1',
		'/replay/(?P<recipe_log_id>\d+)',
		array(
			'methods'             => 'POST',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {

				$recipe_log_id = absint( $request->get_param( 'recipe_log_id' ) );

				if ( 0 === $recipe_log_id ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Missing recipe_log_id.',
						),
						400
					);
				}

				// Check if snapshot exists and is replayable.
				$snapshot_store = Automator()->recipe_runner->snapshot_store();

				if ( ! $snapshot_store->is_replayable( $recipe_log_id ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Snapshot expired or not found. Replay is only available for 48 hours after the original run.',
						),
						410 // Gone — snapshot expired.
					);
				}

				// A deleted run stays deleted, even while its snapshot outlives it.
				if ( null === Database::get_execution_log_store()->get_recipe_log_row( $recipe_log_id ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => esc_html_x( 'This run was deleted, so it cannot be replayed.', 'Replay REST error', 'uncanny-automator' ),
						),
						410 // Gone — the run was deleted.
					);
				}

				// Verify the recipe still exists and is published.
				$snapshot = $snapshot_store->get( $recipe_log_id );
				$recipe_id = $snapshot ? (int) ( $snapshot->to_array()['recipe_id'] ?? 0 ) : 0;

				if ( 0 === $recipe_id || 'publish' !== get_post_status( $recipe_id ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => esc_html__( 'Recipe no longer exists or is not published.', 'uncanny-automator' ),
						),
						404
					);
				}

				// Execute the replay.
				$result = Automator()->recipe_runner->replay( $recipe_log_id );

				if ( $result->should_halt() ) {
					// Log full reason internally, return generic message to client.
					automator_log( 'Replay halted: ' . $result->get_halt_reason(), 'REST_Replay' );

					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => esc_html__( 'Replay could not be completed.', 'uncanny-automator' ),
						),
						500
					);
				}

				$status_int  = $result->get_recipe_status();
				$status_name = null !== $status_int ? \Uncanny_Automator\Automator_Status::get_class_name( $status_int ) : 'unknown';

				return new WP_REST_Response(
					array(
						'success'           => true,
						'new_recipe_log_id' => $result->get_recipe_log_id(),
						'recipe_id'         => $result->get_recipe_id(),
						'status'            => $status_name,
						'message'           => 'Recipe replayed successfully.',
					),
					200
				);
			},
		)
	);

	/**
	 * Registers the nonce refresh endpoint.
	 *
	 * This endpoint returns a fresh WordPress REST API nonce to prevent expiration issues when users keep the application open for extended periods.
	 *
	 * #[Uncanny_Automator_Route('/wp-json/automator/v2/nonce/refresh')]
	 */
	register_rest_route(
		'uap/v2',
		'/site/refresh-nonce',
		array(
			'methods'             => 'POST',
			'permission_callback' => $authentication,
			'callback'            => function ( WP_REST_Request $request ) {
				// Generate a fresh nonce for the current user.
				$nonce = wp_create_nonce( 'wp_rest' );

				// Calculate when the current nonce tick expires.
				// WordPress nonces use 12-hour ticks, valid for 2 ticks (24 hours total).
				// We calculate time until the NEXT tick boundary (when a new nonce will be generated).
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, applied as WordPress applies it.
				$nonce_tick    = apply_filters( 'nonce_life', DAY_IN_SECONDS ) / 2; // Default: 43200 seconds (12 hours).
				$current_tick  = ceil( time() / $nonce_tick );
				$next_tick     = $current_tick * $nonce_tick;
				$expires_in    = $next_tick - time(); // Seconds until next tick.
				$expires_at    = $next_tick; // Unix timestamp of expiration.

				return new WP_REST_Response(
					array(
						'success' => true,
						'data'    => array(
							'nonce'                => $nonce,
							'timestamp'            => time(),
							'expires_in_seconds'   => $expires_in, // Seconds until next nonce tick.
							'expires_at_timestamp' => $expires_at, // Unix timestamp when nonce changes.
						),
					),
					200
				);
			},
		)
	);
}
