<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\App_Events;

use Uncanny_Automator\App\Integration_Catalog\App_Events\Value_Objects\App_Event_Summary;

/**
 * An app that sends the site events through the Automator platform: the
 * platform's slug for it, the integration it belongs to, and how its events
 * read on the Logs page.
 *
 * The platform names an app by its own slug (`dropbox`, `facebook_lead_ads`,
 * `google-drive`); the site names it by its integration code (`DROPBOX`,
 * `FACEBOOK_LEAD_ADS`, `GOOGLESHEET`). The two are not a change of case
 * apart, so each app states both.
 *
 * @since 8.1.0
 */
interface App_Event_Source {

	/**
	 * The platform's slug for the app, as an inbox row stores it.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	public function get_platform_slug(): string;

	/**
	 * The code of the integration the app's triggers belong to.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	public function get_integration_code(): string;

	/**
	 * What one delivery of the app's events says, in the Logs page's words.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string, mixed> $data The vendor event's data block.
	 *
	 * @return App_Event_Summary
	 */
	public function summarize( array $data ): App_Event_Summary;
}
