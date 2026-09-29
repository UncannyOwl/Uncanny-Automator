<?php

use Uncanny_Automator\Integrations\Loginizer\Loginizer_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! class_exists( '\Uncanny_Automator\Integrations\Loginizer\Loginizer_Integration' ) ) {
	return;
}
new Loginizer_Integration();
