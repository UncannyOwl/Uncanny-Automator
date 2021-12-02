<?php

namespace Uncanny_Automator;

/**
 * Class WPF_USERREPUTATION
 *
 * @package Uncanny_Automator
 */
class WPF_USERREPUTATION {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPFORO';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'SETUSERREPUTATION';
		$this->action_meta = 'USERREPUTATION';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$reputation_options = array();
		$levels             = WPF()->member->levels();

		foreach ( $levels as $level ) {
			$title                                        = esc_attr__( 'Level', 'wpforo' ) . ' ' . $level . ' - ' . WPF()->member->rating( $level, 'title' );
			$reputation_options[ 'L' . strval( $level ) ] = $title;
		}

		$option = array(
			'option_code' => $this->action_meta,
			'label'       => esc_attr__( 'Reputation', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $reputation_options,
		);

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wpforo/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - wpForo */
			'sentence'           => sprintf( esc_attr__( "Set the user's reputation to {{a reputation:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - wpForo */
			'select_option_name' => esc_attr__( "Set the user's reputation to {{a reputation}}", 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'set_reputation' ),
			'options'            => array(
				$option,
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function set_reputation( $user_id, $action_data, $recipe_id, $args ) {

		$reputation_id = absint( str_replace( 'L', '', $action_data['meta'][ $this->action_meta ] ) );
		$points        = WPF()->member->rating( $reputation_id, 'points' );

		$args = array( 'rank' => $points );
		WPF()->member->update_profile_fields( $user_id, $args, false );
		WPF()->member->reset( $user_id );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
