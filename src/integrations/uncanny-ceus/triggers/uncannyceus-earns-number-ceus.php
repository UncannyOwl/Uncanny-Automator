<?php

namespace Uncanny_Automator;

/**
 * Class UC_CODEREDEEMED
 *
 * @package Uncanny_Automator
 */
class UNCANNYCEUS_EARNS_NUMBER_CEUS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCEUS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		// The hook is only available on or after CEU version 3.0.7
		$version = \uncanny_ceu\Utilities::get_version();
		if ( version_compare( $version, '3.0.6', '>' ) ) {
			add_filter( 'automator_maybe_parse_token', array( $this, 'tokens' ), 20, 6 );
			$this->trigger_code = 'EARNSCEUSNUMBER';
			$this->trigger_meta = 'AMOUNTSCEUS';
			$this->define_trigger();
		}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-continuing-education-credits-for-learndash/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny CEUs. 1. Credit designation label (plural) */
			'sentence'            => sprintf( esc_attr__( 'A user earns {{a number of:%1$s}} %2$s', 'uncanny-automator' ), $this->trigger_meta, $credit_designation_label_plural ),
			/* translators: Logged-in trigger - Uncanny CEUs. 1. Credit designation label (plural) */
			'select_option_name'  => sprintf( esc_attr__( 'A user earns {{a number of}} %1$s', 'uncanny-automator' ), $credit_designation_label_plural ),
			'action'              => 'ceus_after_updated_user_ceu_record',
			'priority'            => 20,
			'accepted_args'       => 7,
			'validation_function' => array( $this, 'updated_user_ceu_record' ),
			'options'             => array(
				array(
					'option_code'     => $this->trigger_meta,
					/* translators: Uncanny CEUs. 1. Credit designation label (plural) */
					'label'           => sprintf( esc_attr__( 'Number of %1$s', 'uncanny-automator' ), $credit_designation_label_plural ),
					'input_type'      => 'float',
					'validation_type' => 'integer',
					'required'        => true,
					'relevant_tokens' => array(
						$this->trigger_meta            => sprintf( esc_attr__( '%1$s amount', 'uncanny-automator' ), $credit_designation_label_plural ),
						$this->trigger_meta . '_title' => sprintf( esc_attr__( 'Course or %1$s title', 'uncanny-automator' ), $credit_designation_label_plural ),
						$this->trigger_meta . '_date'  => esc_attr__( 'Date awarded', 'uncanny-automator' ),
					),
				),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * @param $current_user
	 * @param $is_manual_creation
	 * @param $completion_date
	 * @param $current_course_id
	 * @param $current_course_title
	 * @param $course_slug
	 * @param $ceu_value
	 */
	public function updated_user_ceu_record( $current_user, $is_manual_creation, $completion_date, $current_course_id, $current_course_title, $course_slug, $ceu_value ) {

		$ceu_value = (float) $ceu_value;

		if ( ! $ceu_value ) {
			return;
		}

		// Get all recipes that have this trigger
		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		// Get the specific WPFFORUMID meta data from the recipes
		$require_ceu_amount = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		// Loop through recipe
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_id = $trigger['ID'];
				$ceu_amount = $require_ceu_amount[ $recipe_id ][ $trigger_id ];

				if ( $ceu_value === (float) $ceu_amount ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $current_user->ID,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							$trigger_meta = array(
								'user_id'        => $current_user->ID,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							);

							$trigger_meta['meta_key']   = $this->trigger_meta;
							$trigger_meta['meta_value'] = maybe_serialize( $ceu_value );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = $this->trigger_meta . '_title';
							$trigger_meta['meta_value'] = maybe_serialize( $current_course_title );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = $this->trigger_meta . '_date';
							$trigger_meta['meta_value'] = maybe_serialize( $completion_date );
							Automator()->insert_trigger_meta( $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( $pieces ) {
			if (
			in_array( $this->trigger_code, $pieces, true )
			) {

				if ( ! absint( $user_id ) ) {
					return $value;
				}

				if ( ! absint( $recipe_id ) ) {
					return $value;
				}

				$replace_pieces = $replace_args['pieces'];
				$trigger_log_id = $replace_args['trigger_log_id'];
				$run_number     = $replace_args['run_number'];
				$user_id        = $replace_args['user_id'];
				$trigger_id     = absint( $replace_pieces[0] );

				// Verb can be found from trigger meta
				if ( in_array( $this->trigger_meta, $pieces ) ) {
					$value = Automator()->get->maybe_get_meta_value_from_trigger_log( $this->trigger_meta, $trigger_id, $trigger_log_id, $run_number, $user_id );

					return $value;
				}

				// Verb can be found from trigger meta
				if ( in_array( $this->trigger_meta . '_title', $pieces ) ) {
					$value = Automator()->get->maybe_get_meta_value_from_trigger_log( $this->trigger_meta . '_title', $trigger_id, $trigger_log_id, $run_number, $user_id );

					return $value;
				}

				// Verb can be found from trigger meta
				if ( in_array( $this->trigger_meta . '_date', $pieces ) ) {
					$value = Automator()->get->maybe_get_meta_value_from_trigger_log( $this->trigger_meta . '_date', $trigger_id, $trigger_log_id, $run_number, $user_id );

					return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), absint( $value ) );
				}
			}
		}

		return $value;
	}
}
