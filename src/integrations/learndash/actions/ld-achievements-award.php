<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class LD_ACHIEVEMENTS_AWARD
 *
 * @package Uncanny_Automator
 */
class LD_ACHIEVEMENTS_AWARD {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';
	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->action_code = 'LDACHIEVEMENTS';
		$this->action_meta = 'LDACHIEVEMENTS_META';

		if ( class_exists( '\LearnDash_Achievements' ) ) {
			$this->define_action();
		}

	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learndash/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in trigger - Uncanny Groups */
			'sentence'           => sprintf( esc_attr__( 'Award {{an achievement:%1$s}} to a user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Uncanny Groups */
			'select_option_name' => esc_attr__( 'Award {{an achievement}} to a user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 0,
			'execution_function' => array( $this, 'award_achievement' ),
			'options_group'      => array(
				$this->action_meta => array(
					array(
						'input_type'            => 'select',
						'option_code'           => $this->action_meta,
						'options'               => $this->get_achievements(),
						'required'              => true,
						'label'                 => esc_attr__( 'Achievements', 'uncanny-automator' ),
						'description'           => esc_attr__( 'Select from the list of available achievements.', 'uncanny-automator' ),
						'supports_token'        => false,
						'supports_custom_value' => false,
					),
				),
			),

		);

		Automator()->register->action( $action );
	}

	/**
	 * Awards the achievement.
	 */
	public function award_achievement( $user_id, $action_data, $recipe_id, $args ) {

		$award_id = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

		$award = get_post( $award_id );

		if ( class_exists( '\LearnDash\Achievements\Achievement' ) ) {

			if ( method_exists( '\LearnDash\Achievements\Achievement', 'store' ) ) {

				$stored = \LearnDash\Achievements\Achievement::store( $award, $user_id );

				if ( false === $stored ) {

					$error_message = esc_attr__( 'There was an error encountered while giving award to the user.', 'uncanny-automator' );

					$action_data['complete_with_errors'] = true;

					Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

				}

				Automator()->complete->action( $user_id, $action_data, $recipe_id );

				return;

			}

			$error_message = esc_attr__( 'Error: The method `store` in `Achievement` Class is not found.', 'uncanny-automator' );

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

		} else {

			$error_message = esc_attr__( "Error: Instance of '\LearnDash\Achievements\Achievement' is not found.", 'uncanny-automator' );

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
		}

	}

	protected function get_achievements() {

		$args = array(
			'post_type'      => 'ld-achievement',
			'posts_per_page' => 99,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$achievements = Automator()->helpers->recipe->options->wp_query( $args, '', esc_attr__( 'Any course', 'uncanny-automator' ) );

		return $achievements;
	}
}
