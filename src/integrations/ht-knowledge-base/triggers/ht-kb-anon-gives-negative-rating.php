<?php

namespace Uncanny_Automator\Integrations\Ht_Knowledge_Base;

/**
 * Class HT_KB_ANON_GIVES_NEGATIVE_RATING
 *
 * @package Uncanny_Automator
 */
class HT_KB_ANON_GIVES_NEGATIVE_RATING extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'HT_KB' );
		$this->set_trigger_code( 'HT_KB_ANON_NEGATIVE_RATING' );
		$this->set_trigger_meta( 'HT_KB_ARTICLES' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Heroic Knowledge Base
		$this->set_sentence( sprintf( esc_attr_x( '{{An article:%1$s}} receives a negative rating', 'Heroic Knowledge Base', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( '{{An article}} receives a negative rating', 'Heroic Knowledge Base', 'uncanny-automator' ) );
		$this->add_action( 'ht_voting_vote_post_action', 20, 3 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->select(
				array(
					'option_code'     => $this->get_trigger_meta(),
					'label'           => esc_attr_x( 'Article', 'Heroic Knowledge Base', 'uncanny-automator' ),
					// Load the options from the helpers file
					'options'         => $this->helpers->get_all_ht_kb_articles( true ),
					'relevant_tokens' => array(),
				)
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		list( $object, $article_id, $direction ) = $hook_args;

		// Only positive voting
		if ( 'down' !== $direction ) {
			return false;
		}

		$selected_article_id = $trigger['meta'][ $this->get_trigger_meta() ];

		if ( intval( '-1' ) === intval( $selected_article_id ) || (int) $article_id === (int) $selected_article_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$article_tokens = $this->helpers->common_tokens_for_article( 'anon' );

		return array_merge( $tokens, $article_tokens );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		return $this->helpers->parse_common_token_values( $hook_args );

	}
}
