<?php

namespace Uncanny_Automator;

/**
 * Class BO_AWARDACHIEVEMENT_A
 * @package Uncanny_Automator
 */
class BO_AWARDACHIEVEMENT_A {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BO';

	private $action_code;
	private $action_meta;
	private $quiz_list;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'AWARDACHIEVEMENT';
		$this->action_meta = 'BOACHIEVEMENT';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {



		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link($this->action_code, 'integration/badgeos/'),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - BadgeOS */
			'sentence'           => sprintf( esc_attr__( 'Award {{an achievement:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - BadgeOS */
			'select_option_name' => esc_attr__( 'Award {{an achievement}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'award_an_achievement' ),
			'options_group'      => [
				$this->action_meta => [
					Automator()->helpers->recipe->badgeos->options->list_bo_award_types(
						esc_attr__( 'Achievement type', 'uncanny-automator' ),
						'BOAWARDTYPES',
						[
							'token'                 => false,
							'is_ajax'               => true,
							'target_field'          => $this->action_meta,
							'supports_custom_value' => false,
							'endpoint'              => 'select_achievements_from_types_BOAWARDACHIEVEMENT',
						]
					),

					Automator()->helpers->recipe->field->select_field_args( [
						'option_code'              => $this->action_meta,
						'options'                  => array(),
						/* translators: Noun */
						'label'                    => esc_attr__( 'Award', 'uncanny-automator' ),
						'required'                 => true,
						'custom_value_description' => esc_attr__( 'Award ID', 'uncanny-automator' ),
					] ),
				],
			],
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
	public function award_an_achievement( $user_id, $action_data, $recipe_id, $args ) {



		$achievement_id = $action_data['meta'][ $this->action_meta ];
		badgeos_award_achievement_to_user( absint( $achievement_id ), absint( $user_id ) );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
