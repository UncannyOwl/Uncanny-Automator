<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

$integration = __NAMESPACE__ . '\\Google_Calendar_Integration';

// Only load when WordPress loads it and if the integration class exists.
if ( defined( 'ABSPATH' ) && class_exists( $integration ) ) {
	return new $integration();
}
