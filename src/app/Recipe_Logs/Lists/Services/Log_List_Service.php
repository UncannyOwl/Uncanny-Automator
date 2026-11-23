<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Services;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Log_List_Store;
use Uncanny_Automator\App\Recipe_Logs\Lists\Enums\Log_List_Kind;
use Uncanny_Automator\App\Recipe_Logs\Lists\Invalid_Log_List_Param;
use Uncanny_Automator\App\Recipe_Logs\Lists\Value_Objects\Log_List_Page_Context;
use Uncanny_Automator\App\Recipe_Logs\Lists\Value_Objects\Log_List_Query;
use Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer\Log_List_Entry_Serializer;
use Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer\Log_List_Text;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Resend_Action_Service;
use WP_Error;

/**
 * Reads one page of a Logs page list: `GET logs/{recipe-runs|trigger-logs|action-logs|failed-actions}`.
 *
 * Validates the parameters, refuses filters on a site that cannot filter,
 * reads the page and its total, reads what the page needs beyond its rows in
 * batches (never one query per row), and serializes each row.
 *
 * Read-only. Returns the reply's `data` or a WP_Error; never throws.
 *
 * @since 8.1.0
 */
final class Log_List_Service {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/** @var Log_List_Store */
	private $store;

	/** @var Action_Error_Store */
	private $errors;

	/** @var Integration_Registry_Bridge */
	private $integrations;

	/** @var Log_Filters_Availability */
	private $availability;

	/** @var Recipe_Run_Delete_Policy */
	private $delete_policy;

	/** @var Resend_Action_Service */
	private $resend;

	/** @var Log_List_Entry_Serializer */
	private $serializer;

	/**
	 * @param Log_List_Store|null              $store         The list store.
	 * @param Action_Error_Store|null          $errors        The error store.
	 * @param Integration_Registry_Bridge|null $integrations  The integration catalog.
	 * @param Log_Filters_Availability|null    $availability  Whether the site can filter.
	 * @param Recipe_Run_Delete_Policy|null    $delete_policy Who may delete runs.
	 * @param Resend_Action_Service|null       $resend        Whether a stored request replays.
	 * @param Log_List_Entry_Serializer|null   $serializer    The entry serializer.
	 */
	private function __construct(
		?Log_List_Store $store = null,
		?Action_Error_Store $errors = null,
		?Integration_Registry_Bridge $integrations = null,
		?Log_Filters_Availability $availability = null,
		?Recipe_Run_Delete_Policy $delete_policy = null,
		?Resend_Action_Service $resend = null,
		?Log_List_Entry_Serializer $serializer = null
	) {
		$this->store         = $store ?? Database::get_log_list_store();
		$this->errors        = $errors ?? Database::get_action_error_store();
		$this->integrations  = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->availability  = $availability ?? new Log_Filters_Availability();
		$this->delete_policy = $delete_policy ?? new Recipe_Run_Delete_Policy();
		$this->resend        = $resend ?? new Resend_Action_Service();
		$this->serializer    = $serializer ?? new Log_List_Entry_Serializer();
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
	 * @param Log_List_Store|null              $store         The list store.
	 * @param Action_Error_Store|null          $errors        The error store.
	 * @param Integration_Registry_Bridge|null $integrations  The integration catalog.
	 * @param Log_Filters_Availability|null    $availability  Whether the site can filter.
	 * @param Recipe_Run_Delete_Policy|null    $delete_policy Who may delete runs.
	 * @param Resend_Action_Service|null       $resend        Whether a stored request replays.
	 * @param Log_List_Entry_Serializer|null   $serializer    The entry serializer.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Log_List_Store $store = null,
		?Action_Error_Store $errors = null,
		?Integration_Registry_Bridge $integrations = null,
		?Log_Filters_Availability $availability = null,
		?Recipe_Run_Delete_Policy $delete_policy = null,
		?Resend_Action_Service $resend = null,
		?Log_List_Entry_Serializer $serializer = null
	): self {
		return new self( $store, $errors, $integrations, $availability, $delete_policy, $resend, $serializer );
	}

