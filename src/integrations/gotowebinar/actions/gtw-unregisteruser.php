<?php
namespace Uncanny_Automator;

/**
 * Class GTW_UNREGISTERUSER
 *
 * @package Uncanny_Automator
 */
class GTW_UNREGISTERUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GTW';

	private $action_code;

	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'GTWUNREGISTERUSER';
		$this->action_meta = 'GTWWEBINAR';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/gotowebinar/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Remove the user from {{a webinar:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Remove the user from {{a webinar}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'gtw_unregister_user' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Callback function to our action that loads the option values.
	 */
	public function load_options() {

		return array(
			'options_group' => array(
				$this->action_meta => array(
					array(
						'option_code'     => 'GTWWEBINAR',
						'label'           => esc_html__( 'Webinar', 'uncanny-automator' ),
						'input_type'      => 'select',
						'required'        => true,
						'supports_tokens' => true,
						'options'         => Automator()->helpers->recipe->gotowebinar->get_webinars(),
					),
				),
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
	public function gtw_unregister_user( $user_id, $action_data, $recipe_id, $args ) {

		try {

			if ( empty( $user_id ) ) {
				throw new \Exception( __( 'User not found.', 'uncanny-automator' ) );
			}

			$webinar_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );
			
			if ( empty( $webinar_key ) ) {
				throw new \Exception( __( 'Webinar not found.', 'uncanny-automator' ) );
			}

			$webinar_key = str_replace( '-objectkey', '', $webinar_key );

			$user_registrant_key = get_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey', true );

			if ( empty( $user_registrant_key ) ) {
				throw new \Exception( __( 'User was not registered for webinar.', 'uncanny-automator' ) );
			}

			$result = Automator()->helpers->recipe->gotowebinar->gtw_unregister_user( $user_id, $webinar_key, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} catch ( \Exception $e ) {
			$action_data['do-nothing'] = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}

	}

}
