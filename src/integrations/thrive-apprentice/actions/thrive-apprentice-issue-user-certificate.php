<?php
namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

use Uncanny_Automator\Recipe\Action;

/**
 * Class THRIVE_APPRENTICE_ISSUE_USER_CERTIFICATE
 *
 * Handles the action for issuing a certificate to a user for a Thrive Apprentice course
 *
 * @package Uncanny_Automator
 * @author Uncanny Automator
 */
class THRIVE_APPRENTICE_ISSUE_USER_CERTIFICATE extends Action {

	/**
	 * Action code constant
	 *
	 * @var string
	 */
	const ACTION_CODE = 'THRIVE_APPRENTICE_ISSUE_USER_CERTIFICATE';

	/**
	 * Action meta constant
	 *
	 * @var string
	 */
	const ACTION_META = 'THRIVE_APPRENTICE_ISSUE_USER_CERTIFICATE_META';

	/**
	 * Helper instance
	 *
	 * @var Thrive_Apprentice_Helpers
	 */
	protected $helper;

	/**
	 * Setup action configurations
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
				// translators:  %1$s: Course
				esc_html_x( 'Issue a certificate for {{a course:%1$s}} for the user', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Issue a certificate for {{a course}} for the user', 'Thrive Apprentice', 'uncanny-automator' )
		);

		$this->set_background_processing( false );
	}

	/**
	 * Define available options for the action
	 *
	 * @return array The available action options
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'required'        => true,
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $this->helper->get_dropdown_options_courses( false, true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Process the action with validation and error handling
	 *
	 * @param int   $user_id      The user ID
	 * @param array $action_data  The action configuration data
	 * @param int   $recipe_id    The recipe ID
	 * @param array $args         Additional arguments
	 * @param array $parsed       Parsed data
	 *
	 * @return bool|null True on success, false on failure, null on do nothing
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		try {
			// Validate course ID
			if ( ! isset( $parsed[ $this->get_action_meta() ] ) ) {
				throw new \Exception( esc_html_x( 'Course ID is missing', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			$course_id = absint( $parsed[ $this->get_action_meta() ] );
			if ( empty( $course_id ) ) {
				throw new \Exception( esc_html_x( 'Invalid course ID provided', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			// Issue certificate using helper
			$result = $this->helper->issue_certificate( $course_id, $user_id );

			if ( is_wp_error( $result ) ) {
				throw new \Exception( esc_html_x( $result->get_error_message(), 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			$this->hydrate_tokens(
				array(
					'CERTIFICATE_NUMBER' => esc_html( $result['certificate_number'] ),
					'CERTIFICATE_URL'    => esc_url( $result['certificate_url'] ),
					'COURSE_ID'          => ! empty( $result['course_data'] ) ? esc_html( $result['course_data']->id ) : '',
					'COURSE_TITLE'       => ! empty( $result['course_data'] ) ? esc_html( $result['course_data']->name ) : '',
					'COURSE_URL'         => ! empty( $result['course_data'] ) ? esc_url( $result['course_data']->preview_url ) : '',
					'COURSE_AUTHOR'      => ! empty( $result['course_data'] ) ? esc_html( $result['course_data']->author->name ) . '(' . esc_html( $result['course_data']->author->email ) . ')' : '',
				)
			);

			return true;

		} catch ( \Exception $e ) {
			$this->add_log_error( esc_html_x( $e->getMessage(), 'Thrive Apprentice', 'uncanny-automator' ) );
			return false;
		}
	}

	/**
	 * Define tokens.
	 *
	 * @return array The defined tokens
	 */
	public function define_tokens() {
		return array(
			'COURSE_ID'          => array(
				'name'      => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_TITLE'       => array(
				'name'      => esc_html_x( 'Course Title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course Title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_AUTHOR'      => array(
				'name'      => esc_html_x( 'Course Author', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_AUTHOR',
				'tokenName' => esc_html_x( 'Course Author', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'CERTIFICATE_NUMBER' => array(
				'name'      => esc_html_x( 'Certificate Number', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'CERTIFICATE_NUMBER',
				'tokenName' => esc_html_x( 'Certificate Number', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'CERTIFICATE_URL'    => array(
				'name'      => esc_html_x( 'Certificate URL', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'CERTIFICATE_URL',
				'tokenName' => esc_html_x( 'Certificate URL', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_URL'         => array(
				'name'      => esc_html_x( 'Course URL', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'COURSE_URL',
				'tokenName' => esc_html_x( 'Course URL', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);
	}
}
