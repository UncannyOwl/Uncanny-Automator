<?php

namespace Uncanny_Automator\Integrations\Mautic;

/**
 * Removes one or more tags from a Mautic contact identified by email.
 *
 * @since 7.0
 *
 * @property Mautic_App_Helpers $helpers
 * @property Mautic_Api_Caller $api
 */
class TAG_CONTACT_REMOVE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Configure the action code, meta key, sentence templates, action tokens, and user requirement.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAUTIC' );
		$this->set_action_code( 'TAG_CONTACT_REMOVE' );
		$this->set_action_meta( 'TAG_CONTACT_REMOVE_META' );
		$this->set_requires_user( false );
		$this->set_readable_sentence( esc_attr_x( 'Remove {{tags}} from {{a contact}}', 'Mautic', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag option code, %2$s is the contact option code
				esc_attr_x(
					'Remove {{tags:%1$s}} from {{a contact:%2$s}}',
					'Mautic',
					'uncanny-automator'
				),
				'TAG:' . $this->get_action_meta(),
				$this->get_action_meta()
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
			$this->helpers->get_email_option_config( $this->get_action_meta() ),
			$this->helpers->get_tag_option_config(),
		);
	}

	/**
	 * Remove one or more tags from the specified Mautic contact via the API proxy.
	 * Prepends each tag name with a minus sign for the Mautic tags convention.
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

		$email     = $this->helpers->validate_email( $parsed[ $this->get_action_meta() ] ?? '' );
		$tag_names = $this->helpers->resolve_tag_names(
			$parsed['TAG'] ?? '',
			$args['action_meta']['TAG_readable'] ?? ''
		);

		// Prefix each tag name with '-' to signal removal to Mautic.
		$removal_tags = array_map(
			function ( $name ) {
				return '-' . $name;
			},
			$tag_names
		);

		$this->api->api_request(
			array(
				'action'  => 'tag_contact_remove',
				'tags'    => wp_json_encode( $removal_tags ),
				'contact' => rawurlencode( $email ),
			),
			$action_data
		);

		return true;
	}
}
