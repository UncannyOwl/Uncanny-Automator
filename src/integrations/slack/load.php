<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Slack\Slack_Integration' ) ) {
	return;
}

new Uncanny_Automator\Integrations\Slack\Slack_Integration();
