<?php

use Uncanny_Automator\Integrations\Mainwp\Mainwp_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\\Uncanny_Automator\\Integrations\\Mainwp\\Mainwp_Integration' ) ) {
	return;
}

new Mainwp_Integration();
