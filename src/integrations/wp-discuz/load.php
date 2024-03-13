<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Wp_Discuz\Wp_Discuz_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Wp_Discuz\Wp_Discuz_Integration();
