<?php

use Uncanny_Automator\Integrations\Gemini\Gemini_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Uncanny_Automator\Integrations\Gemini\Gemini_Integration' ) ) {
	new Gemini_Integration();
}
