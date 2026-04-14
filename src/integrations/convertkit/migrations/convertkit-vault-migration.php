<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

use Uncanny_Automator\Migrations\Migration;

/**
 * Class ConvertKit_Vault_Migration.
 *
 * Migrates legacy API key/secret credentials from the old option keys
 * (automator_convertkit_api_key, automator_convertkit_api_secret)
 * to the normalized vault flow.
 *
 * Legacy options are always cleaned up regardless of migration outcome.
 *
 * @package Uncanny_Automator
 */
class ConvertKit_Vault_Migration extends Migration {

	/**
	 * Legacy option keys used before framework normalization.
	 *
	 * @var string
	 */
	const LEGACY_OPTION     = 'automator_convertkit_client';
	const LEGACY_API_KEY    = 'automator_convertkit_api_key';
	const LEGACY_API_SECRET = 'automator_convertkit_api_secret';

	/**
	 * Api caller instance.
	 *
	 * @var ConvertKit_Api_Caller
	 */
	private $api;

	/**
	 * __construct
	 *
	 * @param string $name
	 * @param ConvertKit_Api_Caller $api
	 *
	 * @return void
	 */
	public function __construct( $name, $api ) {
		$this->name = $name;
		$this->api  = $api;
		add_action( 'shutdown', array( $this, 'maybe_run_migration' ) );
	}

	/**
	 * Check if migration should run.
	 *
	 * @return bool
	 */
	public function conditions_met() {
		return true;
	}

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function migrate() {

		// API keys are stored in their own options, not inside the client option.
		$api_key    = automator_get_option( self::LEGACY_API_KEY, '' );
		$api_secret = automator_get_option( self::LEGACY_API_SECRET, '' );

		if ( ! empty( $api_key ) && ! empty( $api_secret ) ) {
			try {
				$this->api->authorize_api_keys( $api_key, $api_secret );
			} catch ( \Exception $e ) {
				// API call failed — user will need to reconnect manually.
				unset( $e );
			}
		}

		// Always clean up all legacy options and mark complete.
		automator_delete_option( self::LEGACY_OPTION );
		automator_delete_option( self::LEGACY_API_KEY );
		automator_delete_option( self::LEGACY_API_SECRET );
		$this->complete();
	}
}
