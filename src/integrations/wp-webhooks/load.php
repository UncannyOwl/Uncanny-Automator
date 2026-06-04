<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\\Integrations\\Wp_Webhooks\\Wpwh_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Wp_Webhooks\Wpwh_Integration();
