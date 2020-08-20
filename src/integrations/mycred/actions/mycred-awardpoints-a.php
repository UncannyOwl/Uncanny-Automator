<?php

namespace Uncanny_Automator;

/**
 * Class MYCRED_AWARDPOINTS_A
 * @package Uncanny_Automator
 */
class MYCRED_AWARDPOINTS_A {

	/**
	 * integration code
	 * @var string
	 */
	public static $integration = 'MYCRED';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MYCREDAWARDPOINTS';
		$this->action_meta = 'MYCREDPOINTS';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		global $uncanny_automator;

		$action = [
			'author'             => $uncanny_automator->get_author_name(),
			'support_link'       => $uncanny_automator->get_author_support_link(),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - myCred */
			'sentence'           => sprintf(  esc_attr__( 'Award {{a number:%1$s}} {{of a specific type of:%2$s}} points to the user', 'uncanny-automator' ), 'MYCREDPOINTVALUE', $this->action_meta ),
			/* translators: Action - myCred */
			'select_option_name' =>  esc_attr__( 'Award {{points}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => [ $this, 'award_mycred_points' ],
			'options'            => [],
			'options_group'      => [
				$this->action_meta => [
					$uncanny_automator->helpers->recipe->mycred->options->list_mycred_points_types(  esc_attr__( 'Point type', 'uncanny-automator' ), $this->action_meta, [
						'token'   => false,
						'is_ajax' => false,
					] ),
				],
				'MYCREDPOINTVALUE' => [
					[
						'input_type' => 'int',

						'option_code' => 'MYCREDPOINTVALUE',
						'label'       =>  esc_attr__( 'Points', 'uncanny-automator' ),

						'supports_tokens' => true,
						'required'        => true,
					],
				],
			],
		];

		$uncanny_automator->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function award_mycred_points( $user_id, $action_data, $recipe_id, $args ) {

		global $uncanny_automator;

		$points_type = $action_data['meta'][ $this->action_meta ];
		$points      = $uncanny_automator->parse->text( $action_data['meta']['MYCREDPOINTVALUE'], $recipe_id, $user_id, $args );
		$reference   = $uncanny_automator->parse->text( $action_data['meta']['MYCREDPOINTS_readable'], $recipe_id, $user_id, $args );
		mycred_add( $reference, absint( $user_id ), $points, 'Added by uncanny automator action', '', '', $points_type );

		$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
	}
}