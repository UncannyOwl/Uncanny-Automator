<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Automator_Tokens
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */

namespace Uncanny_Automator;

/**
 * Class Automator_Tokens
 *
 * @package Uncanny_Automator
 */
class Automator_Tokens {
	use Recipe\Tokens;

	/**
	 * @var
	 */
	public static $instance;


	/**
	 * @return Automator_Tokens
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get value of the token
	 */
	public function get() {

	}

	/**
	 * Function to parse token {{trigger_id:trigger_code:token}}
	 *
	 * @param $trigger_id
	 * @param $trigger_code
	 * @param $token
	 * @param $args
	 */
	public function parse( $trigger_id, $trigger_code, $token, $args ) {

	}

	/**
	 * Store token in to the trigger meta table
	 */
	public function store() {

	}

	/**
	 * Human readable string of the token
	 */
	public function human_readable() {

	}

	/**
	 *
	 */
	protected function possible_tokens() {

	}

	/**
	 *
	 */
	protected function relevant_tokens() {
	}

	/**
	 * Get token data for recipe triggers
	 *
	 * @param null $triggers_meta
	 * @param null $recipe_id
	 *
	 * @return null|array
	 */
	public function trigger_tokens( $triggers_meta = null, $recipe_id = null ) {
		if ( is_null( $triggers_meta ) && is_null( $recipe_id ) ) {
			return null;
		}

		$tokens = apply_filters( 'automator_maybe_trigger_pre_tokens', array(), $triggers_meta, $recipe_id );
		//Only load these when on edit recipe page or is automator ajax is happening!
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
//		if ( ! Automator()->helpers->recipe->is_edit_page() && ! Automator()->helpers->recipe->is_rest() && ! Automator()->helpers->recipe->is_ajax() ) {
//			return $tokens;
//		}
		if ( empty( $triggers_meta ) ) {
			return $tokens;
		}

		//Add custom tokens regardless of integration / trigger code
		$filters                 = array();
		$trigger_integration     = '';
		$trigger_meta            = '';
		$trigger_value           = '';
		$ignore_metas_for_tokens = apply_filters(
			'automator_ignore_tokenify_meta',
			array(
				'INTEGRATION_NAME',
				'NUMBERCOND',
				'uap_trigger_version',
				'sentence',
				'sentence_human_readable',
				'add_action',
			)
		);
		foreach ( $triggers_meta as $meta_key => $meta_value ) {
			if ( empty( $meta_value ) ) {
				continue;
			}

			if ( in_array( $meta_key, $ignore_metas_for_tokens, false ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
				continue;
			}

			if ( in_array( strtoupper( $meta_key ), $ignore_metas_for_tokens, false ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
				continue;
			}

			if ( 'integration' === (string) $meta_key ) {
				$trigger_integration = strtolower( $meta_value );
			}

			//Ignore NUMTIMES and trigger_integration/trigger_code metas
			if ( 'NUMTIMES' !== (string) strtoupper( $meta_key ) && 'integration' !== (string) strtolower( $meta_key ) ) {
				$trigger_meta  = strtolower( $meta_key );
				$trigger_value = $meta_value;
			}

			//Deal with trigger_meta special cases
			if ( 'trigger_meta' === $meta_key ) {
				$trigger_meta  = strtolower( $meta_value );
				$trigger_value = $meta_value;
			}

			//Deal with trigger_meta special cases
			if ( 'code' === (string) $meta_key ) {
				$trigger_meta  = strtolower( $meta_value );
				$trigger_value = $meta_value;
			}

			//Add general Integration based filter, like automator_maybe_trigger_gf_tokens
			if ( ! empty( $trigger_integration ) ) {

				$filter = 'automator_maybe_trigger_' . $trigger_integration . '_tokens';
				$filter = str_replace( '__', '_', $filter );

				$filters[ $filter ] = array(
					'integration'   => strtoupper( $trigger_integration ),
					'meta'          => strtoupper( $trigger_meta ),
					'triggers_meta' => $triggers_meta,
					'recipe_id'     => $recipe_id,
				);

			}

			//Add trigger code specific filter, like automator_maybe_trigger_gf_gfforms_tokens
			if ( ! empty( $trigger_integration ) && ! empty( $triggers_meta ) ) {
				$filter = 'automator_maybe_trigger_' . $trigger_integration . '_' . $trigger_meta . '_tokens';
				$filter = str_replace( '__', '_', $filter );

				$filters[ $filter ] = array(
					'value'         => $trigger_value,
					'integration'   => strtoupper( $trigger_integration ),
					'meta'          => strtoupper( $trigger_meta ),
					'recipe_id'     => $recipe_id,
					'triggers_meta' => $triggers_meta,
				);
			}
		}

		/* Filter to add/remove custom filter */
		/** @var  $filters */
		$filters = apply_filters_deprecated(
			'automator_trigger_filters',
			array(
				$filters,
				$triggers_meta,
			),
			'3.0',
			'automator_trigger_token_filters'
		);
		$filters = apply_filters( 'automator_trigger_token_filters', $filters, $triggers_meta );

		if ( $filters ) {
			foreach ( $filters as $filter => $args ) {
				$tokens = apply_filters( $filter, $tokens, $args );
			}
		}

		if ( isset( $triggers_meta['code'] ) ) {
			$tokens = Automator()->get->trigger_tokens_from_trigger_code( $triggers_meta['code'] ) + $tokens;
		}
		// Adds the opportunity to modify final tokens list
		// (i.e., remove middle name from GF tokens list)
		//$tokens = $this->remove_duplicate_token_ids( $tokens );

		return apply_filters( 'automator_maybe_trigger_tokens', $tokens, $recipe_id );
	}
}
