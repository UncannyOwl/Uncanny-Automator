<?php

use Uncanny_Automator\Integrations\Google_Site_Kit\Google_Site_Kit_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\Uncanny_Automator\Integrations\Google_Site_Kit\Google_Site_Kit_Integration' ) ) {
	return;
}

new Google_Site_Kit_Integration();
