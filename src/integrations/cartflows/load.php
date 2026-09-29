<?php

use Uncanny_Automator\Integrations\Cartflows\Cartflows_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\Uncanny_Automator\Integrations\Cartflows\Cartflows_Integration' ) ) {
	return;
}

new Cartflows_Integration();
