<?php

namespace Uncanny_Automator;

/**
 * Class WPFF_SUBFORM
 *
 * @package Uncanny_Automator
 */
class WPFF_SUBFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPFF';

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
		$this->trigger_code = 'WPFFSUBFORM';
		$this->trigger_meta = 'WPFFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-fluent-forms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Ninja Forms */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Ninja Forms */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'fluentform_before_insert_submission',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wpffform_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->wp_fluent_forms->options->list_wp_fluent_forms(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $inser_data
	 * @param $data
	 * @param $form
	 */
	public function wpffform_submit( $insert_data, $data, $form ) {

		$user_id = get_current_user_id();

		// Logged in users only
		if ( empty( $user_id ) ) {
			return;
		}
		if ( empty( $form ) ) {
			return;
		}

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$matches = $this->match_condition( $form, $data, $recipes );

		if ( ! $matches ) {
			return;
		}

		if ( ! empty( $matches ) ) {
			foreach ( $matches as $recipe_id => $match ) {
				if ( ! Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
					$args = array(
						'code'            => $this->trigger_code,
						'meta'            => $this->trigger_meta,
						'meta_key'        => $this->trigger_meta,
						'recipe_to_match' => $recipe_id,
						'ignore_post_id'  => true,
						'user_id'         => $user_id,
					);

					$result = Automator()->maybe_add_trigger_entry( $args, false );

					if ( $result ) {
						foreach ( $result as $r ) {
							if ( true === $r['result'] ) {
								if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
									//Saving form values in trigger log meta for token parsing!
									$wp_ff_args = array(
										'trigger_id'     => (int) $r['args']['trigger_id'],
										'user_id'        => $user_id,
										'trigger_log_id' => $r['args']['get_trigger_id'],
										'run_number'     => $r['args']['run_number'],
									);

									$wp_ff_args['meta_key'] = $this->trigger_meta;
									Automator()->helpers->recipe->wp_fluent_forms->extract_save_wp_fluent_form_fields( $data, $form, $wp_ff_args );

									$wp_ff_args['meta_key']   = 'WPFFENTRYID';
									$wp_ff_args['meta_value'] = $insert_data['serial_number'];
									Automator()->insert_trigger_meta( $wp_ff_args );

									$wp_ff_args['meta_key']   = 'WPFFENTRYIP';
									$wp_ff_args['meta_value'] = $insert_data['ip'];
									Automator()->insert_trigger_meta( $wp_ff_args );

									$wp_ff_args['meta_key']   = 'WPFFENTRYSOURCEURL';
									$wp_ff_args['meta_value'] = $insert_data['source_url'];
									Automator()->insert_trigger_meta( $wp_ff_args );

									$wp_ff_args['meta_key']   = 'WPFFENTRYDATE';
									$wp_ff_args['meta_value'] = maybe_serialize( date( 'Y-m-d H:i:s', strtotime( $insert_data['created_at'] ) ) );
									Automator()->insert_trigger_meta( $wp_ff_args );

									Automator()->maybe_add_trigger_entry( $args );
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param      $form_data
	 * @param      $submitted_data
	 * @param null $recipes
	 * @param null $trigger_meta
	 * @param null $trigger_code
	 * @param null $trigger_second_code
	 *
	 * @return array|bool
	 */
	public function match_condition( $form_data, $submitted_data, $recipes = null ) {

		if ( null === $recipes ) {
			return false;
		}

		$matches = array();

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				//  Validate that all needed feilds and value are set
				if (
					isset( $trigger['meta'] ) && ! empty( $trigger['meta'] )
					&& isset( $trigger['meta']['WPFFFORMS'] ) && ! empty( $trigger['meta']['WPFFFORMS'] )
					&& ( (int) $form_data->id === (int) $trigger['meta']['WPFFFORMS'] || '-1' === $trigger['meta']['WPFFFORMS'] )
				) {
					$matches[ $recipe['ID'] ] = array(
						'recipe_id' => $recipe['ID'],
					);
				}
			}
		}

		if ( ! empty( $matches ) ) {
			return $matches;
		}

		return false;
	}
}
