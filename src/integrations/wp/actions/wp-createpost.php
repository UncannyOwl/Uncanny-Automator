<?php

namespace Uncanny_Automator;

/**
 * Class WP_CREATEPOST
 * @package Uncanny_Automator
 */
class WP_CREATEPOST {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'CREATEPOST';
		$this->action_meta = 'WPPOSTFIELDS';
		if ( is_admin() ) {
			add_action( 'wp_loaded', [ $this, 'plugins_loaded' ], 99 );
		} else {
			$this->define_action();
		}
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		global $uncanny_automator;
		$custom_post_types = $uncanny_automator->helpers->recipe->wp->options->all_post_types( esc_attr__( 'Type', 'uncanny-automator' ), $this->action_code, [
			'token'   => false,
			'is_ajax' => false,
		] );
		// now get regular post types.
		$args = [
			'public'   => true,
			'_builtin' => true,
		];

		$output     = 'object';
		$operator   = 'and';
		$options    = [];
		$post_types = get_post_types( $args, $output, $operator );
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
			}
		}
		$options                      = array_merge( $options, $custom_post_types['options'] );
		$custom_post_types['options'] = $options;

		// get all the post stati objects
		$post_statuses = get_post_stati( [], 'objects' );

		// initialise options
		$status_options = [];

		// make sure there are post stati
		if ( ! empty( $post_statuses ) ) {

			// loop through each post status object
			foreach ( $post_statuses as $key => $post_status ) {

				// initialise label indicating the source of the post status
				$source_label = '';

				// guess the source using the text-domain. See: https://developer.wordpress.org/reference/functions/register_post_status/
				if ( is_array( $post_status->label_count ) ) {
					// Also see: https://developer.wordpress.org/reference/functions/_nx_noop/
					$source_label = isset( $post_status->label_count['domain'] ) ? "({$post_status->label_count['domain']})" : '';
				}

				// set the post status and its source as the option's label
				$status_options[ $key ] = $post_status->label . $source_label;
			}
		}

		// Remove status "Scheduled"
		if ( isset( $status_options['future'] ) ) {
			unset( $status_options['future'] );
		}

		// Remove status "auto-draft"
		if ( isset( $status_options['auto-draft'] ) ) {
			unset( $status_options['auto-draft'] );
		}

		// Remove status "inherit"
		if ( isset( $status_options['inherit'] ) ) {
			unset( $status_options['inherit'] );
		}

		$post_status_field = $uncanny_automator->helpers->recipe->field->select_field( 'WPCPOSTSTATUS', esc_attr__( 'Status', 'uncanny-automator' ), $status_options );

		$action = [
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - WordPress Core */
			'sentence'           => sprintf( esc_attr__( 'Create {{a post:%1$s}}', 'uncanny-automator' ), $this->action_code ),
			/* translators: Action - WordPress Core */
			'select_option_name' => esc_attr__( 'Create {{a post}}', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => [ $this, 'create_post' ],
			'options_group'      => [
				$this->action_code => [
					$custom_post_types,
					$post_status_field,
					$uncanny_automator->helpers->recipe->field->text_field( 'WPCPOSTAUTHOR', esc_attr__( 'Author', 'uncanny-automator' ), true, 'text', '{{admin_email}}', true, esc_attr__( 'Accepts user ID, email or username', 'uncanny-automator' ) ),
					$uncanny_automator->helpers->recipe->field->text_field( 'WPCPOSTTITLE', esc_attr__( 'Title', 'uncanny-automator' ), true, 'text', '', true ),
					$uncanny_automator->helpers->recipe->field->text_field( 'WPCPOSTSLUG', esc_attr__( 'Slug', 'uncanny-automator' ), true, 'text', '', false ),
					$uncanny_automator->helpers->recipe->field->text_field( 'WPCPOSTCONTENT', esc_attr__( 'Content', 'uncanny-automator' ), true, 'textarea', '', false ),
					[
						'input_type'        => 'repeater',
						'option_code'       => 'CPMETA_PAIRS',
						'label'             => esc_attr__( 'Meta', 'uncanny-automator' ),
						'required'          => false,
						'fields'            => [
							[
								'input_type'      => 'text',
								'option_code'     => 'KEY',
								'label'           => esc_attr__( 'Key', 'uncanny-automator' ),
								'supports_tokens' => true,
								'required'        => true,
							],
							[
								'input_type'      => 'text',
								'option_code'     => 'VALUE',
								'label'           => esc_attr__( 'Value', 'uncanny-automator' ),
								'supports_tokens' => true,
								'required'        => true,
							],
						],
						'add_row_button'    => esc_attr__( 'Add pair', 'uncanny-automator' ),
						'remove_row_button' => esc_attr__( 'Remove pair', 'uncanny-automator' ),
					],
				],
			],
		];

		$uncanny_automator->register->action( $action );
	}

	public function plugins_loaded() {
		$this->define_action();
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function create_post( $user_id, $action_data, $recipe_id, $args ) {

		global $uncanny_automator;

		$post_title   = $uncanny_automator->parse->text( $action_data['meta']['WPCPOSTTITLE'], $recipe_id, $user_id, $args );
		$post_slug    = $uncanny_automator->parse->text( $action_data['meta']['WPCPOSTSLUG'], $recipe_id, $user_id, $args );
		$post_content = $uncanny_automator->parse->text( $action_data['meta']['WPCPOSTCONTENT'], $recipe_id, $user_id, $args );
		$post_author  = $uncanny_automator->parse->text( $action_data['meta']['WPCPOSTAUTHOR'], $recipe_id, $user_id, $args );
		$post_status  = $uncanny_automator->parse->text( $action_data['meta']['WPCPOSTSTATUS'], $recipe_id, $user_id, $args );
		$post_type    = $action_data['meta'][ $this->action_code ];

		$post_args                 = [];
		$post_args['post_title']   = sanitize_text_field( $post_title );
		$post_args['post_name']    = sanitize_title( $post_slug );
		$post_args['post_content'] = $post_content;
		$post_args['post_type']    = $post_type;
		$post_args['post_status']  = $post_status;
		$post_args['post_author']  = 0;
		if ( is_numeric( $post_author ) ) {
			$post_args['post_author'] = absint( $post_author );
		} else {
			// get author by username or email
			$user = get_user_by( 'login', $post_author );
			if ( ! $user ) {
				$user = get_user_by( 'email', $post_author );
			}
			if ( ! $user ) {
				$user = get_user_by( 'slug', $post_author );
			}
			if ( ! empty( $user ) ) {
				$post_args['post_author'] = absint( $user->ID );
			}
		}

		$post_id = wp_insert_post( $post_args );

		if ( $post_id ) {
			$meta_pairs = json_decode( $action_data['meta']['CPMETA_PAIRS'], true );
			if ( ! empty( $meta_pairs ) ) {
				foreach ( $meta_pairs as $pair ) {
					$meta_key   = sanitize_title( $pair['KEY'] );
					$meta_value = sanitize_text_field( $uncanny_automator->parse->text( $pair['VALUE'], $recipe_id, $user_id, $args ) );
					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}
		}

		$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
	}

}