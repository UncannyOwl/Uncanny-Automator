<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\W3_Total_Cache\W3_Total_Cache_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\W3_Total_Cache\W3_Total_Cache_Integration();
