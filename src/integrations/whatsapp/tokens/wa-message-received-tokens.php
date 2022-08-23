<?php
namespace Uncanny_Automator;

class Wa_Message_Received_Tokens {

	public function message_received_tokens() {

		return array(
			'MESSAGE' => array(
				'name'         => 'Message',
				'hydrate_with' => array( $this, 'message' ),
			),
			'SENDER'  => array(
				'name'         => 'Sender',
				'hydrate_with' => array( $this, 'sender' ),
			),
		);

	}

	public function message( ...$args ) {

		return isset( $args[1]['trigger_args'][0]['body'] )
			? sanitize_text_field( $args[1]['trigger_args'][0]['body'] )
			: '';

	}

	public function sender( ...$args ) {

		return isset( $args[1]['trigger_args'][0]['from'] )
			? sanitize_text_field( $args[1]['trigger_args'][0]['from'] )
			: '';

	}

}
