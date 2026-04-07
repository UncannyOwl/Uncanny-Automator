<?php

namespace Uncanny_Automator\Integrations\Mautic;

/**
 * Adds one or more tags to a Mautic contact identified by email.
 *
 * @since 7.0
 *
 * @property Mautic_App_Helpers $helpers
 * @property Mautic_Api_Caller $api
 */
class TAG_CONTACT_ADD extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Configure the action code, meta key, sentence templates, action tokens, and user requirement.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAUTIC' );
		$this->set_action_code( 'TAG_CONTACT_ADD' );
		$this->set_action_meta( 'TAG_CONTACT_ADD_META' );
		$this->set_requires_user( false );
		$this->set_readable_sentence( esc_attr_x( 'Add {{tags}} to {{a contact}}', 'Mautic', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag option code, %2$s is the email option code
				esc_attr_x(
					'Add {{tags:%1$s}} to {{a contact:%2$s}}',
					'Mautic',
					'uncanny-automator'
				),
				'TAG:' . $this->get_action_meta(),
				'EMAIL:' . $this->get_action_meta()
			)
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
			$this->helpers->get_tag_option_config(),
		);
	}

	/**
	 * Add one or more tags to the specified Mautic contact via the API proxy.
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

		$email     = $this->helpers->validate_email( $parsed['EMAIL'] ?? '' );
		$tag_names = $this->helpers->resolve_tag_names(
			$parsed['TAG'] ?? '',
			$args['action_meta']['TAG_readable'] ?? ''
		);

		$this->api->api_request(
			array(
				'action'  => 'tag_contact_add',
				'tags'    => wp_json_encode( $tag_names ),
				'contact' => rawurlencode( $email ),
			),
			$action_data
		);

		return true;
	}
}
