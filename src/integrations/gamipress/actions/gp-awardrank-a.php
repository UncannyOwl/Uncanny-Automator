<?php

namespace Uncanny_Automator;

/**
 * Class GP_AWARDRANK_A
 * @package Uncanny_Automator
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



		$action = [
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/gamipress/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - GamiPress */
			'sentence'           => sprintf( esc_attr__( 'Award {{a rank:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - GamiPress */
			'select_option_name' => esc_attr__( 'Award {{a rank}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => [ $this, 'award_points' ],
			'options'            => array(),
			'options_group'      => [
				$this->action_meta => [
					Automator()->helpers->recipe->gamipress->options->list_gp_rank_types(
						'',
						'GPRANKTYPES',
						[
							'token'        => false,
							'is_ajax'      => true,
							'target_field' => $this->action_meta,
							'endpoint'     => 'select_ranks_from_types_AWARDRANKS',
						]
					),

					Automator()->helpers->recipe->field->select_field_args( [
						'option_code'              => $this->action_meta,
						'options'                  => array(),
						/* translators: Noun */
						'label'                    => esc_attr__( 'Rank', 'uncanny-automator' ),
						'required'                 => true,
						'custom_value_description' => esc_attr__( 'Rank ID', 'uncanny-automator' ),
					] ),
				],
			],
		];

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function award_points( $user_id, $action_data, $recipe_id, $args ) {



		$rank_id = $action_data['meta'][ $this->action_meta ];
		gamipress_update_user_rank( $user_id, $rank_id );
		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
