<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Litespeed_Cache\Litespeed_Cache_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Litespeed_Cache\Litespeed_Cache_Integration();
