<?php

namespace Uncanny_Automator;

/**
 * Class WP_VIEWCUSTOMPOST
 *
 * @package Uncanny_Automator
 */
class WP_VIEWCUSTOMPOST {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

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
		$this->trigger_code = 'VIEWCUSTOMPOST';
		$this->trigger_meta = 'WPCUSTOMPOST';
		if ( is_admin() ) {
			add_action( 'wp_loaded', array( $this, 'plugins_loaded' ), 99 );
		} else {
			$this->define_trigger();
		}
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
			'sentence'            => sprintf( esc_attr__( 'A user views {{a custom post type:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user views {{a custom post type}}', 'uncanny-automator' ),
			'action'              => 'template_redirect',
			'priority'            => 90,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'view_post' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );

		return;
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
				'options_group' => array(
					$this->trigger_meta => array(
						Automator()->helpers->recipe->wp->options->all_post_types(
							null,
							'WPPOSTTYPES',
							array(
								'token'        => false,
								'is_ajax'      => true,
								'target_field' => $this->trigger_meta,
								'endpoint'     => 'select_custom_post_by_type',
							)
						),
						/* translators: Noun */
						Automator()->helpers->recipe->field->select_field(
							$this->trigger_meta,
							esc_attr__( 'Post', 'uncanny-automator' ),
							array(),
							null,
							false,
							'',
							array(
								$this->trigger_meta => esc_attr__( 'Post title', 'uncanny-automator' ),
								$this->trigger_meta . '_ID' => esc_attr__( 'Post ID', 'uncanny-automator' ),
								$this->trigger_meta . '_URL' => esc_attr__( 'Post URL', 'uncanny-automator' ),
								$this->trigger_meta . '_EXCERPT' => __( 'Post excerpt', 'uncanny-automator' ),
								'POSTIMAGEURL'      => __( 'Post featured image URL', 'uncanny-automator' ),
								'POSTIMAGEID'       => __( 'Post featured image ID', 'uncanny-automator' ),
							)
						),
					),
				),
				'options'       => array(
					Automator()->helpers->recipe->options->number_of_times(),
				),
			)
		);
		return $options;
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		$this->define_trigger();
	}

	/**
	 *
	 */
	public function view_post() {

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( $post ) {

			$user_id = get_current_user_id();

			$args                    = array(
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => $post->ID,
				'user_id' => $user_id,
			);
			$post_type               = get_post_type_object( $post->post_type );
			$args['post_type_label'] = $post_type->labels->singular_name;

			$arr = Automator()->maybe_add_trigger_entry( $args, false );
			if ( $arr ) {
				foreach ( $arr as $result ) {
					if ( true === $result['result'] ) {
						$result['args']['post_type_label'] = $post_type->labels->singular_name;

						$trigger_meta = array(
							'user_id'        => (int) $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						);

						// Post excerpt
						$trigger_meta['meta_key']   = $this->trigger_meta . '_EXCERPT';
						$trigger_meta['meta_value'] = maybe_serialize( $post->post_excerpt );
						Automator()->insert_trigger_meta( $trigger_meta );

						// Post Featured Image URL
						$trigger_meta['meta_key']   = 'POSTIMAGEURL';
						$trigger_meta['meta_value'] = maybe_serialize( get_the_post_thumbnail_url( $post->ID, 'full' ) );
						Automator()->insert_trigger_meta( $trigger_meta );

						// Post Featured Image ID
						$trigger_meta['meta_key']   = 'POSTIMAGEID';
						$trigger_meta['meta_value'] = maybe_serialize( get_post_thumbnail_id( $post->ID ) );
						Automator()->insert_trigger_meta( $trigger_meta );

						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}
