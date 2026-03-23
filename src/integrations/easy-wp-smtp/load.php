<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Easy_Wp_Smtp\Easy_Wp_Smtp_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Easy_Wp_Smtp\Easy_Wp_Smtp_Integration();
