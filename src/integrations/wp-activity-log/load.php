<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Wp_Activity_Log\Wp_Activity_Log_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Wp_Activity_Log\Wp_Activity_Log_Integration();
