<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Domain;

/** Owns retryable and terminal token outcomes without consuming credentials. */
final class Token_Exchange_Policy {

	public const EXCHANGE_DISABLED       = 'exchange_disabled';
	public const EXCHANGE_REQUIRES_HTTPS = 'exchange_requires_https';
	public const TOKEN_RATE_LIMIT        = 'token_rate_limit';
	public const CANCEL_RATE_LIMIT       = 'cancel_rate_limit';
	public const CANCEL_SAVE_FAILED      = 'cancel_save_failed';
	public const REFRESH_INVALID         = 'refresh_invalid';
	public const CLIENT_BUSY             = 'client_busy';
	public const REFRESH_BUSY            = 'refresh_busy';
	public const REFRESH_REUSED          = 'refresh_reused';
	public const ACCESS_ISSUANCE_FAILED  = 'access_issuance_failed';
	public const USER_MISSING            = 'user_missing';

	/** A busy family has not consumed the token; invalid or reused tokens are terminal. */
	public static function rotation_failure( ?bool $rotated, bool $access_issued ): ?Token_Exchange_Failure {
		if ( null === $rotated ) {
			return self::failure( self::REFRESH_BUSY );
		}
		if ( ! $rotated ) {
			return self::failure( self::REFRESH_REUSED );
		}
		return $access_issued ? null : self::failure( self::ACCESS_ISSUANCE_FAILED );
	}

	/** Resolve one known failure while preserving the token endpoint's public contract. */
	public static function failure( string $reason ): Token_Exchange_Failure {
		$failures = array(
			self::EXCHANGE_DISABLED       => array( 'temporarily_unavailable', 'OAuth token exchange is disabled.', 503, 'UA-MCP-501' ),
			self::EXCHANGE_REQUIRES_HTTPS => array( 'temporarily_unavailable', 'OAuth token exchange requires HTTPS.', 503, 'UA-MCP-534' ),
			self::TOKEN_RATE_LIMIT        => array( 'slow_down', 'Too many token requests.', 429, 'UA-MCP-524' ),
			self::CANCEL_RATE_LIMIT       => array( 'slow_down', 'Too many cancellation requests.', 429, 'UA-MCP-527' ),
			self::CANCEL_SAVE_FAILED      => array( 'temporarily_unavailable', 'Cancellation could not be saved. Retry shortly.', 503, 'UA-MCP-530', array( 'Retry-After' => '2' ) ),
			self::REFRESH_INVALID         => array( 'invalid_grant', 'The refresh token is invalid.', 400, 'UA-MCP-508' ),
			self::CLIENT_BUSY             => array( 'temporarily_unavailable', 'Another operation for this client is in progress. Retry shortly.', 503, 'UA-MCP-533', array( 'Retry-After' => '2' ) ),
			self::REFRESH_BUSY            => array( 'temporarily_unavailable', 'Another refresh for this connection is in progress. Retry shortly.', 503, 'UA-MCP-526', array( 'Retry-After' => '2' ) ),
			self::REFRESH_REUSED          => array( 'invalid_grant', 'The refresh token is invalid or was reused.', 400, 'UA-MCP-512' ),
			self::ACCESS_ISSUANCE_FAILED  => array( 'server_error', 'The access token could not be issued.', 500, 'UA-MCP-513' ),
			self::USER_MISSING            => array( 'invalid_grant', 'The WordPress user who approved this connection no longer exists. Connect again and approve as a current administrator.', 400, 'UA-MCP-535' ),
		);
		if ( ! isset( $failures[ $reason ] ) ) {
			throw new \InvalidArgumentException( 'Unknown token exchange failure.' );
		}
		return new Token_Exchange_Failure( ...$failures[ $reason ] );
	}
}
