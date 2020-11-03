<?php

namespace Uncanny_Automator;

/**
 * Class WPCW_MODULECOMPLETED
 * @package Uncanny_Automator
 */
class WPCW_MODULECOMPLETED {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPCW';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPCWMODULECOMPLETED';
		$this->trigger_meta = 'WPCW_MODULE';
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
			/* translators: Logged-in trigger - WP Courseware */
			'sentence'            => sprintf(  esc_attr__( 'A user completes {{a module:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WP Courseware */
			'select_option_name'  =>  esc_attr__( 'A user completes {{a module}}', 'uncanny-automator' ),
			'action'              => 'wpcw_user_completed_module',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wpcw_module_completed' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->wp_courseware->options->all_wpcw_modules(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $unit_id
	 * @param $parent   AssociatedParentData
	 */
	public function wpcw_module_completed( $user_id, $unit_id, $parent ) {

		if ( empty( $user_id ) ) {
			return;
		}

		global $uncanny_automator;

		$module_id = $parent->parent_module_id;

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $module_id ),
			'user_id' => $user_id,
		];
		$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					$trigger_meta = [
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					];

					$trigger_meta['meta_key']   = $this->trigger_meta;
					$trigger_meta['meta_value'] = $module_id;
					$uncanny_automator->insert_trigger_meta( $trigger_meta );
					$uncanny_automator->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