	/**
	 * One page of a list and the total across all pages.
	 *
	 * @param string               $kind   One of {@see Log_List_Kind::all()}.
	 * @param array<string, mixed> $params The request's parameters.
	 *
	 * @return array{entries: array<int, array<string, mixed>>, total: int}|WP_Error
	 */
	public function get_page( string $kind, array $params ) {
		try {
			$query = Log_List_Query::from_params( $kind, $params );
		} catch ( Invalid_Log_List_Param $refused ) {
			return new WP_Error(
				'rest_invalid_param',
				$refused->getMessage(),
				array(
					'status' => 400,
					'params' => array( $refused->get_param() => $refused->getMessage() ),
				)
			);
		} catch ( \InvalidArgumentException $unknown ) {
			return new WP_Error( 'automator_unknown_log_list', $unknown->getMessage(), array( 'status' => 404 ) );
		}

		if ( $query->has_filters() && ! $this->availability->is_available() ) {
			return new WP_Error(
				'automator_logs_filters_unavailable',
				esc_html_x( 'Filtering the logs needs Automator Pro. Search and sorting work without it.', 'Logs list REST error', 'uncanny-automator' ),
				array( 'status' => 403 )
			);
		}

		$rows    = $this->store->find_rows( $query );
		$context = $this->context( $kind, $rows );
		$entries = array();

		foreach ( $rows as $row ) {
			$entries[] = $this->serializer->entry( $kind, $row, $context );
		}

		return array(
			'entries' => $entries,
			'total'   => $this->store->count_rows( $query ),
		);
	}

	/**
	 * Everything the page needs beyond its rows, read in batches.
	 *
	 * @param string                           $kind The list.
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return Log_List_Page_Context
	 */
	private function context( string $kind, array $rows ): Log_List_Page_Context {
		if ( array() === $rows ) {
			return new Log_List_Page_Context( array() );
		}

		$recipe_log_ids = $this->column( $rows, 'recipe_log_id' );
		$entry_ids      = $this->column( $rows, 'entry_id' );
		$item_ids       = Log_List_Kind::has_item( $kind ) ? $this->column( $rows, 'item_id' ) : array();
		$steps          = $this->store->get_run_steps( $recipe_log_ids );

		$step_item_ids = array();
		foreach ( $steps as $run_steps ) {
			foreach ( $run_steps as $step ) {
				if ( 'item' === $step['kind'] ) {
					$step_item_ids[] = $step['id'];
				}
			}
		}

		$item_integrations = $this->store->get_post_meta_values( array_merge( $item_ids, $step_item_ids ), 'integration' );

		$data = array(
			'run_integrations'  => $this->run_integrations( $steps, $item_integrations ),
			'item_integrations' => $item_integrations,
			'users'             => $this->users( $rows ),
			'recipes'           => $this->recipes( $kind, $rows ),
		);

		if ( Log_List_Kind::TRIGGERS === $kind ) {
			$data['item_titles'] = array_map(
				static function ( string $sentence ): string {
					return Log_List_Text::sentence( $sentence, false );
				},
				$this->store->get_trigger_sentences( $entry_ids )
			);
		}

		if ( Log_List_Kind::lists_actions( $kind ) ) {
			$data['item_titles'] = $this->action_titles( $entry_ids );
			$data['notes']       = $this->notes( $rows );
			$data['delays']      = $this->delays( $rows );
		}

		if ( Log_List_Kind::RECIPE_RUNS === $kind ) {
			$data['replays'] = array_map( '__return_true', $this->store->get_recipe_log_meta_values( $recipe_log_ids, 'replay_source' ) );
		}

		if ( Log_List_Kind::FAILED_ACTIONS === $kind ) {
			$data['replayable'] = $this->replayable( $rows );
		}

		return new Log_List_Page_Context( $data );
	}

	/**
	 * Each run's integrations: the integration of each trigger and action that
	 * ran, a loop's actions included, and LOOP for a loop, each once, in the
	 * order they ran. Codes missing from the integration catalog are left out,
	 * because the frontend draws each logo from the catalog.
	 *
	 * @param array<int, array<int, array{kind: string, id: int}>> $steps             Per run id.
	 * @param array<int, string>                                  $item_integrations Per item post id.
	 *
	 * @return array<int, string[]>
	 */
	private function run_integrations( array $steps, array $item_integrations ): array {
		$catalog = $this->integrations->get_all_integrations();
		$runs    = array();

		foreach ( $steps as $recipe_log_id => $run_steps ) {
			$codes = array();

			foreach ( $run_steps as $step ) {
				$code = 'loop' === $step['kind'] ? 'LOOP' : ( $item_integrations[ $step['id'] ] ?? '' );

				if ( '' !== $code && isset( $catalog[ $code ] ) && ! in_array( $code, $codes, true ) ) {
					$codes[] = $code;
				}
			}

			$runs[ $recipe_log_id ] = $codes;
		}

		return $runs;
	}

