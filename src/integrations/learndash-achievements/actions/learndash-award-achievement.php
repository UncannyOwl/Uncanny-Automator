<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\Learndash_Achievements;

/**
 * Class LD_ACHIEVEMENTS_AWARD
 *
 * @package Uncanny_Automator\Integrations\Learndash_Achievements
 *
 * @property \Uncanny_Automator\Integrations\Learndash_Achievements\Ld_Achievements_Helpers $item_helpers
 */
class LD_ACHIEVEMENTS_AWARD extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Set up the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'LD_ACHIEVEMENTS' );
		$this->set_action_code( 'LDACHIEVEMENTS' );
		$this->set_action_meta( 'LDACHIEVEMENTS_META' );

		/* translators: %1$s is the achievement select field */
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Award {{an achievement:%1$s}} to a user', 'LearnDash', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Award {{an achievement}} to a user', 'LearnDash', 'uncanny-automator' )
		);

		$this->set_action_tokens(
			array(
				'ACHIEVEMENT_TITLE'   => array(
					'name' => esc_html_x( 'Achievement title', 'LearnDash', 'uncanny-automator' ),
					'type' => 'text',
				),
				'ACHIEVEMENT_MESSAGE' => array(
					'name' => esc_html_x( 'Achievement message', 'LearnDash', 'uncanny-automator' ),
					'type' => 'text',
				),
				'ACHIEVEMENT_POINTS'  => array(
					'name' => esc_html_x( 'Achievement points', 'LearnDash', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_action_meta(),
				'options'               => array(),
				'required'              => true,
				'label'                 => esc_html_x( 'Achievement', 'LearnDash', 'uncanny-automator' ),
				'description'           => esc_html_x( 'Select from the list of available achievements.', 'LearnDash', 'uncanny-automator' ),
				'supports_tokens'        => false,
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'achievements' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$award_id = absint( $parsed[ $this->get_action_meta() ] );

		$award = get_post( $award_id );

		if ( ! class_exists( '\\LearnDash\\Achievements\\Achievement' ) ) {
			$this->add_log_error( esc_html_x( "Error: Instance of '\\LearnDash\\Achievements\\Achievement' is not found.", 'LearnDash', 'uncanny-automator' ) );

			return false;
		}

		if ( ! method_exists( '\\LearnDash\\Achievements\\Achievement', 'store' ) ) {
			$this->add_log_error( esc_html_x( 'Error: The method `store` in `Achievement` Class is not found.', 'LearnDash', 'uncanny-automator' ) );

			return false;
		}

		$stored = \LearnDash\Achievements\Achievement::store( $award, $user_id );

		if ( false === $stored ) {
			$this->add_log_error( esc_html_x( 'There was an error encountered while giving award to the user.', 'LearnDash', 'uncanny-automator' ) );

			return false;
		}

		$this->hydrate_tokens(
			array(
				'ACHIEVEMENT_TITLE'   => get_the_title( $award_id ),
				'ACHIEVEMENT_MESSAGE' => get_post_meta( $award_id, 'achievement_message', true ),
				'ACHIEVEMENT_POINTS'  => get_post_meta( $award_id, 'points', true ),
			)
		);

		return true;
	}

}
