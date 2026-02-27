<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Wp_Super_Cache\Wp_Super_Cache_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Wp_Super_Cache\Wp_Super_Cache_Integration();
