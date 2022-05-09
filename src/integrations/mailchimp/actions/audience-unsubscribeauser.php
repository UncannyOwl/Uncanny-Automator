<?php

namespace Uncanny_Automator;

/**
 * Class AUDIENCE_UNSUBSCRIBEAUSER
 *
 * @package Uncanny_Automator
 */
class AUDIENCE_UNSUBSCRIBEAUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MAILCHIMP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MCHIMPAUDIENCEUNSUBSCRIBEAUSER';
		$this->action_meta = 'AUDIENCEUNSUBSCRIBEAUSER';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/mailchimp/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			// translators: Mailchimp audience
			'sentence'           => sprintf( __( 'Unsubscribe the user from {{an audience:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Unsubscribe the user from {{an audience}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'options_callback'   => array( $this, 'load_options' ),
			'execution_function' => array( $this, 'unsubscribe_audience_member' ),
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
					Automator()->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST'
					),
					Automator()->helpers->recipe->mailchimp->options->get_double_opt_in(
						__( 'Delete subscriber from Mailchimp?', 'uncanny-automator' ),
						'MCDELETEMEMBER',
						array(
							'description' => __( 'Yes, delete from Mailchimp, No, only unsubscribe from audience', 'uncanny-automator' ),
						)
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
	public function unsubscribe_audience_member( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->mailchimp->options;
		
		try {
			// Here unsubscribe
			$list_id       = $action_data['meta']['MCLIST'];
			$delete_member = $action_data['meta']['MCDELETEMEMBER'];

			// get current user email
			$user      = get_userdata( $user_id );
			$user_hash = md5( strtolower( trim( $user->user_email ) ) );

			if ( 'no' === $delete_member ) {
				$user_data = array(
					'status' => 'unsubscribed',
				);

				$request_params = array(
					'action'    => 'update_subscriber',
					'list_id'   => $list_id,
					'user_hash' => $user_hash,
					'user_data' => wp_json_encode( $user_data ),
				);

			} else {
				$request_params = array(
					'action'    => 'delete_subscriber',
					'list_id'   => $list_id,
					'user_hash' => $user_hash,
				);

			}

			$response = $helpers->api_request( $request_params, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		
		} catch ( \Exception $e ) {
			$helpers->complete_with_error( $e->getMessage(), $user_id, $action_data, $recipe_id );
		}
	}

}
