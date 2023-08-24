<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class WA_MESSAGE_STATUS_UPDATED
 *
 * @package Uncanny_Automator
 */
class WA_MESSAGE_STATUS_UPDATED {

	use Recipe\Triggers;

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'WA_MESSAGE_STATUS_UPDATED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'WA_MESSAGE_STATUS_UPDATED_META';

	/**
	 * The WhatsApp tokens.
	 *
	 * @var Wa_Message_Status_Tokens $whatsapp_tokens
	 */
	public $whatsapp_tokens;

	public function __construct() {

		$this->whatsapp_tokens = new Wa_Message_Status_Tokens();

		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void.
	 */
	public function setup_trigger() {

		$this->set_integration( 'WHATSAPP' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		$this->add_action( 'automator_whatsapp_message_status' );

		$this->set_uses_api( true );

		$this->set_action_args_count( 2 );

		$this->set_sentence( sprintf( 'A message to a recipient is set to {{a specific:%1$s}} status', $this->get_trigger_meta() ) );

		$this->set_readable_sentence( 'A message to a recipient is set to {{a specific}} status' );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_tokens( $this->whatsapp_tokens->status_updated_tokens() );

		// Register the trigger.
		$this->register_trigger();

	}

	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					array(
						'option_code'     => $this->get_trigger_meta(),
						'label'           => __( 'Status', 'uncanny-automator' ),
						'input_type'      => 'select',
						'required'        => true,
						'options'         => array(
							'sent'      => esc_html__( 'Sent', 'uncanny-automator' ),
							'delivered' => esc_html__( 'Delivered', 'uncanny-automator' ),
							'read'      => esc_html__( 'Read', 'uncanny-automator' ),
						),
						'relevant_tokens' => array(),
					),
				),
			)
		);

	}

	/**
	 * Validate the trigger.
	 *
	 * @return boolean True.
	 */
	public function validate_trigger( ...$args ) {

		$response = $args[0][0];

		$name = 'automator_whatsapp_' . $response['wamid'] . '_' . $response['status'];

		if ( false !== get_transient( $name ) ) {
			return false;
		}

		if ( empty( $args[0][1] ) ) {
			return false;
		}

		set_transient( $name, 'yes', 60 ); // Expire in 1 minute.

		// Flush the transient after 60s.
		wp_schedule_single_event( time() + 60, 'automator_whatsapp_flush_transient', array( $name ) );

		return true;

	}

	/**
	 * Prepare to run.
	 *
	 * Sets the conditional trigger to true.
	 *
	 * @return void.
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	/**
	 * Trigger conditions.
	 *
	 * Only run the trigger if status is set to 'read'. Do $this->do_find_any( true ); to allow 'Any'.
	 *
	 * @return void.
	 */
	protected function trigger_conditions( $args ) {

		$status = isset( $args[1] ) ? $args[1] : '';

		// Match specific condition.
		$this->do_find_this( $this->get_trigger_meta() );

		$this->do_find_in( $status );

	}

	/**
	 * Continue trigger process even for logged-in user.
	 *
	 * @return boolean True.
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

}
