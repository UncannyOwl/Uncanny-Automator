<?php
/**
 * Dropbox Webhooks
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App_Integrations\App_Webhooks;

/**
 * Class Dropbox_Webhooks
 *
 * Owns the "Enable triggers" setting. Dropbox events reach the site through
 * the platform's central webhook receiver and {@see Dropbox_Event_Fanout},
 * never through a per-integration route, so none is registered.
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Webhooks extends App_Webhooks {

	/**
	 * No per-integration webhook route.
	 *
	 * @return bool
	 */
	public function should_register_webhooks() {
		return false;
	}

	/**
	 * Dropbox's change notifications reach the site only through the Automator
	 * platform's signed delivery.
	 *
	 * @return bool
	 */
	public function is_platform_delivered() {
		return true;
	}

	/**
	 * Dropbox's triggers are delivered by the Automator platform, and nothing is
	 * delivered to a LOCAL site (L247): while the site is local its triggers
	 * read as off, whatever the stored switch says, and turning them on is
	 * refused before any request (the shared caller answers
	 * `register-site-for-webhooks` with the local-site sentence).
	 *
	 * @return bool
	 */
	public function get_webhooks_enabled_status() {

		if ( ( new Local_Site_Status() )->is_local() ) {
			return false;
		}

		return parent::get_webhooks_enabled_status();
	}
}
