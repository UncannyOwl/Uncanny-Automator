<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;

/**
 * A staging copy attaches to the live site's app accounts — staging-copies
 * handoff R3.
 *
 * Runs only when all three hold: the admin answered "This is a staging copy —
 * use the live site's app connections here" ({@see Site_Copy_Record}), the
 * copy lifecycle is complete (the inherited rows are quarantined and the block
 * lifted), and the registration is settled — the copy holds its own secret.
 *
 * For every v3 row quarantined as `site_copy` that names its account
 * (`identity_id`), it asks the platform to attach this site to that account
 * (`POST /app/v1/connections/bind-existing`) and acts on the answer
 * ({@see Bind_Existing_Result}):
 *
 *  - attached     the row is rewritten in place — the copy's own
 *                 `connection_id` and signature, active, its quarantine
 *                 markers gone — so its id, label and metadata, and anything
 *                 pointing at it, stay. Whether it is its integration's
 *                 default does not change: the flag stayed on the row while
 *                 it was switched off, so recipes act on the same account as
 *                 they did, from this one write on — adoption never chooses
 *                 an account ({@see Connection_Registry::quarantine_all()}).
 *                 A new connection has no trigger subscription; it is owed one
 *                 (R6, {@see Trigger_Subscriptions}), which the sync that
 *                 follows registers — the sync is the one place that does, and
 *                 stays owed until it has.
 *  - not offered, account gone, refused
 *                 the row stays switched off and remembers why
 *                 (`adoption_refusal`), for the report.
 *  - unavailable  nothing changes; the adoption stays owed and is retried
 *                 ({@see Owed_Work}). Never counted as an answer.
 *
 * Resumable and repeatable: a run attaches only what is still quarantined,
 * and attaching an account the copy already holds answers the same connection
 * and changes nothing. A Retry runs it again, refused rows included. Only the
 * account is ever sent — never one of the live site's `connection_id`s. An
 * attachment is followed by a connection sync.
 *
 * @since 8.0.0
 */
final class Connection_Adoption {

	/**
	 * WP-Cron hook that retries an owed adoption.
	 */
	public const CRON_HOOK = 'automator_platform_connection_adoption';

	public const REASON_NOT_OFFERED     = 'not_offered';
	public const REASON_ACCOUNT_GONE    = 'account_gone';
	public const REASON_REFUSED         = 'refused';
	public const REASON_UNKNOWN_ACCOUNT = 'unknown_account';
	public const REASON_NOT_ANSWERED    = 'not_answered';

	/**
	 * Why a row stays off, by the platform's answer.
	 */
	private const REFUSALS = array(
		Bind_Existing_Result::OUTCOME_NOT_OFFERED  => self::REASON_NOT_OFFERED,
		Bind_Existing_Result::OUTCOME_ACCOUNT_GONE => self::REASON_ACCOUNT_GONE,
		Bind_Existing_Result::OUTCOME_INVALID      => self::REASON_REFUSED,
		Bind_Existing_Result::OUTCOME_REFUSED      => self::REASON_REFUSED,
	);

	/**
	 * Metadata keys a row sheds when it is attached.
	 */
	private const SWITCHED_OFF_KEYS = array( 'quarantine_reason', 'adoption_refusal' );

	/**
	 * @var Platform_Connections_Api
	 */
	private $api;

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * @var Connection_Sync
	 */
	private $sync;

	/**
	 * @var Site_Copy_Record
	 */
	private $record;

	/**
	 * @var Owed_Work
	 */
	private $owed;

	/**
	 * @param Platform_Connections_Api   $api          The platform's connection routes.
	 * @param Connection_Registry        $registry     Local uap_connections store.
	 * @param Platform_Site_Registration $registration Says whether the copy holds its own secret.
	 * @param Connection_Sync            $sync         Runs after an attachment.
	 * @param Site_Copy_Record|null      $record       The admin's answer.
	 * @param Owed_Work|null             $owed         Keeps an unfinished adoption owed.
	 */
	public function __construct(
		Platform_Connections_Api $api,
		Connection_Registry $registry,
		Platform_Site_Registration $registration,
		Connection_Sync $sync,
		?Site_Copy_Record $record = null,
		?Owed_Work $owed = null
	) {
		$this->api          = $api;
		$this->registry     = $registry;
		$this->registration = $registration;
		$this->sync         = $sync;
		$this->record       = $record ?? new Site_Copy_Record();
		$this->owed         = $owed ?? new Owed_Work( 'connection_adoption', self::CRON_HOOK );
	}

