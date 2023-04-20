<?php
namespace Uncanny_Automator;

class Wa_Message_Received_Tokens {

	public function message_received_tokens() {

		return array(
			'MESSAGE'             => array(
				'name'         => 'Message',
				'hydrate_with' => array( $this, 'message' ),
			),
			'SENDER'              => array(
				'name'         => 'Sender',
				'hydrate_with' => array( $this, 'sender' ),
			),
			'SENDER_PROFILE_NAME' => array(
				'name'         => 'Sender profile name',
				'hydrate_with' => array( $this, 'sender_profile_name' ),
			),
		);

	}

	public function sender_profile_name( ...$args ) {
		// Whatsapp has weird response payload. They probably designed the payload to have multiple sender.
		// For now, target the first sender. There is no multiple sender in their API.
		// Update this token if they add that as part of this API.
		return isset( $args[1]['trigger_args'][0]['_response']['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] )
			? sanitize_text_field( $args[1]['trigger_args'][0]['_response']['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] )
			: '';
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
