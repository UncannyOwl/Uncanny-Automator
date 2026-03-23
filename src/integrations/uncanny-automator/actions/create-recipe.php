<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Create Recipe Action.
 *
 * @since 5.7
 */
class CREATE_RECIPE extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_action_code( 'CREATE_RECIPE' );
		$this->set_action_meta( 'CREATE_RECIPE_META' );
		$this->set_is_pro( false );
		$this->set_integration( 'UOA' );
		$this->set_requires_user( false );

		// Sentence that appears after you choose the action in the recipe builder.
		$sentence = sprintf(
			// translators: %1$s is the placeholder for the title.
			esc_html_x( 'Create {{a recipe:%1$s}}', 'Uncanny Automator', 'uncanny-automator' ),
			'PLACEHOLDER:' . $this->get_action_meta()
		);

		// Sentence that appears in the dropdown in the recipe builder.
		$readable_sentence = esc_html_x( 'Create {{a recipe}}', 'Uncanny Automator', 'uncanny-automator' );

		$this->set_sentence( $sentence );
		$this->set_readable_sentence( $readable_sentence );
		$this->set_action_tokens( self::get_action_tokens(), $this->get_action_code() );
	}

	/**
	 * Get action tokens.
	 *
	 * @return array
	 */
	public function get_action_tokens() {
		return array(
			'RECIPE_ID'            => array(
				'name' => esc_html_x( 'Recipe ID', 'Uncanny Automator', 'uncanny-automator' ),
				'type' => 'int',
			),
			'RECIPE_TITLE'         => array(
				'name' => esc_html_x( 'Recipe title', 'Uncanny Automator', 'uncanny-automator' ),
			),
			'RECIPE_TYPE'          => array(
				'name' => esc_html_x( 'Recipe type', 'Uncanny Automator', 'uncanny-automator' ),
			),

			'RECIPE_NOTES'         => array(
				'name' => esc_html_x( 'Recipe notes', 'Uncanny Automator', 'uncanny-automator' ),
			),
			'RECIPE_STATUS'        => array(
				'name' => esc_html_x( 'Recipe status', 'Uncanny Automator', 'uncanny-automator' ),
			),

			'RECIPE_TRIGGER_LOGIC' => array(
				'name' => esc_html_x( 'Recipe trigger logic', 'Uncanny Automator', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {

		$title = array(
			'option_code'     => $this->get_action_meta(),
			'label'           => esc_html_x( 'Title', 'Uncanny Automator', 'uncanny-automator' ),
			'input_type'      => 'text',
			'required'        => true,
			'relevant_tokens' => array(),
		);

		$type = array(
			'option_code'           => 'RECIPE_TYPE',
			'label'                 => esc_html_x( 'Type', 'Uncanny Automator', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(
				array(
					'text'  => esc_html_x( 'Logged-in', 'Uncanny Automator', 'uncanny-automator' ),
					'value' => 'user',
				),
				array(
					'text'  => esc_html_x( 'Everyone', 'Uncanny Automator', 'uncanny-automator' ),
					'value' => 'anonymous',
				),
			),
			'options_show_id'       => false,
			'supports_custom_value' => false,
			'relevant_tokens'       => array(),
		);

		$notes = array(
			'option_code'     => 'RECIPE_NOTES',
			'label'           => esc_html_x( 'Notes', 'Uncanny Automator', 'uncanny-automator' ),
			'input_type'      => 'textarea',
			'required'        => false,
			'relevant_tokens' => array(),
		);

		return array( $title, $type, $notes );
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @throws \Exception - Exceptions bubble up intentionally; the orchestrator logs them.
	 *
	 * @return bool
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$title = sanitize_text_field( $this->get_parsed_meta_value( $this->get_action_meta() ) );
		$type  = sanitize_text_field( $this->get_parsed_meta_value( 'RECIPE_TYPE' ) );
		$notes = sanitize_textarea_field( $this->get_parsed_meta_value( 'RECIPE_NOTES' ) );

		$result = automator_create_recipe(
			array(
				'title'         => $title,
				'status'        => 'draft',
				'type'          => $type,
				'trigger_logic' => 'all',
				'notes'         => $notes,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message.
		}

		$this->hydrate_tokens(
			array(
				'RECIPE_ID'            => $result['recipe_id'] ?? '',
				'RECIPE_TITLE'         => $result['title'] ?? '',
				'RECIPE_TYPE'          => $result['type'] ?? '',
				'RECIPE_NOTES'         => $result['notes'] ?? '',
				'RECIPE_STATUS'        => $result['status'] ?? '',
				'RECIPE_TRIGGER_LOGIC' => $result['trigger_logic'] ?? '',
			)
		);

		return true;
	}
}
