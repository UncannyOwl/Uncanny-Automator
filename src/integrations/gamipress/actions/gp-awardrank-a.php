<?php

namespace Uncanny_Automator;

/**
 * Class GP_AWARDRANK_A
 * @package uncanny_automator
 */
class GP_AWARDRANK_A {
	
	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'GP';
	
	private $action_code;
	private $action_meta;
	private $quiz_list;
	
	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'GPAWARDRANK';
		$this->action_meta = 'GPRANK';
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
			/* Translators: 1:Lessons*/
			'sentence'           => sprintf( __( 'Award {{a Rank:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Award {{a Rank}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => [ $this, 'award_points' ],
			'options'            => [],
			'options_group'      => [
				$this->action_meta => [
					$uncanny_automator->helpers->recipe->gamipress->options->list_gp_rank_types(
						__( 'Select an Rank Type', 'uncanny-automator' ),
						'GPRANKTYPES',
						[
							'token'        => false,
							'is_ajax'      => true,
							'target_field' => $this->action_meta,
							'endpoint'     => 'select_ranks_from_types_AWARDRANKS',
						]
					),
					$uncanny_automator->helpers->recipe->field->select_field( $this->action_meta, __( 'Select a Rank', 'uncanny-automator' ), [ '' => __( 'Select a Type Above', 'uncanny-automator' ) ] ),
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
		gamipress_update_user_rank( $user_id, $rank_id );
		$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
	}

}
