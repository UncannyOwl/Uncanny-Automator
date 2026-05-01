<?php
/**
 * Keap Contact Tokens Trait
 *
 * Provides contact action token definitions and hydration methods.
 *
 * @package Uncanny_Automator\Integrations\Keap
 * @since 7.0
 */

namespace Uncanny_Automator\Integrations\Keap;

/**
 * Trait Keap_Contact_Tokens
 *
 * @property Keap_App_Helpers $helpers
 * @property Keap_Api_Caller $api
 */
trait Keap_Contact_Tokens {

	/**
	 * Define contact action tokens.
	 *
	 * @return array Token definitions.
	 */
	protected function define_contact_action_tokens() {
		return array(
			'CONTACT_ID'         => array(
				'name' => esc_html_x( 'Contact ID', 'Keap', 'uncanny-automator' ),
				'type' => 'int',
			),
			'CONTACT_FIRST_NAME' => array(
				'name' => esc_html_x( 'Contact first name', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CONTACT_LAST_NAME'  => array(
				'name' => esc_html_x( 'Contact last name', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CONTACT_TAG_IDS'    => array(
				'name' => esc_html_x( 'Contact tag IDs', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Hydrate contact tokens from API response.
	 *
	 * @param array $contact Contact data from API.
	 *
	 * @return array Hydrated token values.
	 */
	protected function hydrate_contact_tokens( $contact ) {
		$tags = $contact['tag_ids'] ?? '';
		return array(
			'CONTACT_ID'         => $contact['id'] ?? 0,
			'CONTACT_FIRST_NAME' => $contact['given_name'] ?? '',
			'CONTACT_LAST_NAME'  => $contact['family_name'] ?? '',
			'CONTACT_TAG_IDS'    => ! empty( $tags ) && is_array( $tags ) ? implode( ',', $tags ) : '',
		);
	}
}
