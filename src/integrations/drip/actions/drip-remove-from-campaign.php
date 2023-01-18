<?php

namespace Uncanny_Automator;

/**
 * Class DRIP_REMOVE_FROM_CAMPAIGN
 *
 * @package Uncanny_Automator
 */
class DRIP_REMOVE_FROM_CAMPAIGN {

	use Recipe\Actions;

	/**
	 * @var Drip_Functions
	 */
	private $functions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->functions = new Drip_Functions();

		$this->setup_action();
	}


	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->set_integration( 'DRIP' );
		$this->set_action_code( 'REMOVE_FROM_CAMPAIGN' );
		$this->set_action_meta( 'EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/drip/' ) );
		$this->set_requires_user( false );
		/* translators: 1. tag, 2. email address */
		$this->set_sentence( sprintf( esc_attr__( 'Remove {{a subscriber:%1$s}} from {{a campaign:%2$s}}', 'uncanny-automator' ), $this->get_action_meta(), 'CAMPAIGN:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Remove {{a subscriber}} from {{a campaign}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$email_field = array(
			'option_code' => 'EMAIL',
			'label'       => __( 'Email', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		$campaign_options   = $this->functions->get_campaigns_options();
		$campaign_options[] = array(
			'text'  => __( 'Unsubscribe from all campaigns', 'uncanny-automator' ),
			'value' => 'unsubscribe_from_all',
		);

		$campaign_field = array(
			'option_code'           => 'CAMPAIGN',
			'label'                 => __( 'Campaign', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => $campaign_options,
			'required'              => true,
			'supports_custom_value' => false,
			'supports_tokens'       => false,
			'options_show_id'       => false,
		);

		return array(
			'options_group' => array(
				$this->action_meta => array(
					$email_field,
					$campaign_field,
				),
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = Automator()->parse->text( $action_data['meta']['EMAIL'], $recipe_id, $user_id, $args );

		$campaign_id = $action_data['meta']['CAMPAIGN'];

		$error_msg = '';

		try {

			$response = $this->functions->remove_from_campaign( $email, $campaign_id );

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['complete_with_errors'] = true;
		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_msg );
	}
}
