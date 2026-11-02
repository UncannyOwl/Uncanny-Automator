<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

/**
 * One background app-action job: the platform job the action's write was accepted as,
 * the run and row it belongs to, and what the action needs back when the job finishes.
 */
final class Background_Job {

	/**
	 * The job has been accepted and nothing has reported on it yet.
	 *
	 * @var string
	 */
	const STATUS_OPEN = 'open';

	/**
	 * Claimed by a completion path that has not finished yet.
	 *
	 * @var string
	 */
	const STATUS_PROCESSING = 'processing';

	/**
	 * The platform reported success.
	 *
	 * @var string
	 */
	const STATUS_COMPLETED = 'completed';

	/**
	 * The platform reported failure.
	 *
	 * @var string
	 */
	const STATUS_FAILED = 'failed';

	/**
	 * The plugin gave up waiting: the poll found no job or the cap elapsed.
	 *
	 * @var string
	 */
	const STATUS_LOST = 'lost';

	/**
	 * The outcome arrived through the platform's callback batch.
	 *
	 * @var string
	 */
	const SOURCE_CALLBACK = 'callback';

	/**
	 * The outcome came from the recovery poll.
	 *
	 * @var string
	 */
	const SOURCE_POLL = 'poll';

	/**
	 * The outcome came from the site's own retry of a site-originated job.
	 *
	 * @var string
	 */
	const SOURCE_SITE = 'site';

	/**
	 * The platform accepted the write as a job and reports its outcome.
	 *
	 * @var string
	 */
	const ORIGIN_PLATFORM = 'platform';

	/**
	 * The site minted the job to finish a write the platform could not finish in the request, and retries it
	 * itself; the platform never hears of the job id.
	 *
	 * @var string
	 */
	const ORIGIN_SITE = 'site';

	/**
	 * The five statuses a row may hold.
	 *
	 * @var string[]
	 */
	private const STATUSES = array( self::STATUS_OPEN, self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_LOST );

	/**
	 * Row id, 0 until stored.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * The platform's job id.
	 *
	 * @var string
	 */
	private string $job_id;

	/**
	 * The key the request carried.
	 *
	 * @var string
	 */
	private string $idempotency_key;

	/**
	 * The run.
	 *
	 * @var int
	 */
	private int $recipe_log_id;

	/**
	 * The recipe.
	 *
	 * @var int
	 */
	private int $recipe_id;

	/**
	 * The action post id.
	 *
	 * @var int
	 */
	private int $action_id;

	/**
	 * The action's row in uap_action_log.
	 *
	 * @var int
	 */
	private int $action_log_id;

	/**
	 * The user the run executes as.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * The action's integration code, for same-integration waits.
	 *
	 * @var string
	 */
	private string $integration_code;

	/**
	 * The action's parsed field values at dispatch.
	 *
	 * @var array<string, mixed>
	 */
	private array $parsed;

	/**
	 * Values the action remembered before the send.
	 *
	 * @var array<string, mixed>
	 */
	private array $remembered;

	/**
	 * One of the STATUS_* values.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * One of the SOURCE_* values, or empty while open.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * UTC `Y-m-d H:i:s` the job was accepted, empty until stored.
	 *
	 * @var string
	 */
	private string $dispatched_at;

	/**
	 * UTC `Y-m-d H:i:s` a completion path claimed the job, empty until claimed.
	 *
	 * @var string
	 */
	private string $claimed_at;

	/**
	 * How many times the poll released a claim on this job because the completion holding it died.
	 *
	 * @var int
	 */
	private int $redrives;

	/**
	 * UTC `Y-m-d H:i:s` the outcome was recorded, empty while open.
	 *
	 * @var string
	 */
	private string $finished_at;

	/**
	 * One of the ORIGIN_* values.
	 *
	 * @var string
	 */
	private string $origin;

	/**
	 * The connection a platform job's write was sent with; empty for a
	 * site-originated job, or a write sent without one.
	 *
	 * @var string
	 */
	private string $connection_id;

