<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\App_Events\Services;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Log_List_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Integration_Catalog\App_Events\App_Event_Sources;
use Uncanny_Automator\App\Recipe_Logs\App_Events\Value_Objects\App_Event_Query;
use Uncanny_Automator\App\Recipe_Logs\App_Events\Wire_Serializer\App_Event_Entry_Serializer;
use Uncanny_Automator\App\Recipe_Logs\Lists\Invalid_Log_List_Param;
use Uncanny_Automator\App\Recipe_Logs\Lists\Services\Log_Filters_Availability;
use WP_Error;

/**
 * The Logs page's App events list: the events apps sent the site through
 * the platform (the inbox's `vendor_event` rows), newest first, with the
 * app and account each came from and the runs each started.
 *
 * The inbox keeps rows for its retention window
 * (Inbox_Drainer::APP_EVENT_RETENTION_SECONDS), so the list never reaches further
 * back than that.
 *
 * @since 8.1.0
 */
final class App_Event_List_Service {

	/**
	 * Most runs an entry links; `runs_total` says how many there are in all.
	 *
	 * @var int
	 */
	const RUNS_PER_EVENT = 10;

	/** @var self|null */
	private static $instance = null;

	/** @var Platform_Inbox_Store */
	private $inbox;

	/** @var Log_List_Store */
	private $runs;

	/** @var Integration_Registry_Bridge */
	private $integrations;

	/** @var Connection_Registry */
	private $connections;

	/** @var App_Event_Entry_Serializer */
	private $serializer;

	/** @var Log_Filters_Availability */
	private $availability;

	/** @var App_Event_Sources */
	private $sources;

	/**
	 * @param Platform_Inbox_Store|null         $inbox        The inbox store.
	 * @param Log_List_Store|null               $runs         Reads the runs an event started.
	 * @param Integration_Registry_Bridge|null  $integrations The integration catalog.
	 * @param Connection_Registry|null          $connections  The site's app connections.
	 * @param App_Event_Entry_Serializer|null $serializer   The entry serializer.
	 * @param Log_Filters_Availability|null     $availability Whether the site can filter.
	 * @param App_Event_Sources|null            $sources      The apps that send events.
	 */
	private function __construct(
		?Platform_Inbox_Store $inbox = null,
		?Log_List_Store $runs = null,
		?Integration_Registry_Bridge $integrations = null,
		?Connection_Registry $connections = null,
		?App_Event_Entry_Serializer $serializer = null,
		?Log_Filters_Availability $availability = null,
		?App_Event_Sources $sources = null
	) {
		$this->inbox        = $inbox ?? Database::get_platform_inbox_store();
		$this->runs         = $runs ?? Database::get_log_list_store();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->connections  = $connections ?? new Connection_Registry();
		$this->sources      = $sources ?? automator_app_event_sources();
		$this->serializer   = $serializer ?? new App_Event_Entry_Serializer( $this->sources );
		$this->availability = $availability ?? new Log_Filters_Availability();
	}

	/**
	 * The shared instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * An instance with its dependencies given, for tests.
	 *
	 * @param Platform_Inbox_Store|null         $inbox        The inbox store.
	 * @param Log_List_Store|null               $runs         Reads the runs an event started.
	 * @param Integration_Registry_Bridge|null  $integrations The integration catalog.
	 * @param Connection_Registry|null          $connections  The site's app connections.
	 * @param App_Event_Entry_Serializer|null $serializer   The entry serializer.
	 * @param Log_Filters_Availability|null     $availability Whether the site can filter.
	 * @param App_Event_Sources|null            $sources      The apps that send events.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Platform_Inbox_Store $inbox = null,
		?Log_List_Store $runs = null,
		?Integration_Registry_Bridge $integrations = null,
		?Connection_Registry $connections = null,
		?App_Event_Entry_Serializer $serializer = null,
		?Log_Filters_Availability $availability = null,
		?App_Event_Sources $sources = null
	): self {
		return new self( $inbox, $runs, $integrations, $connections, $serializer, $availability, $sources );
	}

	/**
	 * One page of app events, the total across all pages, and the apps the
	 * list can be filtered by.
	 *
	 * @param array<string, mixed> $params The request's parameters.
	 *
	 * @return array{entries: array<int, array<string, mixed>>, total: int, apps: array<int, array{code: string, name: string}>}|WP_Error
	 */
	public function get_page( array $params ) {
		try {
			$query = App_Event_Query::from_params( $params );
		} catch ( Invalid_Log_List_Param $refused ) {
			return new WP_Error(
				'rest_invalid_param',
				$refused->getMessage(),
				array(
					'status' => 400,
					'params' => array( $refused->get_param() => $refused->getMessage() ),
				)
			);
		}

		if ( $query->has_filters() && ! $this->availability->is_available() ) {
			return new WP_Error(
				'automator_logs_filters_unavailable',
				esc_html_x( 'Filtering the logs needs Automator Pro. Search and sorting work without it.', 'Logs list REST error', 'uncanny-automator' ),
				array( 'status' => 403 )
			);
		}

		$query = $this->for_platform_slug( $query );
		$rows  = $this->with_run_links( $this->inbox->find_app_events( $query ) );
		$names = $this->app_names();
		$runs  = $this->runs( $rows );

		$accounts = $this->accounts( $rows );
		$entries  = array();

		foreach ( $rows as $row ) {
			$entries[] = $this->serializer->serialize( $row, $names, $accounts, $runs );
		}

		return array(
			'entries' => $entries,
			'total'   => $this->inbox->count_app_events( $query ),
			'apps'    => $this->apps( $names ),
		);
	}

