<?php
namespace Uncanny_Automator;

/**
 * Class GTT_REGISTERUSER
 *
 * @package Uncanny_Automator
 */
class GTT_REGISTERUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GTT';

	private $action_code;

	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->action_code = 'GTTREGISTERUSER';

		$this->action_meta = 'GTTTRAINING';

		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/gototraining/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf(
				/* translators: Action sentence */
				__( 'Add the user to {{a training session:%1$s}}', 'uncanny-automator' ),
				$this->action_meta
			),
			'select_option_name' => __( 'Add the user to {{a training session}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'gtt_register_user' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );

	}

	/**
	 * Callback method to `options_callback` arg.
	 */
	public function load_options() {

		return array(
			'options_group' => array(
				$this->action_meta => array(
					array(
						'option_code'     => 'GTTTRAINING',
						'input_type'      => 'select',
						'label'           => __( 'Training', 'uncanny-automator' ),
						'description'     => '',
						'required'        => true,
						'supports_tokens' => true,
						'options'         => Automator()->helpers->recipe->gototraining->get_trainings(),
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
	public function gtt_register_user( $user_id, $action_data, $recipe_id, $args ) {

		try {

			$training_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

			if ( empty( $training_key ) ) {
				throw new \Exception( __( 'Training not found.', 'uncanny-automator' ) );
			}

			$training_key = str_replace( '-objectkey', '', $training_key );
	
			$result = Automator()->helpers->recipe->gototraining->gtt_register_user( $user_id, $training_key, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			
			$action_data['do-nothing'] = true;
	
			$action_data['complete_with_errors'] = true;
	
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
