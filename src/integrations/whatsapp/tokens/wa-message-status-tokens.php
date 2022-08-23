<?php
namespace Uncanny_Automator;

class Wa_Message_Status_Tokens {

	public function status_updated_tokens() {

		return array(
			'SENDER'    => array(
				'name'         => 'Sender',
				'hydrate_with' => array( $this, 'callback_status_updated_tokens_from' ),
			),
			'RECIPIENT' => array(
				'name'         => 'Recipient',
				'hydrate_with' => array( $this, 'callback_status_updated_tokens_to' ),
			),
			'STATUS'    => array(
				'name'         => 'Status',
				'hydrate_with' => 'trigger_args|1',
			),
		);

	}

	public function callback_status_updated_tokens_from( ...$args ) {

		if ( isset( $args[1]['trigger_args'][0]['from'] ) ) {

			return sanitize_text_field( $args[1]['trigger_args'][0]['from'] );

		}

		return '';

	}

	public function callback_status_updated_tokens_to( ...$args ) {

		if ( isset( $args[1]['trigger_args'][0]['to'] ) ) {

			return sanitize_text_field( $args[1]['trigger_args'][0]['to'] );

		}

		return '';
	}

}
