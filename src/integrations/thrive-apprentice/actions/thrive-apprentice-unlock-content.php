<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Class THRIVE_APPRENTICE_UNLOCK_CONTENT
 *
 * This action unlocks content (lesson/module) in Thrive Apprentice based on automation rules.
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_UNLOCK_CONTENT extends Action {

	/**
	 * Constant ACTION_CODE.
	 *
	 * @var string
	 */
	const ACTION_CODE = 'THRIVE_APPRENTICE_UNLOCK_CONTENT';

	/**
	 * Constant ACTION_META.
	 *
	 * @var string
	 */
	const ACTION_META = 'THRIVE_APPRENTICE_UNLOCK_CONTENT_META';

	/**
	 * Helper instance
	 *
	 * @var Thrive_Apprentice_Helpers
	 */
	protected $helper;

	/**
	 * Setup action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->helper = new Thrive_Apprentice_Helpers( false );

		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_action_code( self::ACTION_CODE );
		$this->set_action_meta( self::ACTION_META );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Lesson/Module,  %2$s: Course
				esc_html_x( 'Unlock {{a lesson/module:%1$s}} in {{a course:%2$s}} for the user', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_action_meta() . ':' . $this->get_action_meta(),
				'COURSE:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Unlock {{a lesson/module}} in {{a course}} for the user', 'Thrive Apprentice', 'uncanny-automator' )
		);

		$this->set_background_processing( false );
	}

	/**
	 * Define options
	 *
	 * @return array The options configuration.
	 */
	public function options() {
		return array(
			array(
				'option_code' => 'COURSE',
				'label'       => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->helper->get_dropdown_options_courses( false, true ),
			),
			array(
				'option_code'           => 'CONTENT_TYPE',
				'label'                 => esc_html_x( 'Content type', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'ajax'                  => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_content_type_handler',
					'listen_fields' => array( 'COURSE' ),
				),
			),
			array(
				'option_code'              => $this->get_action_meta(),
				'label'                    => esc_html_x( 'Content', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'               => 'select',
				'supports_multiple_values' => true,
				'supports_custom_value'    => true,
				'supports_tokens'          => true,
				'required'                 => true,
				'relevant_tokens'          => array(),
				'ajax'                     => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_content_handler',
					'listen_fields' => array( 'CONTENT_TYPE' ),
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token variables.
	 *
	 * @return bool True if the action was processed successfully, false on failure.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		try {
			$content_ids = json_decode( $parsed[ $this->get_action_meta() ] );

			if ( empty( $content_ids ) ) {
				throw new \Exception( esc_html_x( 'Content ID is missing.', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			// Validate content post
			$content = get_post( $content_ids );

			$customer = new \TVA_Customer( $user_id );
			foreach ( $content_ids as $id ) {
				$customer->set_drip_content_unlocked( $id );
			}

			do_action( 'tva_drip_content_unlocked_for_specific_user', $customer->get_user(), $content, array() );

			return true;
		} catch ( \Exception $e ) {
			$this->add_log_error( $e->getMessage() );
			return false;
		}
	}
}
