<?php

namespace Uncanny_Automator;

/**
 * Class FUSION_SETUSERTAG
 *
 * @package Uncanny_Automator
 */
class WF_SETUSERTAG {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WF';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'SETUSERTAG';
		$this->action_meta = 'SETUSERVAL';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wp-fusion/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - WP Fusion */
			'sentence'           => sprintf( esc_attr__( 'Add {{a tag:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WP Fusion */
			'select_option_name' => esc_attr__( 'Add {{a tag}} to the user', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'set_user_tag' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );

	}

	public function load_options() {

		$options = array(
			'options' => array(
				Wp_Fusion_Helpers::fusion_tags( '', $this->action_meta ),
			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function set_user_tag( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! empty( $user_id ) ) {
			// is the use in DB?
			$contact_id = wp_fusion()->user->get_contact_id( $user_id, true );

			// if not lets add then
			if ( false === $contact_id ) {

				wp_fusion()->user->user_register( $user_id );
			}
			// get tag yo set
			$tag = sanitize_text_field( $action_data['meta'][ $this->action_meta ] );

			// us get_tag_id to id the real ID or return the tag so that this works with all CMS
			$tag = wp_fusion()->user->get_tag_id( $tag );

			$current_tags = wp_fusion()->user->get_tags( $user_id );

			// check we don't have the tag
			if ( ! in_array( $tag, $current_tags, true ) ) {
				// add tag
				wp_fusion()->user->apply_tags( array( $tag ), $user_id );
			}
		} else {
			$error_msg = Automator()->error_message->get( 'not-logged-in' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
