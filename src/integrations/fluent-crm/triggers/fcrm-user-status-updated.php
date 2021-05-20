<?php

namespace Uncanny_Automator;

/**
 * Class FCRM_USER_STATUS_UPDATED
 *
 * @package Uncanny_Automator
 */
class FCRM_USER_STATUS_UPDATED {

	/**
	 * Integration code.
	 *
	 * @var string
	 */
	public static $integration = 'FCRM';

	/**
	 * The trigger code.
	 *
	 * @var string
	 */
	protected $trigger_code;

	/**
	 * The trigger meta.
	 *
	 * @var string
	 */
	protected $trigger_meta;

	protected $helper_status = '';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {


		$this->helper_status = new Fcrm_Status_Helpers();

		$this->trigger_code = 'FCRMUSERSTATUSUPDATED';
		$this->trigger_meta = 'FCRMUSERUPDATEDSTATUS';

		add_action( 'plugins_loaded', array( $this, 'define_trigger' ), 15 );
	}

	public function get_trigger_code() {
		return $this->trigger_code;
	}

	public function get_trigger_meta() {
		return $this->trigger_meta;
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function define_trigger() {


		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/fluentcrm/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Fluent Forms */
			'sentence'            => sprintf( esc_html__( 'A user is set to a {{specific status:%1$s}}', 'uncanny-automator' ), $this->trigger_code ),
			/* translators: Logged-in trigger - Fluent Forms */
			'select_option_name'  => esc_html__( 'A user is set to a {{specific status}}', 'uncanny-automator' ),
			'action'              => 'automator_fluentcrm_status_update',
			'priority'            => 200,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'user_status_updated' ),
			'options'             => array(
				array(
					'input_type'               => 'select',
					'option_code'              => $this->trigger_code,
					'options'                  => Automator()->helpers->recipe->fluent_crm->get_subscriber_statuses(),
					'required'                 => true,
					'label'                    => esc_html__( 'List of all available status values for Fluent CRM contacts.', 'uncanny-automator' ),
					'description'              => esc_html__( 'Select from dropdown list of the options above.', 'uncanny-automator' ),
					'supports_token'           => true,
					'supports_multiple_values' => false,
					'supports_custom_value'    => false,
					'relevant_tokens'          => $this->helper_status->get_tokens(),
				),
			),
		);

		Automator()->register->trigger( $trigger );

	}

	/*
	 * Callback function to define trigger.
	 */
	public function user_status_updated( $subscriber, $old_value ) {


		$user = get_user_by( 'email', $subscriber->email );

		// Bail out if user is not regular WordPress user.
		if ( false === $user ) {
			return;
		}

		$matched_recipe_ids = $this->helper_status->get_matched_recipes_ids( $uncanny_automator, $this, $subscriber );

		$this->helper_status->process_trigger( $matched_recipe_ids, $uncanny_automator, $this, $subscriber );

	}

}
