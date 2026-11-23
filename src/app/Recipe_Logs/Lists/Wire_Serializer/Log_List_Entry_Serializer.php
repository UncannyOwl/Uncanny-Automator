<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Logs\Lists\Enums\Log_List_Kind;
use Uncanny_Automator\App\Recipe_Logs\Lists\Value_Objects\Log_List_Page_Context;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status_Registry;
use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Node_Status_Resolver;
use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Run_Data_Bundle;

/**
 * One row of a Logs page list as the entry the frontend's strict schema
 * reads (`ModelLogsViewer*EntrySchema`).
 *
 * A pure projection: the row and the page's batched context in, the entry
 * out. Statuses come from the same registry, and the same pending-delay rule,
 * as the run's log, so a row and its run say the same thing.
 *
 * @since 8.1.0
 */
final class Log_List_Entry_Serializer {

	/**
	 * The integration code of an item whose post names none, as the run's log
	 * sends it.
	 *
	 * @var string
	 */
	const UNKNOWN_INTEGRATION = 'UNKNOWN';

	/**
	 * The current moment for the short and relative dates, or null to use the current time.
	 *
	 * @var int|null
	 */
	private $now;

	/**
	 * @param int|null $now The current moment; the real one when null.
	 */
	public function __construct( ?int $now = null ) {
		$this->now = $now;
	}

	/**
	 * The entry for one row.
	 *
	 * @param string                $kind    The list.
	 * @param array<string, mixed>  $row     The store's row.
	 * @param Log_List_Page_Context $context The page's batches.
	 *
	 * @return array<string, mixed>
	 */
	public function entry( string $kind, array $row, Log_List_Page_Context $context ): array {
		$entry = $this->common( $kind, $row, $context );

		switch ( $kind ) {
			case Log_List_Kind::RECIPE_RUNS:
				$entry['can_delete'] = $context->recipe( (int) $row['recipe_id'] )['can_delete'];
				$entry['is_replay']  = $context->is_replay( (int) $row['recipe_log_id'] );
				break;

			case Log_List_Kind::TRIGGERS:
				$entry['item']          = $this->item( $row, $context );
				$entry['recipe_status'] = Log_Status_Registry::from_legacy_int( (int) $row['recipe_completed'] )->to_array();
				break;

			case Log_List_Kind::ACTIONS:
				$entry['item']          = $this->item( $row, $context );
				$entry['recipe_status'] = Log_Status_Registry::from_legacy_int( (int) $row['recipe_completed'] )->to_array();
				$entry['notes']         = $context->note( (int) $row['entry_id'] );
				break;

			case Log_List_Kind::FAILED_ACTIONS:
				$detail = Failed_Action_Reason::detail( $context->note( (int) $row['entry_id'] ), $row['response'] ?? null, (string) ( $row['api_status'] ?? '' ) );

				$entry['item']         = $this->item( $row, $context );
				$entry['item_log_id']  = (string) (int) $row['entry_id'];
				$entry['reason']       = Failed_Action_Reason::reason( $detail );
				$entry['detail']       = $detail;
				$entry['http_status']  = trim( (string) ( $row['api_status'] ?? '' ) );
				$entry['endpoint']     = (string) ( $row['endpoint'] ?? '' );
				$entry['resent_count'] = (int) ( $row['resent_count'] ?? 0 );
				$entry['can_resend']   = $context->can_replay( (int) $row['entry_id'] );
				break;
		}

		return $entry;
	}

	/**
	 * The keys every entry carries.
	 *
	 * @param string                $kind    The list.
	 * @param array<string, mixed>  $row     The store's row.
	 * @param Log_List_Page_Context $context The page's batches.
	 *
	 * @return array<string, mixed>
	 */
	private function common( string $kind, array $row, Log_List_Page_Context $context ): array {
		$date          = new Log_List_Date( (string) $row['date_time'], $this->now );
		$recipe_id     = (int) $row['recipe_id'];
		$recipe_log_id = (int) $row['recipe_log_id'];
		$status        = $this->status( $kind, $row, $context );
		$show_relative = Log_List_Kind::TRIGGERS === $kind || $status->is_terminal();

		return array(
			'id'            => (string) (int) $row['entry_id'],
			'run'           => array(
				'recipe_id'  => $recipe_id,
				'run_number' => (int) $row['run_number'],
				'log_id'     => $recipe_log_id,
			),
			'recipe'        => array(
				'id'       => $recipe_id,
				'title'    => $this->title( (string) $row['recipe_title'], $recipe_id ),
				'edit_url' => $context->recipe( $recipe_id )['edit_url'],
			),
			'user'          => $this->user( $row, $context ),
			'status'        => $status->to_array(),
			'date'          => $date->full(),
			'date_short'    => $date->short(),
			'date_relative' => $show_relative ? $date->relative() : null,
			'date_gmt'      => $date->gmt(),
			'integrations'  => $context->run_integrations( $recipe_log_id ),
		);
	}

