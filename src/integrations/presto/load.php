<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Presto\Presto_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Presto\Presto_Integration();

// Load tokens
new Uncanny_Automator\Integrations\Presto\Presto_Tokens();
