<?php

namespace Uncanny_Automator;

/**
 * Class BO_AWARDRANK_A
 * @package Uncanny_Automator
 */
class BO_AWARDRANK_A {

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
		$this->action_code = 'BOAWARDRANK';
		$this->action_meta = 'BORANK';
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
			'sentence'           => sprintf(  esc_attr__( 'Award {{a rank:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - BadgeOS */
			'select_option_name' =>  esc_attr__( 'Award {{a rank}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => [ $this, 'award_points' ],
			'options'            => [],
			'options_group'      => [
				$this->action_meta => [
					$uncanny_automator->helpers->recipe->badgeos->options->list_bo_rank_types(
						'',
						'BORANKTYPES',
						[
							'token'        => false,
							'is_ajax'      => true,
							'target_field' => $this->action_meta,
							'endpoint'     => 'select_ranks_from_types_BOAWARDRANKS',
						]
					),

					$uncanny_automator->helpers->recipe->field->select_field_args([
						'option_code' => $this->action_meta,
						'options'     => [],
						/* translators: Noun */
						'label'       => esc_attr__( 'Rank', 'uncanny-automator' ),
						'required'    => true,
						'custom_value_description' => esc_attr__( 'Rank ID', 'uncanny-automator' )
					]),
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
	public function award_points( $user_id, $action_data, $recipe_id ) {

		global $uncanny_automator;

		$rank_id = $action_data['meta'][ $this->action_meta ];
		badgeos_update_user_rank( [
			'user_id' => $user_id,
			'rank_id' => $rank_id,
		] );

		$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
	}

}
