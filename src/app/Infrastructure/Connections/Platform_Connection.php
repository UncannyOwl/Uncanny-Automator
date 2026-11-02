<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * One of this site's connections as the platform holds it — an entry of
 * `GET /app/v1/connections` (staging-copies contract §3.4), the attachment
 * `POST /app/v1/connections/bind-existing` answers with (§3.3), or the
 * connection `POST /app/v1/connections/adopt` answers with (Facebook Lead Ads
 * signed delivery design, §8).
 *
 * The platform's record is the truth the local `uap_connections` row is
 * repaired from ({@see Connection_Sync}). Only an active connection carries
 * a `connection_signature`, and a signature is never permanent: whenever one
 * is offered it replaces the stored one.
 *
 * @since 8.0.0
 */
final class Platform_Connection {

	public const STATUS_ACTIVE = 'active';

	/**
	 * How this site got the connection: by signing in at the vendor, by
	 * entering an API key here, or by attaching to an account another site
	 * of the same account put on offer.
	 */
	public const ORIGIN_OAUTH   = 'oauth';
	public const ORIGIN_API_KEY = 'api_key';
	public const ORIGIN_SHARED  = 'shared';

	/**
	 * @var string
	 */
	private $connection_id;

	/**
	 * @var string
	 */
	private $integration;

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var string
	 */
	private $identity_id;

	/**
	 * @var string
	 */
	private $external_user_label;

	/**
	 * @var string
	 */
	private $origin;

	/**
	 * @var bool
	 */
	private $shareable;

	/**
	 * @var string
	 */
	private $connection_signature;

	/**
	 * @param string $connection_id        The platform connection id.
	 * @param string $integration          The integration slug, lowercase.
	 * @param string $label                The platform's label.
	 * @param string $status               The platform's status.
	 * @param string $identity_id          The account, or '' when not given.
	 * @param string $external_user_label  The account as the vendor names it.
	 * @param string $origin               One of the ORIGIN_* constants, or ''.
	 * @param bool   $shareable            The per-connection sharing flag.
	 * @param string $connection_signature The current signature, '' when none.
	 */
	private function __construct(
		string $connection_id,
		string $integration,
		string $label,
		string $status,
		string $identity_id,
		string $external_user_label,
		string $origin,
		bool $shareable,
		string $connection_signature
	) {
		$this->connection_id        = $connection_id;
		$this->integration          = $integration;
		$this->label                = $label;
		$this->status               = $status;
		$this->identity_id          = $identity_id;
		$this->external_user_label  = $external_user_label;
		$this->origin               = $origin;
		$this->shareable            = $shareable;
		$this->connection_signature = $connection_signature;
	}

	/**
	 * Read one list entry. An entry that does not say which connection, which
	 * integration and which status it is cannot be matched to anything, so it
	 * is not a connection at all.
	 *
	 * @param mixed $entry One element of the list's `data`.
	 *
	 * @return self|null Null for an entry that cannot be identified.
	 */
	public static function from_list_entry( $entry ): ?self {

		if ( ! is_array( $entry ) ) {
			return null;
		}

		$connection_id = self::text( $entry, 'connection_id' );
		$integration   = strtolower( self::text( $entry, 'integration' ) );
		$status        = self::text( $entry, 'status' );

		if ( '' === $connection_id || '' === $integration || '' === $status ) {
			return null;
		}

		return new self(
			$connection_id,
			$integration,
			self::text( $entry, 'label' ),
			$status,
			self::text( $entry, 'identity_id' ),
			self::text( $entry, 'external_user_label' ),
			self::text( $entry, 'origin' ),
			isset( $entry['shareable'] ) && true === $entry['shareable'],
			self::STATUS_ACTIVE === $status ? self::text( $entry, 'connection_signature' ) : ''
		);
	}

