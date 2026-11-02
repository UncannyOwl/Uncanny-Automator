<?php

namespace Uncanny_Automator\Integrations\Redirect;

/**
 * Class Redirect_Helpers
 *
 * The always-on seam of the integration: the part loader instantiates every helper for an integration a
 * published recipe uses, so the delivery hooks register here (Task 2) and nowhere else.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator
 */
class Redirect_Helpers {

	/**
	 * Register the delivery hooks once per request.
	 */
	public function __construct() {
		Redirect_Delivery::instance()->register_hooks();
	}

	/**
	 * The one field the action has; the 8.0.0 retirement migration writes rows in this exact shape.
	 *
	 * @return array
	 */
	public function redirect_url_field(): array {
		return array(
			'option_code'     => 'REDIRECT_URL',
			'label'           => esc_html_x( 'Redirect URL', 'Redirect', 'uncanny-automator' ),
			'input_type'      => 'url',
			'supports_tokens' => true,
			'required'        => true,
			'placeholder'     => 'https://',
		);
	}
}
