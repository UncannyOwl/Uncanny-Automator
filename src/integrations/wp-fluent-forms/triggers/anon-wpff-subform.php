<?php

namespace Uncanny_Automator;

/**
 * Class ANON_WPFF_SUBFIELD
 *
 * @package Uncanny_Automator
 */
class ANON_WPFF_SUBFORM {

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
		$this->trigger_code = 'ANONWPFFSUBFORM';
		$this->trigger_meta = 'ANONWPFFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-fluent-forms/' ),
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Anonymous trigger - Fluent Forms */
			'sentence'            => sprintf( __( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - Fluent Forms */
			'select_option_name'  => __( '{{A form}} is submitted', 'uncanny-automator' ),
			'action'              => 'fluentform_before_insert_submission',
			'type'                => 'anonymous',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wpffform_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->wp_fluent_forms->options->list_wp_fluent_forms( null, $this->trigger_meta ),
			),
		);
		Automator()->register->trigger( $trigger );
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

		if ( empty( $form ) ) {
			return;
		}
		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$matches = $this->match_condition( $form, $data, $recipes );

		if ( ! $matches ) {
			return;
		}

		foreach ( $matches as $trigger_id => $match ) {
			if ( Automator()->is_recipe_completed( $match['recipe_id'], $user_id ) ) {
				continue;
			}
			$args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'meta_key'         => $this->trigger_meta,
				'recipe_to_match'  => $match['recipe_id'],
				'trigger_to_match' => $trigger_id,
				'ignore_post_id'   => true,
				'user_id'          => $user_id,
			);

			$result = Automator()->process->user->maybe_add_trigger_entry( $args, false );

			if ( $result ) {
				foreach ( $result as $r ) {
					if ( true === $r['result'] ) {
						if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
							//Saving form values in trigger log meta for token parsing!
							$wp_ff_args = array(
								'code'           => $this->trigger_code,
								'meta'           => $this->trigger_meta,
								'post_id'        => absint( $form->id ),
								'trigger_id'     => (int) $r['args']['trigger_id'],
								'user_id'        => $user_id,
								'trigger_log_id' => $r['args']['get_trigger_id'],
								'run_number'     => $r['args']['run_number'],
							);

							$wp_ff_args['meta_key'] = $this->trigger_meta;
							Automator()->helpers->recipe->wp_fluent_forms->extract_save_wp_fluent_form_fields( $data, $form, $wp_ff_args );

							$wp_ff_args['meta_key']   = $this->trigger_meta . '_ID';
							$wp_ff_args['meta_value'] = absint( $form->id );
							Automator()->insert_trigger_meta( $wp_ff_args );

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

							Automator()->process->user->maybe_trigger_complete( $r['args'] );
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
					&& isset( $trigger['meta']['ANONWPFFFORMS'] ) && ! empty( $trigger['meta']['ANONWPFFFORMS'] )
					&& ( (int) $form_data->id === (int) $trigger['meta']['ANONWPFFFORMS'] || '-1' === $trigger['meta']['ANONWPFFFORMS'] )
				) {
					$matches[ $trigger['ID'] ] = array(
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
