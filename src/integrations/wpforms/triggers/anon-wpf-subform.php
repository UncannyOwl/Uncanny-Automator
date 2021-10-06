<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class ANON_WPF_SUBFORM
 * @package Uncanny_Automator
 */
class ANON_WPF_SUBFORM {

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
	 */
	public function __construct() {
		$this->trigger_code = 'ANONWPFSUBFORM';
		$this->trigger_meta = 'ANONWPFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-forms/' ),
			'type'                => 'anonymous',
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Anonymous trigger - WP Forms */
			'sentence'            => sprintf( __( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - WP Forms */
			'select_option_name'  => __( '{{A form}} is submitted', 'uncanny-automator' ),
			'action'              => 'wpforms_process_complete',
			'priority'            => 20,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'wpform_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->wpforms->options->list_wp_forms( null, $this->trigger_meta ),
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
		do_action( 'automator_save_anon_wp_form', $fields, $form_data, $recipes, $args );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					Automator()->process->user->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