	/**
	 * Every integration's name, by code.
	 *
	 * @return array<string, string>
	 */
	private function app_names(): array {
		$names = array();

		foreach ( $this->integrations->get_all_integrations() as $code => $integration ) {
			$name = is_array( $integration ) ? (string) ( $integration['name'] ?? '' ) : '';

			$names[ (string) $code ] = '' !== $name ? $name : (string) $code;
		}

		return $names;
	}

	/**
	 * The read, filtering on the slug the platform stores for the asked app.
	 * An app with no source on this request is named by its slug, so its
	 * code is its slug.
	 *
	 * @param App_Event_Query $query The read as asked.
	 *
	 * @return App_Event_Query
	 */
	private function for_platform_slug( App_Event_Query $query ): App_Event_Query {
		$code = $query->get_app_code();

		if ( null === $code ) {
			return $query;
		}

		$source = $this->sources->find_by_code( $code );

		return $query->for_integration( null !== $source ? $source->get_platform_slug() : $code );
	}

	/**
	 * The apps the inbox holds events from, by name.
	 *
	 * @param array<string, string> $names Integration names by code.
	 *
	 * @return array<int, array{code: string, name: string}>
	 */
	private function apps( array $names ): array {
		$apps = array();

		foreach ( $this->inbox->get_app_event_integrations() as $slug ) {
			$apps[] = $this->serializer->app( $slug, $names );
		}

		usort( $apps, static fn( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] ) );

		return $apps;
	}

	/**
	 * The label of each account the page's events came from.
	 *
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<string, string> Per connection ID.
	 */
	private function accounts( array $rows ): array {
		$accounts = array();

		foreach ( $rows as $row ) {
			$connection_id = (string) ( $row['payload']['connection_id'] ?? '' );

			if ( '' === $connection_id || isset( $accounts[ $connection_id ] ) ) {
				continue;
			}

			$connection                 = $this->connections->get_by_connection_id( $connection_id );
			$accounts[ $connection_id ] = null !== $connection ? (string) $connection->label() : '';
		}

		return $accounts;
	}

	/**
	 * Each row with the first runs its event started that still exist
	 * (`run_ids`), and how many there are in all (`runs_total`).
	 *
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function with_run_links( array $rows ): array {
		$links = $this->inbox->find_started_runs( array_column( $rows, 'ID' ), self::RUNS_PER_EVENT );

		foreach ( $rows as $index => $row ) {
			$found = $links[ (int) $row['ID'] ] ?? array(
				'run_ids' => array(),
				'total'   => 0,
			);

			$rows[ $index ]['run_ids']    = $found['run_ids'];
			$rows[ $index ]['runs_total'] = $found['total'];
		}

		return $rows;
	}

	/**
	 * The runs the page's events started that still exist, with their recipe's title.
	 *
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<int, array{recipe_id: int, run_number: int, recipe_title: string}>
	 */
	private function runs( array $rows ): array {
		$ids = array();

		foreach ( $rows as $row ) {
			$ids = array_merge( $ids, (array) $row['run_ids'] );
		}

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		if ( array() === $ids ) {
			return array();
		}

		$runs = array();

		foreach ( $this->runs->get_runs( $ids ) as $log_id => $run ) {
			$title = get_the_title( $run['recipe_id'] );

			$runs[ (int) $log_id ] = array(
				'recipe_id'    => (int) $run['recipe_id'],
				'run_number'   => (int) $run['run_number'],
				/* translators: %d: The recipe's ID. */
				'recipe_title' => '' !== $title ? html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) : sprintf( esc_html_x( 'Recipe #%d', 'Logs', 'uncanny-automator' ), (int) $run['recipe_id'] ),
			);
		}

		return $runs;
	}
}
