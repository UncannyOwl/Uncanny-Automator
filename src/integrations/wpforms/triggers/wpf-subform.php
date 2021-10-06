<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Exception;

/**
 * Class WPF_SUBFORM
 * @package Uncanny_Automator
 */
class WPF_SUBFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPF';

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
	 * @throws Exception
	 */
	public function __construct() {
		$this->trigger_code = 'WPFSUBFORM';
		$this->trigger_meta = 'WPFFORMS';
		$this->define_trigger();
	}

	/**
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
			'options'             => array(
				Automator()->helpers->recipe->wpforms->options->list_wp_forms(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
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
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					Automator()->process->user->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
