<?php
/**
 * Created by PhpStorm.
 * User: Huma
 * Date: 6/24/2020
 * Time: 10:25 PM
 */

namespace Uncanny_Automator;

class MYCRED_AWARDBADGE_A {

	/**
	 * integration code
	 *
	 * @var string
	 */
	public static $integration = 'MYCRED';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MYCREDAWARDBADGE';
		$this->action_meta = 'MYCREDBADGE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/mycred/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - myCred */
			'sentence'           => sprintf( esc_attr__( 'Award {{a badge:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - myCred */
			'select_option_name' => esc_attr__( 'Award {{a badge}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'award_mycred_badge' ),
			'options'            => array(),
			'options_group'      => array(
				$this->action_meta => array(
					/* translators: Noun */
					Automator()->helpers->recipe->mycred->options->list_mycred_badges(
						esc_attr__( 'Badge', 'uncanny-automator' ),
						$this->action_meta,
						array(
							'token'        => false,
							'is_ajax'      => true,
							'target_field' => $this->action_meta,
						)
					),
				),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function award_mycred_badge( $user_id, $action_data, $recipe_id, $args ) {

		$badge_id = $action_data['meta'][ $this->action_meta ];

		mycred_assign_badge_to_user( absint( $user_id ), absint( $badge_id ) );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
