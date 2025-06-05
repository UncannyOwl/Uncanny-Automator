<?php

use Uncanny_Automator\Integrations\Claude\Claude_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Uncanny_Automator\Integrations\Claude\Claude_Integration' ) ) {
	new Claude_Integration();
}
