<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * What a local site's restart disconnected, and which apps still need
 * connecting again (L247).
 *
 * A local site that registered again without proving its old secret
 * (`data.restarted: true`, platform answers contract 4.8) got a new secret, and
 * the platform disconnected every connection at the site's address — inside
 * the usual grace window: connecting the same account again restores the same
 * connection. Connection sync never takes a row down because the platform's
 * list shows its connection inactive (a passing error must never cost a
 * connection), so the restart itself — a definite answer about this site — is
 * what takes the site's own connections down: on
 * `automator_platform_site_restarted` every active `/v3` row is marked expired
 * (its steps then say "connect again", never "no connection"), and its app is
 * recorded. A `/v2` row is not a platform connection at this address and is
 * left alone.
 *
 * {@see self::awaiting()} answers the apps still to connect: an app with an
 * active row again (the admin connected it) drops off, and the list is
 * forgotten once it is empty. Shown by Site_Restart_Reconnect_Notice.
 *
 * @since 8.0.0
 */
final class Restart_Disconnections {

	/**
	 * The integrations whose connections the restart took down.
	 */
	public const OPTION = 'automator_platform_restart_disconnected';

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
	 * @return void
	 */
	public function register(): void {
		add_action( 'automator_platform_site_restarted', array( $this, 'record' ) );
	}

	/**
	 * Take the site's own connections down and record their apps.
	 *
	 * @return void
	 */
	public function record(): void {

		$apps = $this->recorded();

		foreach ( $this->registry->list_by_status( Connection::STATUS_ACTIVE ) as $connection ) {

			if ( $connection->is_legacy() ) {
				continue;
			}

			$this->registry->mark_expired( $connection->id() );
			$apps[] = $connection->integration();
		}

		$apps = array_values( array_unique( $apps ) );

		if ( array() !== $apps ) {
			automator_update_option( self::OPTION, $apps, false );
		}
	}

	/**
	 * The apps the restart disconnected that have no active connection yet —
	 * the list kept in step: apps connected again drop off, an empty list is
	 * forgotten.
	 *
	 * @return array<int, string> Integration slugs.
	 */
	public function awaiting(): array {

		$recorded = $this->recorded();

		if ( array() === $recorded ) {
			return array();
		}

		$connected = array();
		foreach ( $this->registry->list_by_status( Connection::STATUS_ACTIVE ) as $connection ) {
			$connected[ $connection->integration() ] = true;
		}

		$awaiting = array_values( array_filter( $recorded, static fn ( string $app ): bool => ! isset( $connected[ $app ] ) ) );

		if ( array() === $awaiting ) {
			automator_delete_option( self::OPTION );
		} elseif ( $awaiting !== $recorded ) {
			automator_update_option( self::OPTION, $awaiting, false );
		}

		return $awaiting;
	}

	/**
	 * @return array<int, string>
	 */
	private function recorded(): array {

		$stored = automator_get_option( self::OPTION, array() );

		return is_array( $stored ) ? array_values( array_filter( array_map( 'strval', $stored ) ) ) : array();
	}
}