	/**
	 * Read a bind-existing attachment (§3.3). It is this site's own, active,
	 * attached connection; without an id and a signature it is not usable.
	 *
	 * @param string               $integration The integration the attachment was asked for.
	 * @param array<string, mixed> $data        The answer's `data`.
	 *
	 * @return self|null Null when the attachment lacks its id or signature.
	 */
	public static function from_attachment( string $integration, array $data ): ?self {

		return self::from_answer( $integration, $data, self::ORIGIN_SHARED );
	}

	/**
	 * Read an adoption (`POST /app/v1/connections/adopt`): this site's own,
	 * active connection to the account whose credential it handed over. It
	 * was not attached from another site, and how the platform classifies its
	 * origin is the list's to say, so the origin is left unknown. Without an
	 * id and a signature it is not usable.
	 *
	 * @since 8.0.0
	 *
	 * @param string               $integration The integration the credential was adopted for.
	 * @param array<string, mixed> $data        The answer's `data`.
	 *
	 * @return self|null Null when the answer lacks its id or signature.
	 */
	public static function from_adoption( string $integration, array $data ): ?self {

		return self::from_answer( $integration, $data, '' );
	}

	/**
	 * @return string
	 */
	public function connection_id(): string {
		return $this->connection_id;
	}

	/**
	 * @return string
	 */
	public function integration(): string {
		return $this->integration;
	}

	/**
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * @return string '' when the platform gave none.
	 */
	public function identity_id(): string {
		return $this->identity_id;
	}

	/**
	 * @return string
	 */
	public function external_user_label(): string {
		return $this->external_user_label;
	}

	/**
	 * @return string One of the ORIGIN_* constants, or '' when the platform
	 *                does not know it (`null`, contract §9) — never this
	 *                site's own.
	 */
	public function origin(): string {
		return $this->origin;
	}

	/**
	 * @return bool
	 */
	public function is_shareable(): bool {
		return $this->shareable;
	}

	/**
	 * @return string '' unless the connection is active.
	 */
	public function connection_signature(): string {
		return $this->connection_signature;
	}

	/**
	 * @return bool
	 */
	public function is_active(): bool {
		return self::STATUS_ACTIVE === $this->status;
	}

	/**
	 * An active connection with a signature: a local row can work with it.
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return $this->is_active() && '' !== $this->connection_signature;
	}

	/**
	 * Whether this is the platform's connection for a local row: the same
	 * connection id, or the same account of the same integration.
	 *
	 * @param Connection $row A local row.
	 *
	 * @return bool
	 */
	public function concerns( Connection $row ): bool {

		if ( $this->connection_id === $row->connection_id() ) {
			return true;
		}

		return $this->integration === $row->integration()
			&& '' !== $this->identity_id
			&& $this->identity_id === (string) $row->identity_id();
	}

	/**
	 * An active connection a write answered with: bind-existing and adopt
	 * both answer `{connection_id, connection_signature, identity_id,
	 * external_user_label}`.
	 *
	 * @param string               $integration The integration the write was for.
	 * @param array<string, mixed> $data        The answer's `data`.
	 * @param string               $origin      One of the ORIGIN_* constants, or '' when unknown.
	 *
	 * @return self|null Null when the answer lacks its id or signature.
	 */
	private static function from_answer( string $integration, array $data, string $origin ): ?self {

		$connection_id = self::text( $data, 'connection_id' );
		$signature     = self::text( $data, 'connection_signature' );

		if ( '' === $connection_id || '' === $signature ) {
			return null;
		}

		return new self(
			$connection_id,
			strtolower( $integration ),
			'',
			self::STATUS_ACTIVE,
			self::text( $data, 'identity_id' ),
			self::text( $data, 'external_user_label' ),
			$origin,
			false,
			$signature
		);
	}

	/**
	 * @param array<mixed> $source The decoded object.
	 * @param string       $key    The field.
	 *
	 * @return string The field as a string, '' when absent or not scalar.
	 */
	private static function text( array $source, string $key ): string {

		return isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? trim( (string) $source[ $key ] ) : '';
	}
}
