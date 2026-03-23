<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Wp_Rocket\Wp_Rocket_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Wp_Rocket\Wp_Rocket_Integration();
