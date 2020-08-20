<?php

namespace Uncanny_Automator;

/**
 * Class BO_AWARDPOINTS_A
 * @package Uncanny_Automator
 */
class BO_AWARDPOINTS_A {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BO';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'BOAWARDPOINTS';
		$this->action_meta = 'BOPOINTS';
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
			/* translators: Action - BadgeOS */
			'sentence'           => sprintf(  esc_attr__( 'Award {{a number:%1$s}} {{of a specific type of:%2$s}} points to the user', 'uncanny-automator' ), 'BOPOINTVALUE', $this->action_meta ),
			/* translators: Action - BadgeOS */
			'select_option_name' =>  esc_attr__( 'Award {{points}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => [ $this, 'award_points' ],
			'options'            => [],
			'options_group'      => [
				$this->action_meta => [
					$uncanny_automator->helpers->recipe->badgeos->options->list_bo_points_types(  esc_attr__( 'Point type', 'uncanny-automator' ), $this->action_meta, [
						'token'   => false,
						'is_ajax' => false,
					] ),
				],
				'BOPOINTVALUE'     => [
					[
						'input_type' => 'int',

						'option_code' => 'BOPOINTVALUE',
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
	public function award_points( $user_id, $action_data, $recipe_id, $args ) {

		global $uncanny_automator;

		$points_type = $action_data['meta'][ $this->action_meta ];
		$points      = $uncanny_automator->parse->text( $action_data['meta']['BOPOINTVALUE'], $recipe_id, $user_id, $args );

		$point_type_id = 0;
		$credit_types  = badgeos_get_point_types();
		if ( is_array( $credit_types ) && ! empty( $credit_types ) ) {
			foreach ( $credit_types as $point_type ) {
				if ( $point_type->post_name === $points_type ) {
					$point_type_id = $point_type->ID;
					break;
				}
			}
		}
		badgeos_award_credit( $point_type_id, absint( $user_id ), 'Award', absint( $points ), '', false, '', '' );

		$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
	}

}
