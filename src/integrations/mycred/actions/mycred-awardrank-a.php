<?php

namespace Uncanny_Automator;

/**
 * Class MYCRED_AWARDRANK_A
 * @package Uncanny_Automator
 */
class MYCRED_AWARDRANK_A {

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
		$this->action_code = 'MYCREDAWARDRANK';
		$this->action_meta = 'MYCREDRANK';
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
			'sentence'           => sprintf(  esc_attr__( 'Award {{a rank:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - myCred */
			'select_option_name' =>  esc_attr__( 'Award {{a rank}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => [ $this, 'award_mycred_ranks' ],
			'options'            => [],
			'options_group'      => [
				$this->action_meta => [
					/* translators: Noun */
					$uncanny_automator->helpers->recipe->mycred->options->list_mycred_rank_types(
						 esc_attr__( 'Rank', 'uncanny-automator' ),
						$this->action_meta,
						[
							'token'        => false,
							'is_ajax'      => true,
							'target_field' => $this->action_meta
						]
					)
				]
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
	public function award_mycred_ranks( $user_id, $action_data, $recipe_id, $args ) {
		global $uncanny_automator;

		$rank_id = $action_data['meta'][ $this->action_meta ];

		$rank_detail = mycred_get_rank( $rank_id );
		mycred_save_users_rank( $user_id, $rank_id, $rank_detail->point_type->cred_id );

		$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
	}
}