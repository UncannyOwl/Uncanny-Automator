<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Exception;

/**
 * Class WPF_SUBFORM
 *
 * @package Uncanny_Automator
 */
class WPF_SUBFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPF';

	/**
	 * The trigger code.
	 *
	 * @var string
	 */

	private $trigger_code;

	/**
	 * The trigger meta.
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 *
	 * @throws Exception
	 */
	public function __construct() {
		$this->trigger_code = 'WPFSUBFORM';
		$this->trigger_meta = 'WPFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define the trigger.
	 *
	 * @throws Exception
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-forms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP Forms */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WP Forms */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'wpforms_process_complete',
			'priority'            => 20,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'wpform_submit' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array
	 */
	public function load_options() {

		$options = array(
			'options' => array(
				Automator()->helpers->recipe->wpforms->options->list_wp_forms(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;

	}

	/**
	 * Validation callback method.
	 *
	 * @param $fields
	 * @param $entry
	 * @param $form_data
	 * @param $entry_id
	 */
	public function wpform_submit( $fields, $entry, $form_data, $entry_id ) {
		if ( empty( $form_data ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$args    = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form_data['id'] ),
			'user_id' => $user_id,
		);

		$args = Automator()->process->user->maybe_add_trigger_entry( $args, false );

		//Adding an action to save form submission in trigger meta
		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		do_action( 'automator_save_wp_form', $fields, $form_data, $recipes, $args );

		if ( $args ) {
			foreach ( $args as $r ) {
				if ( true === $r['result'] ) {
					if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
						//Saving form values in trigger log meta for token parsing!
						$wpf_args   = array(
							'trigger_id'     => (int) $r['args']['trigger_id'],
							'user_id'        => $user_id,
							'trigger_log_id' => $r['args']['get_trigger_id'],
							'run_number'     => $r['args']['run_number'],
						);
						$user_ip    = Automator()->helpers->recipe->wpforms->options->get_entry_user_ip_address( $entry_id );
						$entry_date = Automator()->helpers->recipe->wpforms->options->get_entry_entry_date( $entry_id );
						$entry_id   = Automator()->helpers->recipe->wpforms->options->get_entry_entry_id( $entry_id );

						$wpf_args['meta_key']   = 'WPFENTRYID';
						$wpf_args['meta_value'] = $entry_id;
						Automator()->insert_trigger_meta( $wpf_args );

						$wpf_args['meta_key']   = 'WPFENTRYIP';
						$wpf_args['meta_value'] = $user_ip;
						Automator()->insert_trigger_meta( $wpf_args );

						$wpf_args['meta_key']   = 'WPFENTRYDATE';
						$wpf_args['meta_value'] = maybe_serialize( Automator()->helpers->recipe->wpforms->options->get_entry_date( $entry_date ) );
						Automator()->insert_trigger_meta( $wpf_args );
					}
					Automator()->process->user->maybe_trigger_complete( $r['args'] );
				}
			}
		}
	}
}
