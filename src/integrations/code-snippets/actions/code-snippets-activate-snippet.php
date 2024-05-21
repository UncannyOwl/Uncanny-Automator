<?php

namespace Uncanny_Automator\Integrations\Code_Snippets;

use Uncanny_Automator\Recipe\Action;
use function Code_Snippets\activate_snippet;
use function Code_Snippets\get_snippet;

/**
 * Class CODE_SNIPPETS_ACTIVATE_SNIPPET
 *
 * @pacakge Uncanny_Automator
 */
class CODE_SNIPPETS_ACTIVATE_SNIPPET extends Action {

	protected $helpers;

	/**
	 * @return mixed
	 */
	protected function setup_action() {
		/** @var \Uncanny_Automator\Integrations\Code_Snippets\Code_Snippets_Helpers $helpers */
		$helpers       = array_shift( $this->dependencies );
		$this->helpers = $helpers;
		$this->set_integration( 'CODE_SNIPPETS' );
		$this->set_action_code( 'CS_ACTIVATE_SNIPPET' );
		$this->set_action_meta( 'CS_SNIPPETS' );
		$this->set_requires_user( false );
		$this->set_sentence( sprintf( esc_attr_x( 'Activate  {{a snippet:%1$s}}', 'Code Snippets', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Activate  {{a snippet}}', 'Code Snippets', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_action_meta(),
				'label'           => _x( 'Snippet', 'Code Snippets', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $this->helpers->get_all_code_snippets_by_status(),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return $this->helpers->get_action_common_tokens();

	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$snippet_id = isset( $parsed[ $this->get_action_meta() ] ) ? absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ) ) : 0;

		if ( ! function_exists( '\Code_Snippets\get_snippet' ) ) {
			$this->add_log_error( esc_attr_x( 'The function "Code_Snippets\get_snippet" dose not exist.', 'Code Snippets', 'uncanny-automator' ) );

			return false;
		}

		$snippet_details = \Code_Snippets\get_snippet( $snippet_id );

		if ( ! $snippet_details instanceof \Code_Snippets\Snippet ) {
			$this->add_log_error( sprintf( esc_attr_x( 'Invalid snippet id: %d.', 'Code Snippets', 'uncanny-automator' ), $snippet_id ) );

			return false;
		}

		if ( true === $snippet_details->active ) {
			$this->add_log_error( sprintf( esc_attr_x( 'The selected snippet (%s) is already active.', 'Code Snippets', 'uncanny-automator' ), $snippet_details->display_name ) );

			return false;
		}

		if ( ! function_exists( '\Code_Snippets\activate_snippet' ) ) {
			$this->add_log_error( esc_attr_x( 'The function "Code_Snippets\activate_snippet" dose not exist.', 'Code Snippets', 'uncanny-automator' ) );

			return false;
		}

		$snippet_activated = \Code_Snippets\activate_snippet( $snippet_id );

		if ( ! $snippet_activated instanceof \Code_Snippets\Snippet ) {
			$this->add_log_error( esc_attr_x( $snippet_activated, 'Code Snippets', 'uncanny-automator' ) );

			return false;
		}
		$this->hydrate_tokens( $this->helpers->parse_action_tokens( $snippet_activated ) );

		return true;
	}
}
