<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Add Trigger to Recipe Action.
 *
 * @since 5.8
 */
class ADD_TRIGGER_TO_RECIPE extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_action_code( 'ADD_TRIGGER_TO_RECIPE' );
		$this->set_action_meta( 'ADD_TRIGGER_TO_RECIPE_META' );
		$this->set_is_pro( false );
		$this->set_integration( 'UOA' );
		$this->set_requires_user( false );

		// Sentence that appears after you choose the action in the recipe builder.
		$sentence = sprintf(
			// translators: %1$s is the placeholder for the trigger, %2$s for the recipe.
			esc_html_x( 'Add {{a trigger:%1$s}} to {{a recipe:%2$s}}', 'Uncanny Automator', 'uncanny-automator' ),
			'PLACEHOLDER:' . $this->get_action_meta(),
			'PLACEHOLDER:RECIPE_ID'
		);

		// Sentence that appears in the dropdown in the recipe builder.
		$readable_sentence = esc_html_x( 'Add {{a trigger}} to {{a recipe}}', 'Uncanny Automator', 'uncanny-automator' );

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
		return array();
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {

		$trigger_code = array(
			'option_code'     => $this->get_action_meta(),
			'label'           => esc_html_x( 'Trigger', 'Uncanny Automator', 'uncanny-automator' ),
			'input_type'      => 'text',
			'required'        => true,
			'description'     => esc_html_x( 'Specify a trigger code (e.g. ACFWCUSERRECEIVESCREDIT)', 'Uncanny Automator', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		$recipe_id = array(
			'option_code'     => 'RECIPE_ID',
			'label'           => esc_html_x( 'Recipe ID', 'Uncanny Automator', 'uncanny-automator' ),
			'input_type'      => 'int',
			'required'        => true,
			'relevant_tokens' => array(),
		);

		return array( $trigger_code, $recipe_id );
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

		$trigger_code     = sanitize_text_field( $this->get_parsed_meta_value( $this->get_action_meta() ) );
		$target_recipe_id = absint( $this->get_parsed_meta_value( 'RECIPE_ID' ) );

		// Validate trigger exists
		$trigger = automator_get_trigger_by_code( $trigger_code );

		// Result will be WP_Error if trigger config is invalid.
		$config = array(
			'integration'             => $trigger['integration'] ?? '',
			'sentence'                => $trigger['sentence'] ?? '',
			'sentence_human_readable' => $trigger['select_option_name'] ?? '',
			'type'                    => $trigger['type'] ?? '',
			'hook'                    => $trigger['action'] ?? '',
		);

		$result = automator_add_trigger_to_recipe(
			$target_recipe_id,
			array(
				'trigger_code' => $trigger_code,
				'config'       => $config,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message.
		}

		return true;
	}
}
