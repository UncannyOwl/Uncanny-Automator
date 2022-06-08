<?php
namespace Uncanny_Automator;

/**
 * Class AUTONAMI_ADD_USER_TAG
 *
 * @package Uncanny_Automator
 */
class AUTONAMI_ADD_USER_TAG {

	use \Uncanny_Automator\Recipe\Actions;

	public function __construct() {

		$this->helpers = new Autonami_Helpers();
		$this->setup_action();
		$this->register_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'AUTONAMI' );
		$this->set_action_code( 'ADD_USER_TAG' );
		$this->set_action_meta( 'TAG' );
		$this->set_is_pro( false );

		$this->set_support_link( $this->helpers->support_link( $this->action_code ) );

		/* translators: tag name */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{a tag:%1$s}} to the user', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_attr__( 'Add {{a tag}} to the user', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

	}

	/**
	 * Method load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$options[] = $this->helpers->get_tag_dropdown( false );

		return array( 'options' => $options );

	}

	/**
	 * Method process_action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		try {

			$user         = get_user_by( 'id', $user_id );
			$email        = $user->user_email;
			$tag_id       = absint( $action_data['meta'][ $this->action_meta ] );
			$tag_readable = $action_data['meta'][ $this->action_meta . '_readable' ];

			$this->helpers->add_tag_to_contact( $email, $tag_id, $tag_readable );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
