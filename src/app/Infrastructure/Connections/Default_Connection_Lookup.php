<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Why an integration's default connection did, or did not, resolve —
 * {@see Connection_Registry::lookup_default()}.
 *
 * `get_default()` answers only "this connection, or nothing". A caller that
 * has to explain the nothing needs to know which nothing it is — the one
 * answer for every such caller: the `/v3` dispatch path
 * (`get_credentials()`), the customer's sentence for each state
 * (`Platform_Failure_Message::for_lookup()`), and the hint on an app's
 * settings page (`Switched_Off_Connection_Hint`).
 *
 *  - found         an active default row resolves; {@see active_connection()}.
 *  - none          the integration has no default account: it was never
 *                  connected here, its default account was removed, or no
 *                  account was ever chosen.
 *  - switched_off  the integration's default account is quarantined: this
 *                  site is a copy of another site and the row it inherited
 *                  is switched off here. Not broken, and not this site's to
 *                  reconnect blindly.
 *  - rechecking    the copy lifecycle is still running: the block that keeps
 *                  inherited rows unusable until the copy holds its own
 *                  secret is engaged, so nothing resolves for now.
 *  - inactive      the default account needs connecting again (re-authorised,
 *                  expired or revoked).
 *  - lookup_failed the database did not answer; nothing is known. Never read
 *                  as "no connection": the customer must not be sent to
 *                  connect an account they already have.
 *
 * Only the default account explains. A row that is not its integration's
 * default — active or not — reads as `none`: it is not the account recipes
 * use, and nothing makes it the default by itself. Which account recipes use
 * is the user's choice.
 *
 * The registry's answer is about the default row of either platform version;
 * a `/v3` reader reads it through {@see for_v3_dispatch()}.
 *
 * @since 8.0.0
 */
final class Default_Connection_Lookup {

	public const STATE_FOUND         = 'found';
	public const STATE_NONE          = 'none';
	public const STATE_SWITCHED_OFF  = 'switched_off';
	public const STATE_RECHECKING    = 'rechecking';
	public const STATE_INACTIVE      = 'inactive';
	public const STATE_LOOKUP_FAILED = 'lookup_failed';

	/**
	 * No account is the default, but the app has active `/v3` accounts to
	 * choose from. Nothing chooses for the admin among several. Whether they
	 * are asked to choose, or to connect as before, is
	 * {@see Multiple_Connections_Feature}'s to say (off in 8.0).
	 *
	 * @since 8.0.0
	 */
	public const STATE_UNCHOSEN = 'unchosen';

	/**
	 * @var string
	 */
	private $state;

	/**
	 * @var Connection|null
	 */
	private $connection;

	/**
	 * @param string          $state      One of the STATE_* constants.
	 * @param Connection|null $connection The row that resolved or explains the state.
	 */
	private function __construct( string $state, ?Connection $connection ) {

		$this->state      = $state;
		$this->connection = $connection;
	}

	/**
	 * @param Connection $connection The active default.
	 *
	 * @return self
	 */
	public static function found( Connection $connection ): self {

		return new self( self::STATE_FOUND, $connection );
	}

	/**
	 * @return self
	 */
	public static function none(): self {

		return new self( self::STATE_NONE, null );
	}

	/**
	 * @since 8.0.0
	 *
	 * @return self
	 */
	public static function unchosen(): self {

		return new self( self::STATE_UNCHOSEN, null );
	}

	/**
	 * @param Connection $connection The default account, quarantined.
	 *
	 * @return self
	 */
	public static function switched_off( Connection $connection ): self {

		return new self( self::STATE_SWITCHED_OFF, $connection );
	}

	/**
	 * @return self
	 */
	public static function rechecking(): self {

		return new self( self::STATE_RECHECKING, null );
	}

	/**
	 * @param Connection $connection The default account, which needs connecting again.
	 *
	 * @return self
	 */
	public static function inactive( Connection $connection ): self {

		return new self( self::STATE_INACTIVE, $connection );
	}

	/**
	 * @return self
	 */
	public static function lookup_failed(): self {

		return new self( self::STATE_LOOKUP_FAILED, null );
	}

	/**
	 * The lookup as a `/v3` request reads it. A pre-lift legacy (`/v2`)
	 * default — active or not — cannot dispatch to a `/v3` endpoint and says
	 * nothing about a `/v3` connection, so it reads as `none`: the integration
	 * has no `/v3` connection, and the customer is offered Connect. Every other
	 * answer is unchanged.
	 *
	 * @since 8.0.0
	 *
	 * @return self
	 */
	public function for_v3_dispatch(): self {

		if ( null !== $this->connection && $this->connection->is_legacy() ) {
			return self::none();
		}

		return $this;
	}

	/**
	 * @return string One of the STATE_* constants.
	 */
	public function state(): string {

		return $this->state;
	}

	/**
	 * The row that resolved, or the row that explains why nothing did.
	 *
	 * @return Connection|null
	 */
	public function connection(): ?Connection {

		return $this->connection;
	}

	/**
	 * The connection to dispatch with — only when one resolved.
	 *
	 * @return Connection|null
	 */
	public function active_connection(): ?Connection {

		return $this->is_found() ? $this->connection : null;
	}

	/**
	 * @return bool
	 */
	public function is_found(): bool {

		return self::STATE_FOUND === $this->state;
	}

	/**
	 * @return bool
	 */
	public function is_switched_off(): bool {

		return self::STATE_SWITCHED_OFF === $this->state;
	}
}
