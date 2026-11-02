<?php
/**
 * Facebook Lead Ads Page Subscription
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Exception;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Disconnect_Service;
use Uncanny_Automator\App\Infrastructure\Connections\Trigger_Subscriptions;

/**
 * Subscribes each page's platform connection to signed lead events, and ends
 * the subscription and the connection when the page goes away.
 *
 * Subscribing is what makes the platform deliver the page's leads to this
 * site signed, fields included. It also answers the platform's request to
 * restore a subscription for a row whose connection id changed (R6,
 * {@see Trigger_Subscriptions}): a connected page registers, anything else is
 * not needed.
 *
 * Ending is unregister, then the platform disconnect, then the local row.
 * The local row goes whatever the platform answers: an error is logged and
 * the removal proceeds. A row this site inherited as a copy and switched off
 * holds the live site's connection, so nothing is sent for it.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
class Facebook_Lead_Ads_Page_Subscription {

	/**
	 * Registers and unregisters a connection.
	 *
	 * @var Facebook_Lead_Ads_Api_Caller
	 */
	private $api;

	/**
	 * The pages the site is connected to.
	 *
	 * @var Facebook_Lead_Ads_App_Helpers
	 */
	private $helpers;

	/**
	 * The pages' rows.
	 *
	 * @var Facebook_Lead_Ads_Page_Connections
	 */
	private $connections;

	/**
	 * The platform disconnect.
	 *
	 * @var Disconnect_Service
	 */
	private $disconnect;

	/**
	 * Constructor.
	 *
	 * @param Facebook_Lead_Ads_Api_Caller       $api         Registers and unregisters a connection.
	 * @param Facebook_Lead_Ads_App_Helpers      $helpers     The pages the site is connected to.
	 * @param Facebook_Lead_Ads_Page_Connections $connections The pages' rows.
	 * @param Disconnect_Service                 $disconnect  The platform disconnect.
	 */
	public function __construct( $api, Facebook_Lead_Ads_App_Helpers $helpers, Facebook_Lead_Ads_Page_Connections $connections, Disconnect_Service $disconnect ) {
		$this->api         = $api;
		$this->helpers     = $helpers;
		$this->connections = $connections;
		$this->disconnect  = $disconnect;
	}

	/**
	 * Answer the platform's request for a subscription.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'automator_platform_trigger_subscription_restore', array( $this, 'restore' ), 10, 2 );
	}

	/**
	 * Register a Lead Ads connection that got a new connection id, when its
	 * page is connected here.
	 *
	 * @param string     $outcome    The answer so far.
	 * @param Connection $connection The row owed a subscription.
	 *
	 * @return string One of Trigger_Subscriptions' OUTCOME_* constants.
	 */
	public function restore( $outcome, $connection ) {

		if ( ! $connection instanceof Connection || Facebook_Lead_Ads_Page_Connections::INTEGRATION !== $connection->integration() ) {
			return $outcome;
		}

		if ( ! $this->is_page_connected( $connection ) ) {
			return Trigger_Subscriptions::OUTCOME_NOT_NEEDED;
		}

		return $this->subscribe( $connection )
			? Trigger_Subscriptions::OUTCOME_REGISTERED
			: Trigger_Subscriptions::OUTCOME_FAILED;
	}

	/**
	 * Subscribe the row's connection and record it on the row.
	 *
	 * @param Connection $row An active Lead Ads row.
	 *
	 * @return bool Whether the row's connection is now recorded as subscribed.
	 */
	public function subscribe( Connection $row ) {

		try {
			$this->api->register_site_for_webhooks( $row );
		} catch ( Exception $e ) {
			automator_log( 'Subscribing the Facebook page connection ' . $row->connection_id() . ' failed and will be retried: ' . $e->getMessage(), 'Facebook_Lead_Ads_Page_Subscription', false );
			return false;
		}

		return $this->connections->record_subscription( $row );
	}

	/**
	 * End the row's subscription and connection, and remove the row.
	 *
	 * @param Connection $row A Lead Ads row.
	 *
	 * @return void
	 */
	public function tear_down( Connection $row ) {

		if ( ! $row->is_quarantined() ) {
			try {
				$this->api->unregister_site_for_webhooks( $row );
			} catch ( Exception $e ) {
				automator_log( 'Unsubscribing the Facebook page connection ' . $row->connection_id() . ' failed: ' . $e->getMessage(), 'Facebook_Lead_Ads_Page_Subscription', false );
			}
		}

		$result = $this->disconnect->disconnect( $row->connection_id() );

		if ( $result->local_row_removed() ) {
			return;
		}

		automator_log( 'The platform did not disconnect the Facebook page connection ' . $row->connection_id() . ' (' . $result->status() . ', HTTP ' . $result->http_status() . '); the local row is removed anyway.', 'Facebook_Lead_Ads_Page_Subscription', false );

		$this->connections->remove( $row );
	}

	/**
	 * End every Lead Ads connection this site holds.
	 *
	 * @return void
	 */
	public function tear_down_all() {

		foreach ( $this->connections->all_rows() as $row ) {
			$this->tear_down( $row );
		}
	}

	/**
	 * Whether the row's page is one the site is connected to. A row that names
	 * no page counts while the integration is connected at all.
	 *
	 * @param Connection $row A Lead Ads row.
	 *
	 * @return bool
	 */
	private function is_page_connected( Connection $row ) {

		if ( ! $this->helpers->has_connection() ) {
			return false;
		}

		$page_id = $this->connections->page_id_of( $row );

		if ( '' === $page_id ) {
			return true;
		}

		foreach ( $this->helpers->get_pages_credentials() as $page ) {
			if ( isset( $page['id'] ) && (string) $page['id'] === $page_id ) {
				return true;
			}
		}

		return false;
	}
}
