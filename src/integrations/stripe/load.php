<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Stripe\Stripe_Integration' ) ) {
	return;
}


new Uncanny_Automator\Integrations\Stripe\Stripe_Integration();
