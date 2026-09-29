<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Post_Smtp\Post_Smtp_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Post_Smtp\Post_Smtp_Integration();
