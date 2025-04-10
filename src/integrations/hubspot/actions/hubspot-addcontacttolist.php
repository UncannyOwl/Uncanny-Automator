<?php

namespace Uncanny_Automator;

/**
 * Class HUBSPOT_ADDCONTACTTOLIST
 *
 * @package Uncanny_Automator
 */
class HUBSPOT_ADDCONTACTTOLIST {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'HUBSPOT';

	/**
	 *
	 * @var string
	 */
	private $action_code;

	/**
	 *
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'HUBSPOTADDCONTACTTOLIST';
		$this->action_meta = 'HUBSPOTLIST';
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'integration/hubspot/' ),
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			// translators: the selected HubSpot static list name
			'sentence'              => sprintf( esc_html__( 'Add a HubSpot contact to {{a static list:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => esc_html__( 'Add a HubSpot contact to {{a static list}}', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'requires_user'         => false,
			'execution_function'    => array( $this, 'add_contact_to_list' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'HUBSPOTEMAIL',
							'label'       => esc_attr__( 'Email address', 'uncanny-automator' ),
							'input_type'  => 'text',
							'default'     => '',
							'required'    => true,
						)
					),
					Automator()->helpers->recipe->field->select(
						array(
							'option_code'           => $this->action_meta,
							'label'                 => esc_attr__( 'HubSpot List', 'uncanny-automator' ),
							'required'              => true,
							'supports_tokens'       => false,
							'supports_custom_value' => false,
							'options'               => Automator()->helpers->recipe->hubspot->get_lists(),
						)
					),
				),
			),

		);
	}

	/**
	 * Action validation function.
	 *
	 * @return mixed
	 */
	public function add_contact_to_list( $user_id, $action_data, $recipe_id, $args ) {

		$email = trim( Automator()->parse->text( $action_data['meta']['HUBSPOTEMAIL'], $recipe_id, $user_id, $args ) );
		$list  = trim( Automator()->parse->text( $action_data['meta']['HUBSPOTLIST'], $recipe_id, $user_id, $args ) );

		$helpers = Automator()->helpers->recipe->hubspot->options;

		try {

			$response = $helpers->add_contact_to_list( $list, $email, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$helpers->log_action_error( $e->getMessage(), $user_id, $action_data, $recipe_id );
		}
	}
}
