<?php

namespace Uncanny_Automator;

/**
 * Class WP_VIEWPOST
 *
 * @package Uncanny_Automator
 */
class WP_VIEWPOST {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'VIEWPOST';
		$this->trigger_meta = 'WPPOST';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( 'A user views {{a post:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user views {{a post}}', 'uncanny-automator' ),
			'action'              => 'template_redirect',
			'priority'            => 90,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'view_post' ),
			'options_callback'    => array( $this, 'load_options' ),
			// very last call in WP, we need to make sure they viewed the post and didn't skip before is was fully viewable
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		
		Automator()->helpers->recipe->wp->options->load_options = true;
		
		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->wp->options->all_posts(),
					Automator()->helpers->recipe->options->number_of_times(),
				),
			)
		);
		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 */
	public function view_post() {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! is_singular( $post->post_type ) && ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		$user_id   = get_current_user_id();
		$pass_args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post->ID,
			'user_id' => $user_id,
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					$trigger_meta = array(
						'user_id'        => (int) $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					$trigger_meta['meta_key']   = 'WPPOST';
					$trigger_meta['meta_value'] = maybe_serialize( $post->post_title );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'WPPOST_ID';
					$trigger_meta['meta_value'] = maybe_serialize( $post->ID );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'WPPOST_URL';
					$trigger_meta['meta_value'] = maybe_serialize( get_permalink( $post->ID ) );
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