	/**
	 * The entry's own status: the run's on a recipe run, the trigger's or
	 * action's own otherwise. An action still waiting on its delay or schedule
	 * reads Delayed or Scheduled, as in its run's log.
	 *
	 * @param string                $kind    The list.
	 * @param array<string, mixed>  $row     The store's row.
	 * @param Log_List_Page_Context $context The page's batches.
	 *
	 * @return Log_Status
	 */
	private function status( string $kind, array $row, Log_List_Page_Context $context ): Log_Status {
		$completed = (int) $row['completed'];

		if ( Log_List_Kind::lists_actions( $kind ) ) {
			$config = Run_Data_Bundle::decode_delay_config( $context->delays( (int) $row['recipe_log_id'] ), (int) $row['item_id'] );
			$code   = Node_Status_Resolver::pending_delay_code( $completed, $config );

			if ( null !== $code ) {
				return Log_Status_Registry::from_code( $code );
			}
		}

		return Log_Status_Registry::from_legacy_int( $completed );
	}

	/**
	 * The entry's user. A guest and a deleted user carry no email, picture or
	 * profile address.
	 *
	 * @param array<string, mixed>  $row     The store's row.
	 * @param Log_List_Page_Context $context The page's batches.
	 *
	 * @return array{id: int, name: string, email: string, avatar_url: string, edit_url: string}
	 */
	private function user( array $row, Log_List_Page_Context $context ): array {
		$id    = (int) $row['user_id'];
		$found = null !== ( $row['user_found'] ?? null );

		if ( 0 === $id || ! $found ) {
			return array(
				'id'         => $id,
				'name'       => 0 === $id
					? esc_html_x( 'Guest', 'Logs list entry', 'uncanny-automator' )
					/* translators: %d: The id of a user who was deleted. */
					: sprintf( esc_html_x( 'Deleted user #%d', 'Logs list entry', 'uncanny-automator' ), $id ),
				'email'      => '',
				'avatar_url' => '',
				'edit_url'   => '',
			);
		}

		$links = $context->user( $id );

		return array(
			'id'         => $id,
			'name'       => Log_List_Text::plain( (string) $row['display_name'] ),
			'email'      => (string) $row['user_email'],
			'avatar_url' => $links['avatar_url'],
			'edit_url'   => $links['edit_url'],
		);
	}

	/**
	 * The trigger or action the entry logged.
	 *
	 * @param array<string, mixed>  $row     The store's row.
	 * @param Log_List_Page_Context $context The page's batches.
	 *
	 * @return array{id: int, title: string, integration: string}
	 */
	private function item( array $row, Log_List_Page_Context $context ): array {
		$item_id     = (int) $row['item_id'];
		$title       = $context->item_title( (int) $row['entry_id'] );
		$integration = $context->item_integration( $item_id );

		return array(
			'id'          => $item_id,
			'title'       => '' !== $title ? $title : $this->title( (string) ( $row['item_title'] ?? '' ), $item_id ),
			'integration' => '' !== $integration ? $integration : self::UNKNOWN_INTEGRATION,
		);
	}

	/**
	 * A post's title, or its id when it has none.
	 *
	 * @param string $title The stored title.
	 * @param int    $id    The post id.
	 *
	 * @return string
	 */
	private function title( string $title, int $id ): string {
		$title = trim( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( '' !== $title ) {
			return $title;
		}

		/* translators: %d: The id of a recipe, trigger or action that has no title. */
		return sprintf( esc_html_x( 'ID: %d (no title)', 'Logs list entry', 'uncanny-automator' ), $id );
	}
}
