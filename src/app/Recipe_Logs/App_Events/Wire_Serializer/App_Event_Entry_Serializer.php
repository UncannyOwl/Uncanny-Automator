<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\App_Events\Wire_Serializer;

use Uncanny_Automator\App\Integration_Catalog\App_Events\App_Event_Data;
use Uncanny_Automator\App\Integration_Catalog\App_Events\App_Event_Sources;
use Uncanny_Automator\App\Integration_Catalog\App_Events\Value_Objects\App_Event_Summary;
use Uncanny_Automator\App\Recipe_Logs\App_Events\Status\App_Event_Status;
use Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer\Log_List_Date;

/**
 * One App events entry on the wire: an event an app sent the site through
 * the platform, which app and account it came from, what it says changed,
 * whether Automator handled it, and the runs it started.
 *
 * What changed is the app's to say: its event source summarizes the
 * delivery ({@see \Uncanny_Automator\App\Integration_Catalog\App_Events\App_Event_Source::summarize()}).
 * An app with no source on the request reads as its count of changes.
 *
 * @since 8.1.0
 */
final class App_Event_Entry_Serializer {

	/**
	 * Longest error text an entry carries.
	 *
	 * @var int
	 */
	const MAX_ERROR_LENGTH = 300;

	/**
	 * The apps that send events, by the platform's slug.
	 *
	 * @var App_Event_Sources
	 */
	private $sources;

	/**
	 * @param App_Event_Sources|null $sources The apps that send events.
	 */
	public function __construct( ?App_Event_Sources $sources = null ) {
		$this->sources = $sources ?? automator_app_event_sources();
	}

	/**
	 * The app an event came from: the integration code its source states
	 * for the platform's slug, and the integration's name. An app with no
	 * source on this request is shown by its slug.
	 *
	 * @param string                $slug  The platform's slug, as the inbox stores it.
	 * @param array<string, string> $names Integration names by code.
	 *
	 * @return array{code: string, name: string}
	 */
	public function app( string $slug, array $names ): array {
		$source = $this->sources->find( $slug );

		if ( null === $source ) {
			return array(
				'code' => $slug,
				'name' => $slug,
			);
		}

		$code = $source->get_integration_code();

		return array(
			'code' => $code,
			'name' => $names[ $code ] ?? $code,
		);
	}

	/**
	 * The entry for one inbox row.
	 *
	 * @param array<string, mixed>                                                                $row      A row from Platform_Inbox_Store::find_app_events(), with its `run_ids` and `runs_total`.
	 * @param array<string, string>                                                               $apps     App name per integration code.
	 * @param array<string, string>                                                               $accounts Account label per connection ID.
	 * @param array<int, array{recipe_id: int, run_number: int, recipe_title: string}>            $runs     Per run ID, the runs that still exist.
	 * @param int|null                                                                            $now      The current moment; the real one when null.
	 *
	 * @return array<string, mixed>
	 */
	public function serialize( array $row, array $apps, array $accounts, array $runs, ?int $now = null ): array {
		$payload       = (array) $row['payload'];
		$app           = $this->app( (string) ( $payload['integration'] ?? '' ), $apps );
		$connection_id = (string) ( $payload['connection_id'] ?? '' );
		$summary       = $this->summary( (string) ( $payload['integration'] ?? '' ), is_array( $payload['data'] ?? null ) ? $payload['data'] : array() );
		$date          = new Log_List_Date( wp_date( 'Y-m-d H:i:s', (int) $row['received_at'] ), $now );

		return array(
			'id'         => (string) $row['ID'],
			'app'        => array(
				'code'    => $app['code'],
				'name'    => $app['name'],
				'account' => $accounts[ $connection_id ] ?? '',
			),
			'event'      => array(
				'name'    => (string) ( $payload['event_name'] ?? '' ),
				'summary' => $summary->get_summary(),
				'changes' => $summary->get_changes(),
				'items'   => $summary->get_items(),
			),
			'status'     => App_Event_Status::for_inbox_status( (string) $row['status'] )->to_array(),
			'attempts'   => (int) $row['attempts'],
			'error'      => $this->error( (string) $row['error_message'] ),
			'date'       => $date->full(),
			'date_short' => $date->short(),
			'date_gmt'   => $date->gmt(),
			'runs'       => $this->runs( (array) ( $row['run_ids'] ?? array() ), $runs ),
			'runs_total' => (int) ( $row['runs_total'] ?? 0 ),
		);
	}

	/**
	 * What the delivery says, in its app's words.
	 *
	 * @param string               $slug The platform's slug for the app.
	 * @param array<string, mixed> $data The vendor event's data block.
	 *
	 * @return App_Event_Summary
	 */
	private function summary( string $slug, array $data ): App_Event_Summary {
		$source = $this->sources->find( $slug );

		return null !== $source ? $source->summarize( $data ) : App_Event_Data::summarize_changes( $data );
	}

	/**
	 * The error a failed attempt left, on one line and capped.
	 *
	 * @param string $message The stored error.
	 *
	 * @return string
	 */
	private function error( string $message ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', $message ) );

		return mb_strlen( $text ) > self::MAX_ERROR_LENGTH ? rtrim( mb_substr( $text, 0, self::MAX_ERROR_LENGTH - 1 ) ) . '…' : $text;
	}

	/**
	 * The runs the event started that still exist, in the order they started.
	 *
	 * @param int[]                                                                     $ids  The row's run IDs.
	 * @param array<int, array{recipe_id: int, run_number: int, recipe_title: string}> $runs Per run ID.
	 *
	 * @return array<int, array{recipe_id: int, recipe_title: string, run_number: int, log_id: int}>
	 */
	private function runs( array $ids, array $runs ): array {
		$entries = array();

		foreach ( $ids as $log_id ) {
			if ( ! isset( $runs[ (int) $log_id ] ) ) {
				continue;
			}

			$run       = $runs[ (int) $log_id ];
			$entries[] = array(
				'recipe_id'    => $run['recipe_id'],
				'recipe_title' => $run['recipe_title'],
				'run_number'   => $run['run_number'],
				'log_id'       => (int) $log_id,
			);
		}

		return $entries;
	}
}