	/**
	 * Follow the end of the copy lifecycle, and the retry event.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action( 'automator_platform_site_copy_completed', array( $this, 'run_if_wanted' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_if_wanted' ) );
	}

	/**
	 * Run when the admin chose adoption; otherwise there is nothing to do and
	 * nothing owed.
	 *
	 * @return void
	 */
	public function run_if_wanted(): void {

		if ( $this->record->wants_adoption() ) {
			$this->run();
			return;
		}

		$this->owed->settle();
	}

	/**
	 * Attach every switched-off inherited row that can be attached.
	 *
	 * @return Adoption_Report Where the adoption stands afterwards.
	 */
	public function run(): Adoption_Report {

		if ( ! $this->record->wants_adoption() ) {
			return $this->report();
		}

		if ( ! $this->is_ready() ) {
			$this->owed->failed();
			return $this->report();
		}

		try {
			$rows = $this->inherited_rows();
		} catch ( Registry_Read_Failed_Exception $e ) {
			$this->owed->failed();
			automator_log( 'Adoption could not read the connections this copy inherited; it will be retried: ' . $e->getMessage(), 'Connection_Adoption', false );
			return $this->report();
		}

		$attached   = 0;
		$unanswered = 0;

		foreach ( $rows as $row ) {

			if ( null === $row->identity_id() ) {
				continue;
			}

			$outcome = $this->adopt( $row );

			$attached   += Bind_Existing_Result::OUTCOME_ATTACHED === $outcome ? 1 : 0;
			$unanswered += Bind_Existing_Result::OUTCOME_UNAVAILABLE === $outcome ? 1 : 0;
		}

		if ( 0 < $attached ) {
			$this->sync->sync();
		}

		$this->finish( 0 === $unanswered );

		return $this->report();
	}

	/**
	 * Where the adoption stands, read from the rows.
	 *
	 * @return Adoption_Report
	 */
	public function report(): Adoption_Report {

		$attached     = array();
		$not_attached = array();

		try {
			foreach ( $this->registry->list_by_status( Connection::STATUS_ACTIVE ) as $row ) {
				if ( ! empty( $row->metadata()['adopted'] ) ) {
					$attached[] = $row;
				}
			}

			foreach ( $this->inherited_rows() as $row ) {
				$not_attached[] = array(
					'connection' => $row,
					'reason'     => $this->reason_off( $row ),
				);
			}
		} catch ( Registry_Read_Failed_Exception $e ) {
			automator_log( 'The adoption report could not read the connections: ' . $e->getMessage(), 'Connection_Adoption', false );
		}

		return new Adoption_Report( $this->record->wants_adoption(), $this->owed->is_owed(), $attached, $not_attached );
	}

	/**
	 * Whether this site holds rows it inherited as a copy and switched off.
	 *
	 * @since 8.0.0
	 *
	 * @return bool False also when the rows cannot be read.
	 */
	public function has_inherited_rows(): bool {

		try {
			return array() !== $this->inherited_rows();
		} catch ( Registry_Read_Failed_Exception $e ) {
			return false;
		}
	}

	/**
	 * Whether adoption can run now: the copy lifecycle is complete and the
	 * copy holds its own, accepted secret.
	 *
	 * @return bool
	 */
	public function is_ready(): bool {

		return ! ( new Copy_Quarantine_State() )->is_blocking() && $this->registration->is_settled();
	}

