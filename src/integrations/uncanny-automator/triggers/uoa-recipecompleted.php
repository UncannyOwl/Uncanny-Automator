<?php

namespace Uncanny_Automator;

/**
 * Class UOA_RECIPECOMPLETED
 * @package Uncanny_Automator
 */
class UOA_RECIPECOMPLETED {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'UOA';

	/**
	 * @var string
	 */
	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'UOARECIPES';
		$this->trigger_meta = 'UOARECIPE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Uncanny Automator */
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a recipe:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Uncanny Automator */
			'select_option_name'  => esc_attr__( 'A user completes {{a recipe}} {{a number of}} time(s)', 'uncanny-automator' ),
			'action'              => 'uap_recipe_completed',
			'priority'            => 99,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'on_completion' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->uncanny_automator->options->get_recipes(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $recipe_id
	 * @param $user_id
	 * @param $recipe_log_id
	 * @param $args
	 */
	public function on_completion( $recipe_id, $user_id, $recipe_log_id, $args ) {

		global $uncanny_automator;

		global $wpdb;
		// get recipe actions
		$table_name = $wpdb->prefix . 'uap_action_log';
		$q          = "SELECT automator_action_id FROM $table_name WHERE automator_recipe_log_id = {$recipe_log_id} AND error_message != ''";
		$errors     = $wpdb->get_results( $q );

		if ( ! empty( $errors ) ) {
			// bail early
			return;
		}
		if ( empty( $errors ) ) {

			$args = [
				'code'           => $this->trigger_code,
				'meta'           => $this->trigger_meta,
				'user_id'        => $user_id,
				'post_id'        => $recipe_id,
			];

			$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );
			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {

						$recipe = get_post( $recipe_id );

						if ( $recipe ) {

							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOARECIPES_recipe_id',
									'meta_value'     => $recipe->ID,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOARECIPES_recipe_title',
									'meta_value'     => $recipe->post_title,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOARECIPES_recipe_edit_link',
									'meta_value'     => get_edit_post_link( $recipe->ID ),
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOARECIPES_recipe_log_url',
									'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOARECIPES_action_log_url',
									'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
						}
						$uncanny_automator->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}