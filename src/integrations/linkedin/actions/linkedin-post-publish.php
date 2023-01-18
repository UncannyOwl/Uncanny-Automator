<?php
namespace Uncanny_Automator;

/**
 * Class LINKEDIN_POST_PUBLISH
 *
 * @package Uncanny_Automator
 */
class LINKEDIN_POST_PUBLISH {

	use \Uncanny_Automator\Recipe\Actions;

	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'LINKEDIN' );

		$this->set_action_code( 'LINKEDIN_POST_PUBLISH' );

		$this->set_action_meta( 'LINKEDIN_POST_PUBLISH_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/linkedin/' ) );

		$this->set_requires_user( false );

		/* translators: tag name */
		$this->set_sentence( sprintf( esc_attr__( 'Publish a post to {{a LinkedIn page:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_attr__( 'Publish a post to {{a LinkedIn page}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_wpautop( false );

		$this->register_action();

	}

	/**
	 * Method load_options
	 *
	 * @return void
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'           => $this->get_action_meta(),
							'label'                 => esc_attr__( 'LinkedIn Page', 'uncanny-automator' ),
							'input_type'            => 'select',
							'is_ajax'               => true,
							'endpoint'              => 'automator_linkedin_get_pages',
							'supports_custom_value' => false,
							'required'              => true,
						),
						array(
							'option_code'     => 'BODY',
							'label'           => esc_attr__( 'Message', 'uncanny-automator' ),
							'input_type'      => 'textarea',
							'supports_tokens' => true,
							'required'        => true,
						),
					),
				),
			)
		);

	}

	/**
	 * Method process_action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helper = new Linkedin_Helpers( false );

		$message = isset( $parsed['BODY'] ) ? $this->format( $parsed['BODY'] ) : '';

		$urn = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		try {

			$body = array(
				'access_token' => $helper->get_client()['access_token'],
				'message'      => $message,
				'urn'          => $urn,
				'action'       => 'post_publish',
			);

			$helper->api_call( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	public function format( $string = '' ) {

		return sanitize_textarea_field( str_replace( array( '<br />', '<br/>', '<br>' ), PHP_EOL, $string ) );

	}


}
