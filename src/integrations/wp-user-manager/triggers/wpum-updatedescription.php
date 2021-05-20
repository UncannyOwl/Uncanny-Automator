<?php

namespace Uncanny_Automator;

/**
 * Class WPUM_UPDATEDESCRIPTION
 * @package Uncanny_Automator
 */
class WPUM_UPDATEDESCRIPTION {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPUSERMANAGER';

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
		$this->trigger_code = 'WPUMUSERDESCRIPTION';
		$this->trigger_meta = 'WPUMDESCRIPTIONUPDATED';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {


		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-user-manager/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP User Manager */
			'sentence'            => __( 'A user updates their profile description', 'uncanny-automator' ),
			/* translators: Logged-in trigger - WP User Manager */
			'select_option_name'  => __( 'A user updates their profile description', 'uncanny-automator' ),
			'action'              => 'wpum_before_user_update',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wpum_description_update' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $obj
	 * @param $values
	 * @param $updated_id
	 */
	public function wpum_description_update( $obj, $values, $updated_id ) {


		if ( 0 === absint( $updated_id ) ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$user_data = get_userdata( $updated_id );

		if ( isset( $values['account']['user_description'] ) && $values['account']['user_description'] == $user_data->description ) {
			return;
		}

		$pass_args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $updated_id,
			'ignore_post_id' => true,
		];

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					$trigger_meta = [
						'user_id'        => $updated_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					];

					foreach ( $values['account'] as $key => $value ) {
						$trigger_meta['meta_key']   = $key;
						$trigger_meta['meta_value'] = maybe_serialize( $value );
						Automator()->insert_trigger_meta( $trigger_meta );
					}

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
