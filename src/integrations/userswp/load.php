<?php

use Uncanny_Automator\Integrations\Userswp\Userswp_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\\Uncanny_Automator\\Integrations\\Userswp\\Userswp_Integration' ) ) {
	return;
}

new Userswp_Integration();
