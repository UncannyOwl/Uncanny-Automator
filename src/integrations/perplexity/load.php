<?php
use Uncanny_Automator\Integrations\Perplexity\Perplexity_Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Uncanny_Automator\Integrations\Perplexity\Perplexity_Integration' ) ) {
	new Perplexity_Integration();
}
