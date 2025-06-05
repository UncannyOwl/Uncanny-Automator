<?php

use Uncanny_Automator\Integrations\OpenAI_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Uncanny_Automator\Integrations\OpenAI_Integration' ) ) {
	new OpenAI_Integration();
}
