<?php

namespace Uncanny_Automator;

/**
 * Class ANON_ELEM_SUBMITFORM
 *
 * @package Uncanny_Automator
 */
class ANON_ELEM_SUBMITFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ELEM';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'ANONELEMSUBMITFORM';
		$this->trigger_meta = 'ELEMFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/elementor/' ),
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Forminator */
			'sentence'            => sprintf( esc_attr__( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Forminator */
			'select_option_name'  => esc_attr__( '{{A form}} is submitted', 'uncanny-automator' ),
			'action'              => 'elementor_pro/forms/new_record',
			'priority'            => 100,
			'type'                => 'anonymous',
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'elem_submit_form' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array
	 */
	public function load_options() {
		$options = array(
			'options' => array(
				Automator()->helpers->recipe->elementor->options->all_elementor_forms( null, $this->trigger_meta ),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param object $record
	 * @param object $object
	 */
	public function elem_submit_form( $record, $object ) {

		if ( ! $object->is_success ) {
			return;
		}

		$form_id = $record->get_form_settings( 'id' );
		if ( empty( $form_id ) ) {
			return;
		}

		$user_id    = get_current_user_id();
		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$conditions = $this->match_condition( $form_id, $recipes, $this->trigger_meta, $this->trigger_code );

		if ( ! $conditions ) {
			return;
		}

		foreach ( $conditions['recipe_ids'] as $trigger_id => $recipe_id ) {
			if ( Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
				continue;
			}
			$args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'recipe_to_match'  => $recipe_id,
				'trigger_to_match' => $trigger_id,
				'ignore_post_id'   => true,
				'user_id'          => $user_id,
			);

			$args = Automator()->process->user->maybe_add_trigger_entry( $args, false );
			do_action( 'automator_save_elementor_form_entry', $record, $recipes, $args );
			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						Automator()->process->user->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}

	/**
	 * Matching Form id because its not an integer.
	 *
	 * @param array $form_id .
	 * @param array $recipes .
	 * @param string $trigger_meta .
	 * @param string $trigger_code .
	 *
	 * @return array|bool
	 */
	public function match_condition( $form_id, $recipes = null, $trigger_meta = null, $trigger_code = null ) {

		if ( null === $recipes ) {
			return false;
		}

		$recipe_ids     = array();
		$entry_to_match = $form_id;

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) && (string) $trigger['meta'][ $trigger_meta ] === (string) $entry_to_match ) {
					$recipe_ids[ $trigger['ID'] ] = $recipe['ID'];
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}
}
