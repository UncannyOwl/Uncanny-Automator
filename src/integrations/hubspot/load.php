<?php
/**
 * Uncanny Automator HubSpot Integration
 *
 * @package UncannyAutomator
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\HubSpot\HubSpot_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\HubSpot\HubSpot_Integration();
