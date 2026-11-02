<?php
/**
 * Dropbox Trigger Subscription
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Exception;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Trigger_Subscriptions;

/**
 * Registers Dropbox triggers for a connection that got a new connection id.
 *
 * The platform keys a trigger subscription on the connection. When a staging
 * copy attaches to the live site's Dropbox account, or a connection sync
 * rebinds the row, the new connection has no subscription even though the
 * "Enable triggers" switch is on here. The platform-side owner of that debt
 * ({@see Trigger_Subscriptions}) asks each integration through a filter; this
 * answers it for Dropbox, whose triggers follow the default connection.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Trigger_Subscription {

	/**
	 * Dropbox API caller.
	 *
	 * @var Dropbox_Api_Caller
	 */
	private $api;

	/**
	 * Owner of the "Enable triggers" switch.
	 *
	 * @var Dropbox_Webhooks
	 */
	private $webhooks;

	/**
	 * Constructor.
	 *
	 * @param Dropbox_Api_Caller|object $api      Registers the site for Dropbox events.
	 * @param Dropbox_Webhooks          $webhooks Owner of the "Enable triggers" switch.
	 */
	public function __construct( $api, Dropbox_Webhooks $webhooks ) {
		$this->api      = $api;
		$this->webhooks = $webhooks;
	}

	/**
	 * Answer the platform's request for a trigger subscription.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'automator_platform_trigger_subscription_restore', array( $this, 'restore' ), 10, 2 );
	}

	/**
	 * Register Dropbox triggers for the connection when they were on.
	 *
	 * @param string     $outcome    The answer so far.
	 * @param Connection $connection The connection that got a new connection id.
	 *
	 * @return string One of Trigger_Subscriptions' OUTCOME_* constants.
	 */
	public function restore( $outcome, $connection ) {

		if ( ! $connection instanceof Connection || 'dropbox' !== $connection->integration() ) {
			return $outcome;
		}

		// Registration follows the default connection; another account has no triggers.
		if ( ! $this->webhooks->get_webhooks_enabled_status() || ! $connection->is_default() ) {
			return Trigger_Subscriptions::OUTCOME_NOT_NEEDED;
		}

		try {
			$this->api->register_site_for_webhooks();
		} catch ( Exception $e ) {
			return Trigger_Subscriptions::OUTCOME_FAILED;
		}

		return Trigger_Subscriptions::OUTCOME_REGISTERED;
	}
}
