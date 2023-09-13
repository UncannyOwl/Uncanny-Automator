<?php
namespace Uncanny_Automator;

/**
 * Class TELEGRAM_SEND_MESSAGE
 *
 * @package Uncanny_Automator
 */
class TELEGRAM_SEND_MESSAGE {

	use \Uncanny_Automator\Recipe\Actions;

	protected $functions;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup_action();

		$this->functions = new Telegram_Functions();
	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'TELEGRAM' );

		$this->set_action_code( 'SEND_MESSAGE' );

		$this->set_action_meta( 'CHAT_ID' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/telegram/' ) );

		$this->set_requires_user( false );

		/* translators: tag name */
		$this->set_sentence( sprintf( esc_attr__( 'Send {{a text message:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_attr__( 'Send {{a text message}}', 'uncanny-automator' ) );

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
							'option_code'     => $this->get_action_meta(),
							'label'           => esc_attr__( 'Chat/Channel', 'uncanny-automator' ),
							'input_type'      => 'text',
							'supports_tokens' => true,
							'required'        => true,
						),
						array(
							'option_code'     => 'TEXT',
							'label'           => esc_attr__( 'Text', 'uncanny-automator' ),
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

		$chat_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( trim( $parsed[ $this->get_action_meta() ] ) ) : '';
		$text    = isset( $parsed['TEXT'] ) ? sanitize_textarea_field( $parsed['TEXT'] ) : '';

		try {

			$response = $this->functions->api->send_message( $chat_id, $text );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}
	}
}
