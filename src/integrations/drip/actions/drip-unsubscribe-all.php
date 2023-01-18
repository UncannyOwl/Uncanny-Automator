<?php

namespace Uncanny_Automator;

/**
 * Class DRIP_UNSUBSCRIBE_ALL
 *
 * @package Uncanny_Automator
 */
class DRIP_UNSUBSCRIBE_ALL {

	use Recipe\Actions;

	/**
	 * @var Drip_Functions
	 */
	private $functions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->functions = new Drip_Functions();

		$this->setup_action();
	}


	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->set_integration( 'DRIP' );
		$this->set_action_code( 'UNSUBSCRIBE_ALL' );
		$this->set_action_meta( 'EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/drip/' ) );
		$this->set_requires_user( false );
		/* translators: 1. tag, 2. email address */
		$this->set_sentence( sprintf( esc_attr__( 'Unsubscribe {{a subscriber:%1$s}} from all the mailings', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Unsubscribe {{a subscriber}} from all the mailings', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$email = array(
			'option_code' => 'EMAIL',
			'label'       => __( 'Email', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		return array(
			'options_group' => array(
				$this->action_meta => array(
					$email,
				),
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = Automator()->parse->text( $action_data['meta']['EMAIL'], $recipe_id, $user_id, $args );

		$error_msg = '';

		try {

			$response = $this->functions->unsubscribe_all( $email );

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['complete_with_errors'] = true;
		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_msg );
	}
}