	/**
	 * Validate and freeze the job.
	 *
	 * @param array<string, mixed> $data job_id, idempotency_key, recipe_log_id, recipe_id, action_id, action_log_id, user_id,
	 *                                   integration_code, parsed, remembered; optional id, status, source, dispatched_at, claimed_at, redrives, finished_at,
	 *                                   origin (platform when absent), connection_id (none when absent).
	 *
	 * @throws \InvalidArgumentException When an identifier is missing or the status or the origin is unknown.
	 */
	public function __construct( array $data ) {
		$this->job_id = trim( (string) ( $data['job_id'] ?? '' ) );
		if ( '' === $this->job_id ) {
			throw new \InvalidArgumentException( 'A background job needs the platform job id.' );
		}
		foreach ( array( 'recipe_log_id', 'action_id', 'action_log_id' ) as $key ) {
			if ( (int) ( $data[ $key ] ?? 0 ) <= 0 ) {
				throw new \InvalidArgumentException( esc_html( sprintf( 'A background job needs a positive %s.', $key ) ) );
			}
		}
		$status = (string) ( $data['status'] ?? self::STATUS_OPEN );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Unknown background job status "%s".', $status ) ) );
		}

		$origin = (string) ( $data['origin'] ?? self::ORIGIN_PLATFORM );
		if ( ! in_array( $origin, array( self::ORIGIN_PLATFORM, self::ORIGIN_SITE ), true ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Unknown background job origin "%s".', $origin ) ) );
		}

		$this->id               = (int) ( $data['id'] ?? 0 );
		$this->idempotency_key  = (string) ( $data['idempotency_key'] ?? '' );
		$this->recipe_log_id    = (int) $data['recipe_log_id'];
		$this->recipe_id        = (int) ( $data['recipe_id'] ?? 0 );
		$this->action_id        = (int) $data['action_id'];
		$this->action_log_id    = (int) $data['action_log_id'];
		$this->user_id          = (int) ( $data['user_id'] ?? 0 );
		$this->integration_code = (string) ( $data['integration_code'] ?? '' );
		$this->parsed           = is_array( $data['parsed'] ?? null ) ? $data['parsed'] : array();
		$this->remembered       = is_array( $data['remembered'] ?? null ) ? $data['remembered'] : array();
		$this->status           = $status;
		$this->source           = (string) ( $data['source'] ?? '' );
		$this->dispatched_at    = (string) ( $data['dispatched_at'] ?? '' );
		$this->claimed_at       = (string) ( $data['claimed_at'] ?? '' );
		$this->redrives         = (int) ( $data['redrives'] ?? 0 );
		$this->finished_at      = (string) ( $data['finished_at'] ?? '' );
		$this->origin           = $origin;
		$this->connection_id    = (string) ( $data['connection_id'] ?? '' );
	}

	/**
	 * Rebuild a job from its uap_async_jobs row.
	 *
	 * @param array<string, mixed> $row The row, JSON columns still encoded.
	 *
	 * @return self
	 */
	public static function from_row( array $row ): self {
		$parsed     = json_decode( (string) ( $row['parsed'] ?? '' ), true );
		$remembered = json_decode( (string) ( $row['remembered'] ?? '' ), true );

		return new self(
			array(
				'id'               => (int) ( $row['ID'] ?? 0 ),
				'job_id'           => (string) ( $row['job_id'] ?? '' ),
				'idempotency_key'  => (string) ( $row['idempotency_key'] ?? '' ),
				'recipe_log_id'    => (int) ( $row['recipe_log_id'] ?? 0 ),
				'recipe_id'        => (int) ( $row['recipe_id'] ?? 0 ),
				'action_id'        => (int) ( $row['action_id'] ?? 0 ),
				'action_log_id'    => (int) ( $row['action_log_id'] ?? 0 ),
				'user_id'          => (int) ( $row['user_id'] ?? 0 ),
				'integration_code' => (string) ( $row['integration_code'] ?? '' ),
				'parsed'           => is_array( $parsed ) ? $parsed : array(),
				'remembered'       => is_array( $remembered ) ? $remembered : array(),
				'status'           => (string) ( $row['status'] ?? self::STATUS_OPEN ),
				'source'           => (string) ( $row['source'] ?? '' ),
				'dispatched_at'    => (string) ( $row['dispatched_at'] ?? '' ),
				'claimed_at'       => (string) ( $row['claimed_at'] ?? '' ),
				'redrives'         => (int) ( $row['redrives'] ?? 0 ),
				'finished_at'      => (string) ( $row['finished_at'] ?? '' ),
				'origin'           => (string) ( $row['origin'] ?? self::ORIGIN_PLATFORM ),
				'connection_id'    => (string) ( $row['connection_id'] ?? '' ),
			)
		);
	}

