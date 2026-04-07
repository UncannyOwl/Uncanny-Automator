<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\ConvertKit\ConvertKit_Integration' ) ) {
	return;
}

// Backward compatibility alias for Pro add-on that references the legacy helper class.
class_alias(
	'Uncanny_Automator\Integrations\ConvertKit\ConvertKit_App_Helpers',
	'Uncanny_Automator\ConvertKit_Helpers'
);

new Uncanny_Automator\Integrations\ConvertKit\ConvertKit_Integration();
