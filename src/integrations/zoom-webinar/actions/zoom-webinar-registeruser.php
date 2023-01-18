<?php

namespace Uncanny_Automator;

/**
 * Class ZOOM_WEBINAR_REGISTERUSER
 *
 * @package Uncanny_Automator
 */
class ZOOM_WEBINAR_REGISTERUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ZOOMWEBINAR';

	private $action_code;
	private $action_meta;
	private $helpers;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ZOOMWEBINARREGISTERUSER';
		$this->action_meta = 'ZOOMWEBINAR';
		$this->helpers     = new Zoom_Webinar_Helpers();
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/zoom/' ),
			'is_pro'                => false,
			//'is_deprecated'      => true,
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			/* translators: Webinar topic */
			'sentence'              => sprintf( __( 'Add the user to {{a webinar:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => __( 'Add the user to {{a webinar}}', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'execution_function'    => array( $this, 'zoom_webinar_register_user' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
			'buttons'               => array(
				array(
					'show_in'     => $this->action_meta,
					'text'        => __( 'Get webinar questions', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => 'uap_zoom_get_webinar_questions',
					'modules'     => array( 'modal', 'markdown' ),
				),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$account_users_field = array(
			'option_code'           => 'ZOOMUSER',
			'label'                 => __( 'Account user', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'is_ajax'               => true,
			'endpoint'              => 'uap_zoom_api_get_webinars',
			'fill_values_in'        => $this->action_meta,
			'options'               => $this->helpers->get_account_user_options(),
			'relevant_tokens'       => array(),
			'supports_custom_value' => false,
		);

		$user_webinars_field = array(
			'option_code'           => $this->action_meta,
			'label'                 => __( 'Webinar', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_tokens'       => true,
			'supports_custom_value' => true,
			'is_ajax'               => true,
			'endpoint'              => 'uap_zoom_api_get_webinar_occurrences',
			'fill_values_in'        => 'OCCURRENCES',
		);

		$webinar_occurrences_field = array(
			'option_code'              => 'OCCURRENCES',
			'label'                    => __( 'Occurrences', 'uncanny-automator' ),
			'input_type'               => 'select',
			'required'                 => false,
			'options'                  => array(),
			'supports_tokens'          => true,
			'supports_custom_value'    => true,
			'supports_multiple_values' => true,
		);

		$option_fileds = array(
			$account_users_field,
			$user_webinars_field,
			$webinar_occurrences_field,
			$this->helpers->get_webinar_questions_repeater(),
		);

		//Don't show the user dropdown to old credentials so it's easier to test the update
		if ( $this->helpers->jwt_mode() ) {
			$option_fileds = array(
				Automator()->helpers->recipe->zoom_webinar->get_webinars_field( null, $this->action_meta ),
				Automator()->helpers->recipe->zoom_webinar->get_webinar_questions_repeater(),
			);
		}

		return array(
			'options_group' => array(
				$this->action_meta => $option_fileds,
			),
		);

	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function zoom_webinar_register_user( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->zoom_webinar;

		try {

			$webinar_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

			if ( empty( $user_id ) ) {
				throw new \Exception( __( 'User was not found.', 'uncanny-automator' ) );
			}

			if ( empty( $webinar_key ) ) {
				throw new \Exception( __( 'Webinar was not found.', 'uncanny-automator' ) );
			}

			$webinar_key = str_replace( '-objectkey', '', $webinar_key );
			$user        = get_userdata( $user_id );

			if ( is_wp_error( $user ) ) {
				throw new \Exception( __( 'User not found.', 'uncanny-automator' ) );
			}

			$webinar_user          = array();
			$webinar_user['email'] = $user->user_email;

			$webinar_user['first_name'] = $user->first_name;
			$webinar_user['last_name']  = $user->last_name;

			$email_parts                = explode( '@', $webinar_user['email'] );
			$webinar_user['first_name'] = empty( $webinar_user['first_name'] ) ? $email_parts[0] : $webinar_user['first_name'];

			if ( ! empty( $action_data['meta']['WEBINARQUESTIONS'] ) ) {
				$webinar_user = $helpers->add_custom_questions( $webinar_user, $action_data['meta']['WEBINARQUESTIONS'], $recipe_id, $user_id, $args );
			}

			$webinar_occurrences = array();

			if ( ! empty( $action_data['meta']['OCCURRENCES'] ) ) {
				$webinar_occurrences = json_decode( $action_data['meta']['OCCURRENCES'] );
			}

			$response = $helpers->add_to_webinar( $webinar_user, $webinar_key, $webinar_occurrences, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
