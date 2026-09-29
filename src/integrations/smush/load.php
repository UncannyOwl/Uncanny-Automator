<?php

use Uncanny_Automator\Integrations\Smush\Smush_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\\Uncanny_Automator\\Integrations\\Smush\\Smush_Integration' ) ) {
	return;
}

new Smush_Integration();
