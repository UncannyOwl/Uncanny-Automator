<?php

use Uncanny_Automator\Integrations\Wordfence\Wordfence_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( '\Uncanny_Automator\Integrations\Wordfence\Wordfence_Integration' ) ) {
	return;
}

new Wordfence_Integration();
