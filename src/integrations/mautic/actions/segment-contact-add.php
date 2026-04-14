<?php

namespace Uncanny_Automator\Integrations\Mautic;

/**
 * Adds a contact (identified by email) to a Mautic segment.
 *
 * @since 5.0
 *
 * @property Mautic_App_Helpers $helpers
 * @property Mautic_Api_Caller $api
 */
class SEGMENT_CONTACT_ADD extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Configure the action code, meta key, sentence templates, action tokens, and user requirement.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAUTIC' );
		$this->set_action_code( 'SEGMENT_CONTACT_ADD' );
		$this->set_action_meta( 'SEGMENT_CONTACT_ADD_META' );
		$this->set_requires_user( false );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a contact}} to {{a segment}}', 'Mautic', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the email option code, %2$s is the segment option code
				esc_attr_x(
					'Add {{a contact:%1$s}} to {{a segment:%2$s}}',
					'Mautic',
					'uncanny-automator'
				),
				'EMAIL:' . $this->get_action_meta(),
				'SEGMENT:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'SEGMENT_NAME' => array(
					'name' => esc_html_x( 'Segment name', 'Mautic', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define the option fields for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config(),
			$this->helpers->get_segment_option_config(),
		);
	}

	/**
	 * Add the contact to the specified Mautic segment via the API proxy.
	 * Hydrates the SEGMENT_NAME action token on success.
	 *
	 * @param int     $user_id     The WordPress user ID.
	 * @param mixed[] $action_data The action configuration data.
	 * @param int     $recipe_id   The recipe ID.
	 * @param mixed[] $args        Additional arguments including action_meta.
	 * @param mixed[] $parsed      The parsed token values keyed by option code.
	 *
	 * @return bool True on success.
	 * @throws \Exception For invalid params, or if the API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$segment = $this->helpers->validate_segment( $parsed['SEGMENT'] ?? '' );
		$email   = $this->helpers->validate_email( $parsed['EMAIL'] ?? '' );

		$this->api->api_request(
			array(
				'action'     => 'segment_contact_add',
				'segment_id' => $segment,
				'contact'    => rawurlencode( $email ),
			),
			$action_data
		);

		$this->hydrate_tokens(
			array(
				'SEGMENT_NAME' => $args['action_meta']['SEGMENT_readable'],
			)
		);

		return true;
	}
}
