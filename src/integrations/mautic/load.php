<?php
namespace Uncanny_Automator\Integrations\Mautic;

$integration = __NAMESPACE__ . '\\Mautic_Integration';

// Only load when WordPress loads it and if the integration class exists.
if ( defined( 'ABSPATH' ) && class_exists( $integration ) ) {
	return new $integration();
}