	/**
	 * Each user's picture and profile addresses. The picture asks Gravatar to
	 * fail (404) for a user without one, so the frontend draws their initials;
	 * it is empty when the site shows no pictures.
	 *
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<int, array{avatar_url: string, edit_url: string}>
	 */
	private function users( array $rows ): array {
		$show  = (bool) get_option( 'show_avatars' );
		$users = array();

		foreach ( $rows as $row ) {
			$id = (int) $row['user_id'];

			if ( 0 === $id || null === ( $row['user_found'] ?? null ) || isset( $users[ $id ] ) ) {
				continue;
			}

			$users[ $id ] = array(
				'avatar_url' => $show ? (string) get_avatar_url( $id, array( 'default' => '404' ) ) : '',
				'edit_url'   => (string) get_edit_user_link( $id ),
			);
		}

		return $users;
	}

	/**
	 * Each recipe's editor address, when the current user may edit it, and
	 * whether they may delete its runs.
	 *
	 * @param string                           $kind The list.
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<int, array{edit_url: string, can_delete: bool}>
	 */
	private function recipes( string $kind, array $rows ): array {
		$recipes = array();

		foreach ( $this->column( $rows, 'recipe_id' ) as $recipe_id ) {
			$recipes[ $recipe_id ] = array(
				'edit_url'   => current_user_can( 'edit_post', $recipe_id ) ? (string) get_edit_post_link( $recipe_id, 'raw' ) : '',
				'can_delete' => Log_List_Kind::RECIPE_RUNS === $kind && $this->delete_policy->can_delete( $recipe_id ),
			);
		}

		return $recipes;
	}

	/**
	 * Each action log row's sentence as plain text.
	 *
	 * @param int[] $action_log_ids Action log ids.
	 *
	 * @return array<int, string>
	 */
	private function action_titles( array $action_log_ids ): array {
		$titles = array();

		foreach ( $this->store->get_action_sentences( $action_log_ids ) as $log_id => $sentence ) {
			$titles[ $log_id ] = Log_List_Text::sentence( $sentence['text'], $sentence['html'] );
		}

		return $titles;
	}

	/**
	 * Each action's note, the same text its run's log shows: its last error,
	 * else the message stored on its row.
	 *
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<int, string>
	 */
	private function notes( array $rows ): array {
		$errors = $this->errors->get_messages_by_action_logs( $this->column( $rows, 'entry_id' ) );
		$notes  = array();

		foreach ( $rows as $row ) {
			$log_id   = (int) $row['entry_id'];
			$messages = $errors[ $log_id ] ?? array();
			$stored   = array() !== $messages ? (string) end( $messages ) : (string) ( $row['error_message'] ?? '' );

			$notes[ $log_id ] = Log_List_Text::plain( $stored );
		}

		return $notes;
	}

	/**
	 * The action_delays meta of the runs whose action has not finished; a
	 * finished action never reads as delayed.
	 *
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<int, string>
	 */
	private function delays( array $rows ): array {
		$pending = array();

		foreach ( $rows as $row ) {
			if ( ! Automator_Status::finished( (int) $row['completed'] ) ) {
				$pending[] = (int) $row['recipe_log_id'];
			}
		}

		return $this->store->get_recipe_log_meta_values( $pending, Execution_Log_Store::META_ACTION_DELAYS );
	}

	/**
	 * Whether each failed action's stored request can be replayed.
	 *
	 * @param array<int, array<string, mixed>> $rows The page's rows.
	 *
	 * @return array<int, bool>
	 */
	private function replayable( array $rows ): array {
		$replayable = array();

		foreach ( $rows as $row ) {
			$replayable[ (int) $row['entry_id'] ] = $this->resend->can_replay(
				(object) array(
					'params'   => $row['params'] ?? '',
					'endpoint' => (string) ( $row['endpoint'] ?? '' ),
				)
			);
		}

		return $replayable;
	}

	/**
	 * One integer column of the rows, each value once.
	 *
	 * @param array<int, array<string, mixed>> $rows   The rows.
	 * @param string                           $column The column.
	 *
	 * @return int[]
	 */
	private function column( array $rows, string $column ): array {
		return array_values( array_unique( array_map( 'intval', array_column( $rows, $column ) ) ) );
	}
}
