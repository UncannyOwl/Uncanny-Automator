<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

/**
 * Trait Sugar_Calendar_Requirements
 *
 * The plugins the Sugar Calendar ticket and RSVP items state, each as the literal set_plugin_details() takes.
 *
 * @package Uncanny_Automator
 *
 * @since 8.1.0
 */
trait Sugar_Calendar_Requirements {

	/**
	 * The plugin a ticket item states: Sugar Calendar itself, which has sold tickets since 3.1.0 (before that,
	 * the Event Ticketing add-on did). The item's guard checks for the ticketing functions.
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function ticketing_plugin_details(): array {
		return array(
			'plugin_file'       => 'sugar-calendar-lite/sugar-calendar-lite.php',
			'plugin_name'       => 'Sugar Calendar',
			'distribution_type' => 'wp_org',
		);
	}

	/**
	 * The plugin an RSVP item states: the RSVP add-on.
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function rsvp_plugin_details(): array {
		return array(
			'plugin_file'       => 'sc-rsvp/sc-rsvp.php',
			'plugin_name'       => 'Sugar Calendar RSVP',
			'distribution_type' => 'commercial',
		);
	}
}
