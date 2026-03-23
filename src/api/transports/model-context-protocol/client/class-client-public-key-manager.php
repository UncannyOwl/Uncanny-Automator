<?php
/**
 * MCP public key manager.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

/**
 * Class Client_Public_Key_Manager
 *
 * Coordinates storage, caching and remote fetching of the public key.
 */
class Client_Public_Key_Manager {

	/**
	 * Cache TTL.
	 *
	 * @var int
	 */
	private int $ttl;

	/**
	 * Storage helper.
	 *
	 * @var Client_Public_Key_Storage
	 */
	private Client_Public_Key_Storage $storage;

	/**
	 * Remote fetcher.
	 *
	 * @var Client_Public_Key_Fetcher
	 */
	private Client_Public_Key_Fetcher $fetcher;

	/**
	 * License data provider.
	 *
	 * @var Client_Public_Key_License_Provider
	 */
	private Client_Public_Key_License_Provider $license_provider;

	/**
	 * Time callback.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Logger callback.
	 *
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Cached record.
	 *
	 * @var Client_Public_Key_Record|null
	 */
	private ?Client_Public_Key_Record $cache = null;

	/**
	 * Constructor.
	 *
	 * @param Client_Public_Key_Storage|null          $storage          Storage helper.
	 * @param Client_Public_Key_Fetcher|null          $fetcher          Remote fetcher.
	 * @param Client_Public_Key_License_Provider|null $license_provider License provider.
	 * @param callable|null                           $clock            Time provider.
	 * @param callable|null                           $logger           Logger callback.
	 * @param int                                     $ttl              Cache lifetime.
	 */
	public function __construct(
		?Client_Public_Key_Storage $storage = null,
		?Client_Public_Key_Fetcher $fetcher = null,
		?Client_Public_Key_License_Provider $license_provider = null,
		?callable $clock = null,
		?callable $logger = null,
		int $ttl = 21600
	) {
		$this->storage          = $storage ? $storage : new Client_Public_Key_Storage();
		$this->fetcher          = $fetcher ? $fetcher : new Client_Public_Key_Fetcher();
		$this->license_provider = $license_provider ? $license_provider : new Client_Public_Key_License_Provider();
		$this->clock            = $clock ? $clock : 'time';
		$this->logger           = $logger;
		$this->ttl              = $ttl;
	}

	/**
	 * Ensure the key is available and valid.
	 *
	 * @param bool $force_refresh Force refresh.
	 * @return bool
	 */
	public function ensure_public_key_ready( bool $force_refresh = false ): bool {
		$record = $this->resolve_record( $force_refresh );

		if ( $record->is_empty() ) {
			$this->log( 'Public key unavailable; aborting MCP chat initialization.' );
			return false;
		}

		if ( ! $record->is_status_ok() ) {
			$this->log(
				sprintf( 'Public key validation status "%s"; skipping MCP chat display.', $record->get_status() )
			);
			return false;
		}

		return true;
	}

	/**
	 * Get the PEM encoded public key string.
	 *
	 * @param bool $force_refresh Force refresh.
	 * @return string
	 */
	public function get_public_key( bool $force_refresh = false ): string {
		$record  = $this->resolve_record( $force_refresh );
		$base64  = $record->get_base64();
		$version = $record->get_version();

		if ( '' === $base64 ) {
			return '';
		}

		$pem = $this->convert_base64_to_pem( $base64 );

		return apply_filters( 'automator_mcp_public_key', $pem, $version, $record->to_array() );
	}

	/**
	 * Resolve the active record considering cache/storage/remote.
	 *
	 * @param bool $force_refresh Force refresh flag.
	 * @return Client_Public_Key_Record
	 */
	private function resolve_record( bool $force_refresh ): Client_Public_Key_Record {
		if ( $force_refresh ) {
			$this->cache = null;
		}

		if ( $this->cache ) {
			return $this->cache;
		}

		$now    = (int) call_user_func( $this->clock );
		$record = $this->storage->load();

		if ( $this->should_refresh( $record, $force_refresh, $now ) ) {
			$record = $this->refresh_record( $record, $now );
		} else {
			$record = $record->with_status( 'cached' );
		}

		if ( $record->is_empty() ) {
			$record = Client_Public_Key_Record::empty();
		}

		$this->cache = $record;

		return $record;
	}

	/**
	 * Determine if the record must be refreshed.
	 *
	 * @param Client_Public_Key_Record $record        Current record.
	 * @param bool                     $force_refresh Force refresh flag.
	 * @param int                      $now           Current timestamp.
	 * @return bool
	 */
	private function should_refresh( Client_Public_Key_Record $record, bool $force_refresh, int $now ): bool {
		if ( $force_refresh ) {
			return true;
		}

		return $record->is_stale( $this->ttl, $now );
	}

	/**
	 * Refresh the record by calling the remote API.
	 *
	 * @param Client_Public_Key_Record $current Current record.
	 * @param int                      $now     Timestamp.
	 * @return Client_Public_Key_Record
	 */
	private function refresh_record( Client_Public_Key_Record $current, int $now ): Client_Public_Key_Record {
		$license_data = $this->license_provider->get_license_data();
		$fetched      = $this->fetcher->fetch( \Uncanny_Automator\Api\Application\Mcp\Mcp_Client::get_inference_url(), $license_data, $now );

		if ( $fetched ) {
			$persisted = $fetched->with_fetch_metadata( $now, 'mcp_api' )->with_status( 'ok' );
			$this->storage->save( $persisted );

			return $persisted;
		}

		$this->storage->save( $current->with_status( 'failed' ) );

		return $current->with_status( 'failed' );
	}

	/**
	 * Convert base64 to PEM.
	 *
	 * @param string $base64 Base64 encoded key.
	 * @return string
	 */
	private function convert_base64_to_pem( string $base64 ): string {
		$decoded = base64_decode( $base64, true );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OAuth/JWT encoding.

		if ( false === $decoded ) {
			return '';
		}

		if ( false !== strpos( $decoded, 'BEGIN PUBLIC KEY' ) ) {
			return $decoded;
		}

		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( $base64, 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	/**
	 * Log a message if a logger is present.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( $this->logger && is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message, 'MCP Public Key' );
			return;
		}

		if ( function_exists( 'automator_log' ) ) {
			\automator_log( $message, 'MCP Public Key' );
		}
	}
}
