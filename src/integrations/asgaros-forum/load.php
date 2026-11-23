<?php

use Uncanny_Automator\Integrations\Asgaros_Forum\Asgaros_Forum_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\\Uncanny_Automator\\Integrations\\Asgaros_Forum\\Asgaros_Forum_Integration' ) ) {
	return;
}

new Asgaros_Forum_Integration();
