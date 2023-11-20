<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\WooCommerce_Bookings\Woocommerce_Bookings_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\WooCommerce_Bookings\Woocommerce_Bookings_Integration();