	/**
	 * The columns the store writes; JSON columns encoded.
	 *
	 * @return array<string, mixed>
	 */
	public function to_row(): array {
		return array(
			'job_id'           => $this->job_id,
			'idempotency_key'  => $this->idempotency_key,
			'recipe_log_id'    => $this->recipe_log_id,
			'recipe_id'        => $this->recipe_id,
			'action_id'        => $this->action_id,
			'action_log_id'    => $this->action_log_id,
			'user_id'          => $this->user_id,
			'integration_code' => $this->integration_code,
			'parsed'           => (string) wp_json_encode( $this->parsed ),
			'remembered'       => (string) wp_json_encode( $this->remembered ),
			'status'           => $this->status,
			'source'           => $this->source,
			'dispatched_at'    => $this->dispatched_at,
			'claimed_at'       => $this->claimed_at,
			'redrives'         => $this->redrives,
			'finished_at'      => $this->finished_at,
			'origin'           => $this->origin,
			'connection_id'    => $this->connection_id,
		);
	}

	/**
	 * The same job with other remembered values.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, mixed> $remembered The values; must survive JSON.
	 *
	 * @return self
	 */
	public function with_remembered( array $remembered ): self {
		return self::from_row(
			array_merge(
				$this->to_row(),
				array(
					'ID'         => $this->id,
					'remembered' => (string) wp_json_encode( $remembered ),
				)
			)
		);
	}

	/**
	 * Seconds since dispatch at the given instant.
	 *
	 * @param int $now Unix timestamp.
	 *
	 * @return int
	 */
	public function age_seconds( int $now ): int {
		$dispatched = strtotime( $this->dispatched_at . ' UTC' );

		return false === $dispatched ? 0 : max( 0, $now - $dispatched );
	}

	/**
	 * Row id, 0 until stored.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * The platform's job id.
	 *
	 * @return string
	 */
	public function job_id(): string {
		return $this->job_id;
	}

	/**
	 * The key the request carried.
	 *
	 * @return string
	 */
	public function idempotency_key(): string {
		return $this->idempotency_key;
	}

	/**
	 * The run.
	 *
	 * @return int
	 */
	public function recipe_log_id(): int {
		return $this->recipe_log_id;
	}

	/**
	 * The recipe.
	 *
	 * @return int
	 */
	public function recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * The action post id.
	 *
	 * @return int
	 */
	public function action_id(): int {
		return $this->action_id;
	}

	/**
	 * The action's row.
	 *
	 * @return int
	 */
	public function action_log_id(): int {
		return $this->action_log_id;
	}

	/**
	 * The user the run executes as.
	 *
	 * @return int
	 */
	public function user_id(): int {
		return $this->user_id;
	}

	/**
	 * The action's integration code.
	 *
	 * @return string
	 */
	public function integration_code(): string {
		return $this->integration_code;
	}

	/**
	 * The parsed field values at dispatch.
	 *
	 * @return array<string, mixed>
	 */
	public function parsed(): array {
		return $this->parsed;
	}

	/**
	 * What the action remembered before the send.
	 *
	 * @return array<string, mixed>
	 */
	public function remembered(): array {
		return $this->remembered;
	}

	/**
	 * One of the STATUS_* values.
	 *
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * One of the SOURCE_* values, or empty while open.
	 *
	 * @return string
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * UTC `Y-m-d H:i:s` the job was accepted.
	 *
	 * @return string
	 */
	public function dispatched_at(): string {
		return $this->dispatched_at;
	}

	/**
	 * UTC `Y-m-d H:i:s` a completion path claimed the job, empty until claimed.
	 *
	 * @return string
	 */
	public function claimed_at(): string {
		return $this->claimed_at;
	}

	/**
	 * How many times the poll released a claim on this job because the completion holding it died.
	 *
	 * @return int
	 */
	public function redrives(): int {
		return $this->redrives;
	}

	/**
	 * UTC `Y-m-d H:i:s` the outcome was recorded, empty while open.
	 *
	 * @return string
	 */
	public function finished_at(): string {
		return $this->finished_at;
	}

	/**
	 * One of the ORIGIN_* values.
	 *
	 * @return string
	 */
	public function origin(): string {
		return $this->origin;
	}

	/**
	 * The connection a platform job's write was sent with; '' when none was
	 * recorded (a site-originated job, or a write sent without a connection).
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function connection_id(): string {
		return $this->connection_id;
	}

	/**
	 * Whether the site retries this job itself instead of waiting on the platform.
	 *
	 * @return bool
	 */
	public function is_site_originated(): bool {
		return self::ORIGIN_SITE === $this->origin;
	}
}
