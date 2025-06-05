<?php

use Uncanny_Automator\Integrations\Deepseek\Deepseek_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Uncanny_Automator\Integrations\Deepseek\Deepseek_Integration' ) ) {
	new Deepseek_Integration();
}
