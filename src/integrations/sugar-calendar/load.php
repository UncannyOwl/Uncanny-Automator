<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Integration();
