<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Cloudflare\Cloudflare_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Cloudflare\Cloudflare_Integration();
