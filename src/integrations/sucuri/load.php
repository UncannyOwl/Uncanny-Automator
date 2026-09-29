<?php

use Uncanny_Automator\Integrations\Sucuri\Sucuri_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\Uncanny_Automator\Integrations\Sucuri\Sucuri_Integration' ) ) {
	return;
}

new Sucuri_Integration();
