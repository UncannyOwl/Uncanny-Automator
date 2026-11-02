<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Credential_Repository;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Site_Policy;

/**
 * End this site's AI approvals without depending on capped dashboard scans.
 * Registrations and hosted cleanup credentials survive. Work is bounded and
 * checkpointed; a retry resumes after the last confirmed row.
 *
 * @since 8.0.0
 */
final class OAuth_Site_Credential_Revoker {

	private const PROGRESS   = 'site_disconnect';
	private const PAGE_SIZE  = 50;
	private const WORK_LIMIT = 250;

	/** False means unfinished work or unconfirmed storage; both can be retried. */
	public function revoke(): bool {
		$clients = ( new OAuth_Client_Registry() )->confirmed_clients();
		if ( null === $clients ) {
			return false;
		}
		$store = new OAuth_Artifact_Store();
		foreach ( array_keys( $clients ) as $client_id ) {
			if ( ! is_string( $client_id ) || '' === $client_id || strlen( $client_id ) > 2048
				|| true !== $store->with_lock(
					'client',
					$client_id,
					static function (): bool {
						return true;
					}
				) ) {
				return false;
			}
		}
		return true === $store->with_lock(
			self::PROGRESS,
			'site',
			function () use ( $store ): bool {
				$state = $store->get_confirmed( self::PROGRESS, 'site' );
				if ( null === $state ) {
					return false;
				}
				$progress = $state['exists'] ? $state['record'] : array(
					'phase'  => 0,
					'cursor' => 0,
				);
				if ( ! is_array( $progress ) || ! isset( $progress['phase'], $progress['cursor'] ) ) {
					return false;
				}
				$deadline = microtime( true ) + 3;
				$work     = 0;
				while ( $progress['phase'] < 2 && $work < self::WORK_LIMIT && microtime( true ) < $deadline ) {
					$rows = $this->read_page( (int) $progress['phase'], (int) $progress['cursor'] );
					if ( null === $rows ) {
						return false;
					}
					if ( array() === $rows ) {
						++$progress['phase'];
						$progress['cursor'] = 0;
						if ( ! $store->put( self::PROGRESS, 'site', $progress ) ) {
							return false;
						}
						continue;
					}
					foreach ( $rows as $row ) {
						if ( ! $this->revoke_row( (int) $progress['phase'], $row, $store ) ) {
							return false;
						}
						$progress['cursor'] = (int) $row->row_id;
						if ( ! $store->put( self::PROGRESS, 'site', $progress ) ) {
							return false;
						}
						if ( ++$work >= self::WORK_LIMIT || microtime( true ) >= $deadline ) {
							break;
						}
					}
				}
				return 2 === $progress['phase'] && $store->delete( self::PROGRESS, 'site' );
			}
		);
	}

	/** Fresh pages use primary keys, so deleting rows never skips the next page. */
	private function read_page( int $phase, int $cursor ): ?array {
		global $wpdb;
		if ( 0 === $phase ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pages through credential usermeta rows by primary key; live state the cleanup deletes, so it must not be cached.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT umeta_id AS row_id, user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND umeta_id > %d ORDER BY umeta_id LIMIT %d",
					( new Token_Site_Policy() )->get_token_meta_key(),
					$cursor,
					self::PAGE_SIZE
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix scan of the options table, which WordPress has no API for; live OAuth state the cleanup deletes, so it must not be cached.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id AS row_id, option_name, option_value FROM {$wpdb->options} WHERE option_id > %d AND (option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s) AND option_name NOT LIKE %s ORDER BY option_id LIMIT %d",
					$cursor,
					$wpdb->esc_like( 'automator_mcp_credential_v2_' ) . '%',
					$wpdb->esc_like( 'automator_mcp_oauth_v1_family_' ) . '%',
					$wpdb->esc_like( 'automator_mcp_oauth_v1_consent_' ) . '%',
					$wpdb->esc_like( 'automator_mcp_oauth_v1_code_' ) . '%',
					'%' . $wpdb->esc_like( '_lock' ),
					self::PAGE_SIZE
				)
			);
		}
		return is_array( $rows ) && '' === $wpdb->last_error ? $rows : null;
	}

	/** Mark access invalid before cleanup; locked or failed rows keep their place. */
	private function revoke_row( int $phase, $row, OAuth_Artifact_Store $store ): bool {
		$marker = new OAuth_User_Revocation_Marker( $store );
		if ( 0 === $phase ) {
			$user_id = (int) $row->user_id;
			if ( ! $marker->mark( $user_id ) ) {
				return false;
			}
			// Drop the aggregate under its existing lock. The option phase removes
			// its indexes one row at a time; do not walk thousands of tokens here.
			return ( new Token_Credential_Repository() )->mutate(
				$user_id,
				( new Token_Site_Policy() )->get_token_meta_key(),
				static function (): array {
					return array();
				}
			);
		}
		$record = automator_safe_unserialize( $row->option_value );
		if ( ! is_array( $record ) || (int) ( $record['user_id'] ?? 0 ) <= 0 ) {
			return false;
		}
		$revoke    = static function () use ( $row, $record, $store, $marker ): bool {
			if ( ! $marker->mark( (int) $record['user_id'] ) ) {
				return false;
			}
			if ( 0 === strpos( $row->option_name, 'automator_mcp_oauth_v1_family_' ) ) {
				$family_id = $record['family_id'] ?? null;
				if ( ! is_string( $family_id ) || '' === $family_id ) {
					return false;
				}
				return true === $store->with_lock(
					'family',
					$family_id,
					static function () use ( $store, $family_id ): bool {
						$family = $store->get_confirmed( 'family', $family_id );
						if ( null === $family ) {
							return false;
						}
						if ( ! $family['exists'] || ! empty( $family['record']['revoked_at'] ) ) {
							return true;
						}
						$value               = $family['record'];
						$value['revoked_at'] = time();
						return $store->put( 'family', $family_id, $value );
					}
				);
			}
			return delete_option( $row->option_name );
		};
		$client_id = $record['client_id'] ?? '';
		return is_string( $client_id ) && '' !== $client_id
			? true === $store->with_lock( 'client', $client_id, $revoke ) : $revoke();
	}
}
