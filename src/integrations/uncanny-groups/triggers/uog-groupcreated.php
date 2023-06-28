<?php

namespace Uncanny_Automator;

/**
 * Class UOG_GROUPCREATED
 *
 * @package Uncanny_Automator
 */
class UOG_GROUPCREATED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UOG';

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
		$this->trigger_code = 'GROUPCREATED';
		$this->trigger_meta = 'UNCANNYGROUPS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-groups/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny Groups */
			'sentence'            => esc_attr__( 'A group is created', 'uncanny-automator' ),
			/* translators: Logged-in trigger - Uncanny Groups */
			'select_option_name'  => esc_attr__( 'A group is created', 'uncanny-automator' ),
			'action'              => array( 'uo_new_group_created', 'uo_new_group_purchased' ),
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'group_created' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $user_id
	 * @param $code
	 */
	public function group_created( $group_id, $leader_id ) {
		if ( empty( $leader_id ) ) {
			$user_id = get_current_user_id();
		} else {
			$user_id = $leader_id;
		}

		if ( empty( $user_id ) ) {
			return;
		}

		$pass_args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( isset( $args ) ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					$trigger_meta['meta_key']   = 'group_id';
					$trigger_meta['meta_value'] = maybe_serialize( $group_id );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'leader_id';
					$trigger_meta['meta_value'] = maybe_serialize( $leader_id );
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

}
