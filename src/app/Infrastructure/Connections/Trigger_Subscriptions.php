<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Throwable;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Trigger subscriptions for connections whose `connection_id` changed —
 * staging-copies handoff R6.
 *
 * The platform keys an app trigger's subscription on the connection. A row
 * that attaches to an account (adoption) or is rebound to another connection
 * (sync) therefore has no subscription on the platform, even though the
 * integration's "Enable triggers" switch is on locally. Whoever changes a
 * row's `connection_id` marks it owed ({@see mark_owed()}); {@see
 * restore_owed()} then asks the row's integration to register, through the
 * filter `automator_platform_trigger_subscription_restore`:
 *
 *     add_filter( 'automator_platform_trigger_subscription_restore', function ( $outcome, Connection $connection ) {
 *         // Not mine, or my triggers are off: leave the outcome alone / 'not_needed'.
 *         // Registered: 'registered'. Could not: 'failed'.
 *     }, 10, 2 );
 *
 * The flag lives on the row, so it survives requests and is cleared only by
 * an answer that settles it — registered, not needed, or no integration that
 * subscribes (the default). A failure, an exception or an answer this class
 * does not know leaves it owed, and the next sync asks again. Asking happens
 * once per change: a settled row is never asked again until its
 * `connection_id` changes again.
 *
 * The integration may record what it registered on the row while it answers
 * (Facebook Lead Ads keeps the subscription it made there), so the flag is
 * cleared from the row as it is after the answer, not as it was read. An
 * answer settles only the connection it was asked about: a row given another
 * connection meanwhile stays owed, and a row removed meanwhile owes nothing.
 *
 * @since 8.0.0
 */
final class Trigger_Subscriptions {

	public const OUTCOME_REGISTERED     = 'registered';
	public const OUTCOME_NOT_NEEDED     = 'not_needed';
	public const OUTCOME_NOT_APPLICABLE = 'not_applicable';
	public const OUTCOME_FAILED         = 'failed';

	/**
	 * The metadata key that marks a row owed.
	 */
	private const OWED = 'trigger_subscription_owed';

	/**
	 * Answers that settle the debt.
	 */
	private const SETTLED = array( self::OUTCOME_REGISTERED, self::OUTCOME_NOT_NEEDED, self::OUTCOME_NOT_APPLICABLE );

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @param Connection_Registry $registry Local uap_connections store.
	 */
	public function __construct( Connection_Registry $registry ) {

		$this->registry = $registry;
	}

	/**
	 * Mark a row's metadata owed a subscription. For callers that are about to
	 * write a row with a new `connection_id`.
	 *
	 * @param array<string, mixed> $metadata The row's metadata.
	 *
	 * @return array<string, mixed>
	 */
	public static function mark_owed( array $metadata ): array {

		$metadata[ self::OWED ] = true;

		return $metadata;
	}

	/**
	 * @param Connection $connection A row.
	 *
	 * @return bool Whether the row is owed a subscription.
	 */
	public static function is_owed( Connection $connection ): bool {

		return ! empty( $connection->metadata()[ self::OWED ] );
	}

	/**
	 * Ask the integration of every active row owed a subscription to
	 * register it.
	 *
	 * @return int How many rows are still owed afterwards.
	 *
	 * @throws Registry_Read_Failed_Exception When the rows cannot be read.
	 */
	public function restore_owed(): int {

		$still_owed = 0;

		foreach ( $this->registry->list_by_status( Connection::STATUS_ACTIVE ) as $connection ) {

			if ( ! self::is_owed( $connection ) ) {
				continue;
			}

			if ( ! $this->restore( $connection ) ) {
				++$still_owed;
			}
		}

		return $still_owed;
	}

	/**
	 * @param Connection $connection An active row owed a subscription.
	 *
	 * @return bool Whether the debt is settled.
	 */
	private function restore( Connection $connection ): bool {

		// L247 — nothing is delivered to a local site: there is no subscription
		// to restore, and asking would only be refused. The debt is settled.
		if ( ( new Local_Site_Status() )->is_local() ) {
			return true;
		}

		try {
			$outcome = Dispatcher::filter( 'automator_platform_trigger_subscription_restore', self::OUTCOME_NOT_APPLICABLE, $connection );
		} catch ( Throwable $e ) {
			automator_log( 'Registering app triggers for the ' . $connection->integration() . ' connection failed and will be retried: ' . $e->getMessage(), 'Trigger_Subscriptions', false );
			return false;
		}

		if ( ! in_array( $outcome, self::SETTLED, true ) ) {
			return false;
		}

		$current = $this->registry->get_by_id( $connection->id() );

		if ( null === $current ) {
			return true;
		}

		if ( $current->connection_id() !== $connection->connection_id() ) {
			return false;
		}

		$metadata = $current->metadata();
		unset( $metadata[ self::OWED ] );

		return $this->registry->replace_metadata( $current->id(), $metadata );
	}
}
