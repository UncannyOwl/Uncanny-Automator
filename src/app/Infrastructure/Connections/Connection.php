<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Immutable value object representing an Automator Platform connection
 * stored on this site.
 *
 * Per MVP plan G.1 the registry holds rows for both `legacy_*` (v2) and
 * `v3` connections. The `platform_version` field tells callers which
 * dispatch path applies; `identity_id` holds the platform Identity FK
 * (nullable for legacy_* rows pre-promotion). The plugin NEVER stores
 * access tokens — those live on the platform Identity row.
 *
 * `connection_signature` (Contract G) is the platform-issued static auth
 * token the plugin echoes verbatim on every /v3/* request body. Computed
 * platform-side at OAuth-callback time over `(envelope_version,
 * connection_id, site_id, license_id)` with the platform's private
 * signing key (`vault.platform_signing_keys`). Plugin treats it as
 * opaque — never mints, never verifies. Nullable for `legacy_*` rows
 * which haven't been promoted to /v3 yet.
 *
 * `metadata` holds non-secret display / scope info (workspace_id,
 * team_name, account email, required/granted scopes for reauth tracking).
 * No credential material lives here.
 *
 * @since 8.0.0
 */
final class Connection {

	public const STATUS_ACTIVE          = 'active';
	public const STATUS_REQUIRES_REAUTH = 'requires_reauth';
	public const STATUS_EXPIRED         = 'expired';
	public const STATUS_REVOKED         = 'revoked';

	/**
	 * A connection this site inherited from the site it was COPIED from
	 * (cloned WordPress DB) and that the platform did not bind to the fresh
	 * copy. Deliberately distinct from `revoked`: `revoked` means the remote
	 * credential is no longer valid, but a copied connection may still be
	 * perfectly valid for the ORIGINAL site — so `revoked` would give a
	 * misleading UI + the wrong recovery. Quarantined rows are never eligible
	 * as defaults or dispatch credentials; reconnect binds a NEW connection.
	 */
	public const STATUS_QUARANTINED = 'quarantined';

	/**
	 * Metadata flag on an `expired` row: the platform no longer holds this
	 * connection. It refused a call made with the row, and its list of this
	 * site's connections names neither the connection nor an active one for
	 * the same account (flow spec §9.3: a connection missing from the list is
	 * gone, and nothing brings it back). The customer connects the app again
	 * ({@see Connection_Registry::mark_not_on_platform()}).
	 *
	 * @since 8.0.0
	 */
	public const METADATA_NOT_ON_PLATFORM = 'not_on_platform';

	public const PLATFORM_VERSION_V2 = 'v2';
	public const PLATFORM_VERSION_V3 = 'v3';

	/** @var int */
	private $id;

	/** @var string */
	private $connection_id;

	/** @var string|null */
	private $connection_signature;

	/** @var string|null */
	private $identity_id;

	/** @var string */
	private $integration;

	/** @var string|null */
	private $label;

	/** @var string */
	private $platform_version;

	/** @var array<string, mixed> */
	private $metadata;

	/** @var string */
	private $status;

	/** @var bool */
	private $is_default;

	/** @var string */
	private $created_at;

	/** @var string */
	private $updated_at;

	/** @var string|null */
	private $last_used_at;

	/**
	 * @param int                  $id
	 * @param string               $connection_id
	 * @param string|null          $connection_signature Platform-issued opaque auth token (Contract G).
	 * @param string|null          $identity_id
	 * @param string               $integration
	 * @param string|null          $label
	 * @param string               $platform_version
	 * @param array<string, mixed> $metadata
	 * @param string               $status
	 * @param bool                 $is_default
	 * @param string               $created_at
	 * @param string               $updated_at
	 * @param string|null          $last_used_at
	 */
	public function __construct(
		int $id,
		string $connection_id,
		?string $connection_signature,
		?string $identity_id,
		string $integration,
		?string $label,
		string $platform_version,
		array $metadata,
		string $status,
		bool $is_default,
		string $created_at,
		string $updated_at,
		?string $last_used_at
	) {
		$this->id                   = $id;
		$this->connection_id        = $connection_id;
		$this->connection_signature = $connection_signature;
		$this->identity_id          = $identity_id;
		$this->integration          = $integration;
		$this->label                = $label;
		$this->platform_version     = $platform_version;
		$this->metadata             = $metadata;
		$this->status               = $status;
		$this->is_default           = $is_default;
		$this->created_at           = $created_at;
		$this->updated_at           = $updated_at;
		$this->last_used_at         = $last_used_at;
	}
	/**
	 * Id.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}
	/**
	 * Connection id.
	 *
	 * @return string
	 */
	public function connection_id(): string {
		return $this->connection_id;
	}
	/**
	 * Connection signature.
	 *
	 * @return string|null
	 */
	public function connection_signature(): ?string {
		return $this->connection_signature;
	}
	/**
	 * Identity id.
	 *
	 * @return string|null
	 */
	public function identity_id(): ?string {
		return $this->identity_id;
	}
	/**
	 * Integration.
	 *
	 * @return string
	 */
	public function integration(): string {
		return $this->integration;
	}
	/**
	 * Label.
	 *
	 * @return string|null
	 */
	public function label(): ?string {
		return $this->label;
	}
	/**
	 * Platform version.
	 *
	 * @return string
	 */
	public function platform_version(): string {
		return $this->platform_version;
	}
	/**
	 * Is legacy.
	 *
	 * @return bool
	 */
	public function is_legacy(): bool {
		return self::PLATFORM_VERSION_V2 === $this->platform_version;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function metadata(): array {
		return $this->metadata;
	}
	/**
	 * Status.
	 *
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}
	/**
	 * Is default.
	 *
	 * @return bool
	 */
	public function is_default(): bool {
		return $this->is_default;
	}
	/**
	 * Is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return self::STATUS_ACTIVE === $this->status;
	}
	/**
	 * Requires reauth.
	 *
	 * @return bool
	 */
	public function requires_reauth(): bool {
		return self::STATUS_REQUIRES_REAUTH === $this->status;
	}
	/**
	 * Is expired.
	 *
	 * @return bool
	 */
	public function is_expired(): bool {
		return self::STATUS_EXPIRED === $this->status;
	}
	/**
	 * Is revoked.
	 *
	 * @return bool
	 */
	public function is_revoked(): bool {
		return self::STATUS_REVOKED === $this->status;
	}
	/**
	 * Whether the platform no longer holds this connection
	 * ({@see METADATA_NOT_ON_PLATFORM}).
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_not_on_platform(): bool {
		return $this->is_expired() && true === ( $this->metadata[ self::METADATA_NOT_ON_PLATFORM ] ?? false );
	}
	/**
	 * Is quarantined — inherited from the source site of a copy and neutralized
	 * (see {@see STATUS_QUARANTINED}).
	 *
	 * @return bool
	 */
	public function is_quarantined(): bool {
		return self::STATUS_QUARANTINED === $this->status;
	}
	/**
	 * Created at.
	 *
	 * @return string
	 */
	public function created_at(): string {
		return $this->created_at;
	}
	/**
	 * Updated at.
	 *
	 * @return string
	 */
	public function updated_at(): string {
		return $this->updated_at;
	}
	/**
	 * Last used at.
	 *
	 * @return string|null
	 */
	public function last_used_at(): ?string {
		return $this->last_used_at;
	}
}
