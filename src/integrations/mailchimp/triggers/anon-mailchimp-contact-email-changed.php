<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED
 *
 * @package Uncanny_Automator
 */
class ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED_META';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->set_integration( 'MAILCHIMP' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_trigger_type( 'anonymous' );

		$this->set_is_login_required( false );

		$this->set_is_pro( false );

		/* Translators: Trigger sentence */
		$this->set_sentence( esc_html__( 'A contact email is changed', 'uncanny-automator' ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'A contact email is changed', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->add_action( 'automator_mailchimp_webhook_received_upemail' ); // which do_action() fires this trigger

		if ( get_option( 'uap_mailchimp_enable_webhook', false ) ) {

			$this->register_trigger(); // Registering this trigger

		}

	}

	public function validate_trigger( ...$args ) {
		return Automator()->helpers->recipe->mailchimp->options->validate_trigger();
	}

	public function prepare_to_run( $data ) {

		// Do nothing for now.
		Automator()->helpers->recipe->mailchimp->options->log( '[3/3. Trigger conditions]. No trigger conditions set. Check recipe log.' );

	}

	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

}
