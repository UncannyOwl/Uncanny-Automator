<?php

namespace Uncanny_Automator\Integrations\Mautic;

/**
 * Creates a new segment in Mautic with the given name, alias, and description.
 *
 * @since 5.0
 *
 * @property Mautic_App_Helpers $helpers
 * @property Mautic_Api_Caller $api
 */
class SEGMENT_CREATE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Configure the action code, meta key, sentence templates, and user requirement.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAUTIC' );
		$this->set_action_code( 'SEGMENT_CREATE' );
		$this->set_action_meta( 'SEGMENT_CREATE_META' );
		$this->set_requires_user( false );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a segment}}', 'Mautic', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the segment option code
				esc_attr_x(
					'Create {{a segment:%1$s}}',
					'Mautic',
					'uncanny-automator'
				),
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
			array(
				'option_code' => $this->get_action_meta(),
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Name', 'Mautic', 'uncanny-automator' ),
				'required'    => true,
			),
			array(
				'option_code' => 'ALIAS',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Alias', 'Mautic', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'DESCRIPTION',
				'input_type'  => 'textarea',
				'label'       => esc_html_x( 'Description', 'Mautic', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Execute the segment creation API call with the parsed name, alias, and description.
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
		// Sanitize / validate required fields.
		$name = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		if ( empty( $name ) ) {
			throw new \Exception( esc_html_x( 'Segment name is required', 'Mautic', 'uncanny-automator' ) );
		}

		// Sanitize optional fields.
		$alias       = sanitize_text_field( $parsed['ALIAS'] ?? '' );
		$description = sanitize_textarea_field( $parsed['DESCRIPTION'] ?? '' );

		// Create the segment.
		$this->api->api_request(
			array(
				'action'      => 'segment_create',
				'name'        => $name,
				'alias'       => $alias,
				'description' => $description,
			),
			$action_data
		);

		// Invalidate the segments cache so it's refreshed on the next request.
		automator_delete_option( $this->helpers->get_option_key( 'segments' ) );

		return true;
	}
}
