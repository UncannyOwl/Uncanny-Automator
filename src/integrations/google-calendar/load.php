<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Google_Calendar\Google_Calendar_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Google_Calendar\Google_Calendar_Integration();
