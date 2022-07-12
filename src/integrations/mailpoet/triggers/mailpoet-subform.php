<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Exception;
use MailPoet\FormEntity;

/**
 * Class MAILPOET_SUBFORM
 *
 * @package Uncanny_Automator
 */
class MAILPOET_SUBFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MAILPOET';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 *
	 * @throws Exception
	 */
	public function __construct() {
		$this->trigger_code = 'MAILPOETSUBFORM';
		$this->trigger_meta = 'MAILPOETFORMS';
		$this->define_trigger();
	}

	/**
	 * @throws Exception
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/mailpoet/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Mailpoet */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Mailpoet */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'mailpoet_subscription_before_subscribe',
			//mailpoet_form_submitted
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'mailpoet_form_submit' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->mailpoet->options->list_mailpoet_forms( null, $this->trigger_meta ),
					Automator()->helpers->recipe->options->number_of_times(),
				),
			)
		);
		return $options;
	}

	/**
	 * @param $data
	 * @param $segmentIds
	 * @param $form
	 */
	public function mailpoet_form_submit( $data, $segmentIds, $form ) {

		if ( empty( $form ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$args    = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form->getId() ),
			'user_id' => $user_id,
		);

		$args = Automator()->process->user->maybe_add_trigger_entry( $args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					// Form title
					$trigger_meta['meta_key']   = $this->trigger_meta;
					$trigger_meta['meta_value'] = esc_html( $form->getName() );
					Automator()->insert_trigger_meta( $trigger_meta );

					// Form ID
					$trigger_meta['meta_key']   = $this->trigger_meta . '_ID';
					$trigger_meta['meta_value'] = esc_html( $form->getId() );
					Automator()->insert_trigger_meta( $trigger_meta );

					// Email
					$trigger_meta['meta_key']   = $this->trigger_meta . '_EMAIL';
					$trigger_meta['meta_value'] = ( isset( $data['email'] ) ) ? $data['email'] : '';
					Automator()->insert_trigger_meta( $trigger_meta );

					// First name
					$trigger_meta['meta_key']   = $this->trigger_meta . '_FIRSTNAME';
					$trigger_meta['meta_value'] = ( isset( $data['first_name'] ) ) ? $data['first_name'] : '';
					Automator()->insert_trigger_meta( $trigger_meta );

					// Last name
					$trigger_meta['meta_key']   = $this->trigger_meta . '_LASTNAME';
					$trigger_meta['meta_value'] = ( isset( $data['last_name'] ) ) ? $data['last_name'] : '';
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->process->user->maybe_trigger_complete( $result['args'] );
				}
			}
		}

	}

}
