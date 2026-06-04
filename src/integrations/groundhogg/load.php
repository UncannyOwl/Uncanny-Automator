<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! class_exists( 'Uncanny_Automator\Integrations\Groundhogg\Groundhogg_Integration' ) ) {
	return;
}

class_alias( \Uncanny_Automator\Integrations\Groundhogg\Groundhogg_Helpers::class, 'Uncanny_Automator\\Groundhogg_Helpers' );

new Uncanny_Automator\Integrations\Groundhogg\Groundhogg_Integration();
