<?php

namespace Uncanny_Automator;

/**
 * Class ANON_GF_SUBFIELD
 *
 * @package Uncanny_Automator
 */
class ANON_GF_SUBFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GF';

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
		$this->trigger_code = 'ANONGFSUBFORM';
		$this->trigger_meta = 'ANONGFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ),
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Anonymous trigger - Gravity Forms */
			'sentence'            => sprintf( __( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - Gravity Forms */
			'select_option_name'  => __( '{{A form}} is submitted', 'uncanny-automator' ),
			'action'              => 'gform_after_submission',
			'type'                => 'anonymous',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'gform_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->gravity_forms->options->list_gravity_forms( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $entry
	 * @param $form
	 */
	public function gform_submit( $entry, $form ) {
		if ( empty( $entry ) ) {
			return;
		}
		$recipes       = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_form = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		if ( empty( $recipes ) ) {
			return;
		}
		if ( empty( $required_form ) ) {
			return;
		}
		$form_id            = $form['id'];
		$user_id            = get_current_user_id();
		$matched_recipe_ids = array();
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				if ( ! isset( $required_form[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_form[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				if ( intval( '-1' ) === intval( $required_form[ $recipe_id ][ $trigger_id ] ) || (int) $form_id === (int) $required_form[ $recipe_id ][ $trigger_id ] ) {
					$matched_recipe_ids[ $recipe_id ] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}
		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'ignore_post_id'   => true,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'user_id'          => $user_id,
			);

			$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

			if ( ! empty( $args ) ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						$trigger_meta = array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						);

						$trigger_meta['meta_key']   = 'GFENTRYID';
						$trigger_meta['meta_value'] = $entry['id'];
						Automator()->insert_trigger_meta( $trigger_meta );

						$trigger_meta['meta_key']   = 'GFUSERIP';
						$trigger_meta['meta_value'] = maybe_serialize( $entry['ip'] );
						Automator()->insert_trigger_meta( $trigger_meta );

						$trigger_meta['meta_key']   = 'GFENTRYDATE';
						$trigger_meta['meta_value'] = maybe_serialize( \GFCommon::format_date( $entry['date_created'], false, 'Y/m/d' ) );
						Automator()->insert_trigger_meta( $trigger_meta );

						$trigger_meta['meta_key']   = 'GFENTRYSOURCEURL';
						$trigger_meta['meta_value'] = maybe_serialize( $entry['source_url'] );
						Automator()->insert_trigger_meta( $trigger_meta );

						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}
