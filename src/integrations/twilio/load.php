<?php
/**
 * Load Twilio integration
 *
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Twilio\Twilio_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Twilio\Twilio_Integration();
