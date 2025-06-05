<?php

use Uncanny_Automator\Integrations\Cohere\Cohere_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Uncanny_Automator\Integrations\Cohere\Cohere_Integration' ) ) {
	new Cohere_Integration();
}
