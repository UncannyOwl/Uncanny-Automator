<?php

use Uncanny_Automator\Integrations\Mistral\Mistral_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Uncanny_Automator\Integrations\Mistral\Mistral_Integration' ) ) {
	new Mistral_Integration();
}