	/**
	 * Ask the platform to attach this site to one row's account, and act on
	 * the answer.
	 *
	 * @param Connection $row A switched-off inherited row with an account.
	 *
	 * @return string The Bind_Existing_Result outcome that applies, unavailable
	 *                when the attachment could not be written.
	 */
	private function adopt( Connection $row ): string {

		$result = $this->api->bind_existing( $row->integration(), (string) $row->identity_id() );

		if ( Bind_Existing_Result::OUTCOME_ATTACHED === $result->outcome() ) {
			return $this->attach( $row, $result->connection() )
				? Bind_Existing_Result::OUTCOME_ATTACHED
				: Bind_Existing_Result::OUTCOME_UNAVAILABLE;
		}

		if ( $result->is_answer() ) {
			$this->remember_refusal( $row, self::REFUSALS[ $result->outcome() ] );
		}

		return $result->outcome();
	}

	/**
	 * Rewrite the row in place as this copy's own connection to the account.
	 *
	 * @param Connection               $row        The switched-off row.
	 * @param Platform_Connection|null $connection The attachment.
	 *
	 * @return bool Whether the row was written.
	 */
	private function attach( Connection $row, ?Platform_Connection $connection ): bool {

		if ( null === $connection ) {
			return false;
		}

		$metadata = $row->metadata();

		foreach ( self::SWITCHED_OFF_KEYS as $key ) {
			unset( $metadata[ $key ] );
		}

		$metadata['origin']  = Platform_Connection::ORIGIN_SHARED;
		$metadata['adopted'] = true;
		if ( '' !== $connection->external_user_label() ) {
			$metadata['external_user_label'] = $connection->external_user_label();
		}

		// The live site's connection was never this copy's: a new one has no
		// trigger subscription. Re-attaching what the site already held
		// answers the same connection, whose subscription stands.
		if ( $connection->connection_id() !== $row->connection_id() ) {
			$metadata = Trigger_Subscriptions::mark_owed( $metadata );
		}

		$identity_id = '' !== $connection->identity_id() ? $connection->identity_id() : (string) $row->identity_id();

		if ( ! $this->registry->bind( $row->id(), $connection->connection_id(), $connection->connection_signature(), $identity_id, Connection::STATUS_ACTIVE, $metadata ) ) {
			automator_log( 'Adoption could not write the attached ' . $row->integration() . ' connection; it will be retried.', 'Connection_Adoption', false );
			return false;
		}

		return true;
	}

	/**
	 * @param Connection $row    The switched-off row.
	 * @param string     $reason One of the REASON_* constants.
	 *
	 * @return void
	 */
	private function remember_refusal( Connection $row, string $reason ): void {

		$metadata = $row->metadata();
		if ( ( $metadata['adoption_refusal'] ?? '' ) === $reason ) {
			return;
		}

		$metadata['adoption_refusal'] = $reason;
		$this->registry->replace_metadata( $row->id(), $metadata );
	}

	/**
	 * @param Connection $row A switched-off inherited row.
	 *
	 * @return string Why it is still off.
	 */
	private function reason_off( Connection $row ): string {

		if ( null === $row->identity_id() ) {
			return self::REASON_UNKNOWN_ACCOUNT;
		}

		$reason = (string) ( $row->metadata()['adoption_refusal'] ?? '' );

		return '' !== $reason ? $reason : self::REASON_NOT_ANSWERED;
	}

	/**
	 * The v3 rows this copy inherited and switched off.
	 *
	 * @return array<int, Connection>
	 *
	 * @throws Registry_Read_Failed_Exception When the rows cannot be read.
	 */
	private function inherited_rows(): array {

		return array_values(
			array_filter(
				$this->registry->list_by_status( Connection::STATUS_QUARANTINED ),
				static function ( Connection $row ): bool {
					return ! $row->is_legacy() && 'site_copy' === ( $row->metadata()['quarantine_reason'] ?? '' );
				}
			)
		);
	}

	/**
	 * @param bool $complete Whether every row was answered.
	 *
	 * @return void
	 */
	private function finish( bool $complete ): void {

		if ( $complete ) {
			$this->owed->settle();
			return;
		}

		$this->owed->failed();
	}
}
