<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class MAILCHIMP_CONTACT_ADDED
 *
 * @package Uncanny_Automator
 */
class ANON_MAILCHIMP_CONTACT_ADDED {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'ANON_MAILCHIMP_CONTACT_ADDED';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'ANON_MAILCHIMP_CONTACT_ADDED_META';

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
		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( 'A contact is added to {{an audience:%1$s}}', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'A contact is added to {{an audience}}', 'uncanny-automator' ) ); // Non-active state sentence to show

		// Which do_action() fires this trigger.
		$this->add_action( 'automator_mailchimp_webhook_received_subscribe' );

		// Set the options field group.
		$this->set_options_callback( array( $this, 'get_trigger_option_fields' ) );

		// Only register the trigger if mailchimp webhook is enabled inside the settings.
		if ( get_option( 'uap_mailchimp_enable_webhook', false ) ) {

			$this->register_trigger();

		}

	}

	/**
	 * The set_options_callback method callback function.
	 *
	 * @return array the list of option group.
	 */
	public function get_trigger_option_fields() {

		return array(

			'options_group' => array(

				$this->get_trigger_meta() => array(

					Automator()->helpers->recipe->mailchimp->options->get_all_lists(
						esc_html__( 'Audience', 'uncanny-automator' ),
						$this->get_trigger_meta(),
						array(
							'has_any' => true,
						)
					),

				),

			),

		);

	}

	public function validate_trigger( ...$args ) {
		return Automator()->helpers->recipe->mailchimp->options->validate_trigger();
	}

	/**
	 * Trigger conditions.
	 *
	 * Only run the trigger if audience is set to 'Any' or if audience id is equals to the one set in the recipe.
	 *
	 * @return void.
	 */
	protected function trigger_conditions( $args ) {

		// If args is empty, bail.
		if ( ! is_array( $args ) ) {
			return;
		}

		// First element of args is the MailChimp event data.
		$event = $args[0];

		// Match 'Any audience' condition.
		$this->do_find_any( true );

		// Match specific condition.
		$this->do_find_this( $this->get_trigger_meta() );

		// Find in list id.
		$this->do_find_in( array( $event['data']['list_id'] ) );

		Automator()->helpers->recipe->mailchimp->options->log( '[3/3. Trigger conditions]. Setting trigger conditions. Will run if audience is set to `Any` or if audience matches the selected audience value. Check recipe log.' );

	}

	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

}
