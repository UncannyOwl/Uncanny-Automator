<?php

use Uncanny_Automator\Integrations\Eventin\Eventin_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\Uncanny_Automator\Integrations\Eventin\Eventin_Integration' ) ) {
	return;
}

new Eventin_Integration();
