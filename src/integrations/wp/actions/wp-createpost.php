<?php

namespace Uncanny_Automator;

/**
 * Class WP_CREATEPOST
 *
 * @package Uncanny_Automator
 */
class WP_CREATEPOST {

	use Recipe\Action_Tokens;

	public static $integration = 'WP';

	private $action_code = 'CREATEPOST';

	private $action_meta = 'WPPOSTFIELDS';

	public function __construct() {

		if ( is_admin() ) {
			add_action( 'wp_loaded', array( $this, 'define_action' ), 99 );
		} else {
			$this->define_action();
		}

	}

	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wordpress-core/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			/* translators: Action - WordPress Core */
			'sentence'           => sprintf( esc_attr__( 'Create {{a post:%1$s}}', 'uncanny-automator' ), $this->action_code ),
			/* translators: Action - WordPress Core */
			'select_option_name' => esc_attr__( 'Create {{a post}}', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'create_post' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		$this->set_action_tokens(
			array(
				'POST_ID'       => array(
					'name' => __( 'Post ID', 'uncanny-automator' ),
					'type' => 'int',
				),
				'POST_URL'      => array(
					'name' => __( 'Post URL', 'uncanny-automator' ),
					'type' => 'url',
				),
				'POST_URL_EDIT' => array(
					'name' => __( 'Post edit URL', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->action_code
		);

		Automator()->register->action( $action );

	}

	public function load_options() {

		Automator()->helpers->recipe->wp->options->load_options = true;

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->action_code => array(

						$this->get_all_post_types(),

						array(
							'input_type'               => 'select',
							'option_code'              => 'TAXONOMY',
							'label'                    => esc_attr__( 'Taxonomies', 'uncanny-automator' ),
							'custom_value_description' => esc_attr__( 'Taxonomy ID. Separated by comma.', 'uncanny-automator' ),
							'supports_multiple_values' => true,
							'supports_custom_value'    => false,
							'supports_tokens'          => false,
							'required'                 => false,
							'options'                  => array(),
							'is_ajax'                  => true,
							'endpoint'                 => 'select_specific_taxonomy_terms',
							'fill_values_in'           => 'TERM',
							'relevant_tokens'          => array(),

						),

						array(
							'input_type'               => 'select',
							'option_code'              => 'TERM',
							'label'                    => esc_attr__( 'Terms', 'uncanny-automator' ),
							'custom_value_description' => esc_attr__( 'Term ID. Separated by comma.', 'uncanny-automator' ),
							'supports_multiple_values' => true,
							'supports_custom_value'    => false,
							'supports_tokens'          => false,
							'required'                 => false,
							'options'                  => array(),
							'relevant_tokens'          => array(),
						),

						Automator()->helpers->recipe->field->select_field(
							'WPCPOSTSTATUS',
							esc_attr__( 'Status', 'uncanny-automator' ),
							$this->get_post_statuses()
						),

						Automator()->helpers->recipe->field->text_field(
							'WPCPOSTAUTHOR',
							esc_attr__( 'Author', 'uncanny-automator' ),
							true,
							'text',
							'{{admin_email}}',
							true,
							esc_attr__( 'Accepts user ID, email or username', 'uncanny-automator' )
						),

						Automator()->helpers->recipe->field->text_field(
							'WPCPOSTTITLE',
							esc_attr__( 'Title', 'uncanny-automator' ),
							true,
							'text',
							'',
							true
						),

						Automator()->helpers->recipe->field->text_field(
							'WPCPOSTSLUG',
							esc_attr__( 'Slug', 'uncanny-automator' ),
							true,
							'text',
							'',
							false
						),

						Automator()->helpers->recipe->field->text_field(
							'WPCPOSTCONTENT',
							esc_attr__( 'Content', 'uncanny-automator' ),
							true,
							'textarea',
							'',
							false
						),

						array(
							'option_code' => 'WPCPOSTEXCERPT',
							/* translators: Post Excerpt field */
							'label'       => esc_attr__( 'Excerpt', 'uncanny-automator' ),
							'placeholder' => '',
							'input_type'  => 'textarea',
							'required'    => false,
						),

						// The photo url field.
						array(
							'option_code' => 'FEATURED_IMAGE_URL',
							/* translators: Email field */
							'label'       => esc_attr__( 'Featured image URL', 'uncanny-automator' ),
							'placeholder' => esc_attr__( 'https://examplewebsite.com/path/to/image.jpg', 'uncanny-automator' ),
							'input_type'  => 'url',
							'required'    => false,
							'description' => esc_attr__( 'The URL must include a supported image file extension (e.g. .jpg, .png, .svg, etc.). Some sites may block remote image download.', 'uncanny-automator' ),
						),

						array(
							'input_type'        => 'repeater',
							'option_code'       => 'CPMETA_PAIRS',
							'label'             => esc_attr__( 'Meta', 'uncanny-automator' ),
							'required'          => false,
							'fields'            => array(
								array(
									'input_type'      => 'text',
									'option_code'     => 'KEY',
									'label'           => esc_attr__( 'Key', 'uncanny-automator' ),
									'supports_tokens' => true,
									'required'        => true,
								),
								array(
									'input_type'      => 'text',
									'option_code'     => 'VALUE',
									'label'           => esc_attr__( 'Value', 'uncanny-automator' ),
									'supports_tokens' => true,
									'required'        => true,
								),
							),
							'add_row_button'    => esc_attr__( 'Add pair', 'uncanny-automator' ),
							'remove_row_button' => esc_attr__( 'Remove pair', 'uncanny-automator' ),
						),
					),
				),
			)
		);

		return $options;
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function create_post( $user_id, $action_data, $recipe_id, $args ) {

		$post_title                = Automator()->parse->text( $action_data['meta']['WPCPOSTTITLE'], $recipe_id, $user_id, $args );
		$post_terms                = Automator()->parse->text( $action_data['meta']['TERM'], $recipe_id, $user_id, $args );
		$post_slug                 = Automator()->parse->text( $action_data['meta']['WPCPOSTSLUG'], $recipe_id, $user_id, $args );
		$post_content              = Automator()->parse->text( $action_data['meta']['WPCPOSTCONTENT'], $recipe_id, $user_id, $args );
		$post_excerpt              = Automator()->parse->text( $action_data['meta']['WPCPOSTEXCERPT'], $recipe_id, $user_id, $args );
		$post_author               = Automator()->parse->text( $action_data['meta']['WPCPOSTAUTHOR'], $recipe_id, $user_id, $args );
		$post_status               = Automator()->parse->text( $action_data['meta']['WPCPOSTSTATUS'], $recipe_id, $user_id, $args );
		$post_fimage               = Automator()->parse->text( $action_data['meta']['FEATURED_IMAGE_URL'], $recipe_id, $user_id, $args );
		$post_fimage               = filter_var( $post_fimage, FILTER_SANITIZE_URL );
		$post_type                 = $action_data['meta'][ $this->action_code ];
		$post_args                 = array();
		$post_args['post_title']   = sanitize_text_field( $post_title );
		$post_args['post_name']    = sanitize_title( $post_slug );
		$post_args['post_content'] = $post_content;
		$post_args['post_excerpt'] = $post_excerpt;
		$post_args['post_type']    = $post_type;
		$post_args['post_status']  = $post_status;
		$post_args['post_author']  = 0;

		$fields_to_check = array( 'ID', 'login', 'email', 'slug' );

		foreach ( $fields_to_check as $field ) {
			$user = get_user_by( $field, $post_author );

			if ( ! empty( $user ) ) {
				$post_args['post_author'] = absint( $user->ID );
				break;
			}
		}

		$post_id = wp_insert_post( $post_args );

		if ( $post_id ) {

			$this->set_taxonomy_terms( $post_id, json_decode( $post_terms ) );

			if ( ! empty( $post_fimage ) ) {
				$this->add_featured_image( $post_fimage, $post_id );
			}

			$meta_pairs = json_decode( $action_data['meta']['CPMETA_PAIRS'], true );

			if ( ! empty( $meta_pairs ) ) {
				foreach ( $meta_pairs as $pair ) {
					$meta_key   = sanitize_title( $pair['KEY'] );
					$meta_value = sanitize_text_field( Automator()->parse->text( $pair['VALUE'], $recipe_id, $user_id, $args ) );
					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}
		}

		// Hydrate the tokens with value.
		$this->hydrate_tokens(
			array(
				'POST_ID'       => $post_id,
				'POST_URL'      => get_permalink( $post_id ),
				'POST_URL_EDIT' => add_query_arg(
					array(
						'post'   => $post_id,
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				),
			)
		);

		Automator()->complete_action( $user_id, $action_data, $recipe_id );

	}

	/**
	 * Set the taxonomy term relationship for the given post using post ID.
	 *
	 * @param array $taxonomy_terms The taxonomy and terms combination.
	 *
	 * @return boolean True if there are no errors. Otherwise, false.
	 */
	public function set_taxonomy_terms( $post_id = 0, $terms = array() ) {

		if ( empty( $terms ) || empty( $post_id ) ) {
			return false;
		}

		$curated_taxonomy = array();

		foreach ( $terms as $term ) {

			list( $taxonomy, $term )         = explode( ':', $term );
			$curated_taxonomy[ $taxonomy ][] = $term;

		}

		foreach ( $curated_taxonomy as $taxonomy => $taxonomy_terms ) {
			wp_set_object_terms( $post_id, (array) $taxonomy_terms, $taxonomy, false );
		}

		return true;

	}

	/**
	 * Adds a featured image using the image URL and post ID.
	 *
	 * @param $image_url
	 * @param $post_id
	 */
	public function add_featured_image( $image_url, $post_id ) {

		$upload_dir = wp_upload_dir();
		$image_data = file_get_contents( $image_url );
		$filename   = basename( $image_url );

		if ( wp_mkdir_p( $upload_dir['path'] ) ) {
			$file = $upload_dir['path'] . '/' . $filename;
		} else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}

		file_put_contents( $file, $image_data );

		$wp_filetype = wp_check_filetype( $filename, null );

		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

		$res1 = wp_update_attachment_metadata( $attach_id, $attach_data );

		$res2 = set_post_thumbnail( $post_id, $attach_id );

	}

	public function get_custom_post_types() {

		// Only public post type is available.
		$args = array(
			'public'   => true,
			'_builtin' => true,
		);

		$options = array();

		$post_types = get_post_types( $args, 'object', 'and' );

		if ( ! empty( $post_types ) ) {

			foreach ( $post_types as $post_type ) {

				$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );

			}
		}

		return array_merge( $options, $custom_post_types['options'] );

	}

	public function get_post_statuses() {

		// Get all the post stati objects.
		$post_statuses = get_post_stati( array(), 'objects' );

		// Initialise options.
		$status_options = array();

		// Make sure there are post stati.
		if ( ! empty( $post_statuses ) ) {

			// Loop through each post status object.
			foreach ( $post_statuses as $key => $post_status ) {

				// Initialise label indicating the source of the post status.
				$source_label = '';

				// Guess the source using the text-domain. See: https://developer.wordpress.org/reference/functions/register_post_status/.
				if ( is_array( $post_status->label_count ) ) {
					// Also see: https://developer.wordpress.org/reference/functions/_nx_noop/
					$source_label = isset( $post_status->label_count['domain'] ) ? "({$post_status->label_count['domain']})" : '';
				}

				// Set the post status and its source as the option's label.
				$status_options[ $key ] = $post_status->label . $source_label;
			}
		}

		$status_options = $this->remove_post_statuses( $status_options );

		return $status_options;

	}

	/**
	 * Removes post statuses from a given statuses.
	 *
	 * @param array $status_options
	 *
	 * @return array The status options.
	 */
	public function remove_post_statuses( $status_options ) {

		$disallowed_status = array( 'future', 'auto-draft', 'inherit' );

		foreach ( $disallowed_status as $status ) {

			if ( isset( $status_options[ $status ] ) ) {

				unset( $status_options[ $status ] );

			}
		}

		return $status_options;

	}

	/**
	 * Returns the field post type while removing the `any option`.
	 *
	 * @return array The type field.
	 */
	public function get_all_post_types() {

		$field = Automator()->helpers->recipe->wp->options->all_post_types(
			esc_attr__( 'Type', 'uncanny-automator' ),
			$this->action_code,
			array(
				'token'        => false,
				'is_ajax'      => true,
				'endpoint'     => 'select_specific_post_type_taxonomies',
				'target_field' => 'TAXONOMY',
			)
		);

		// Removed any options.
		unset( $field['options'][-1] );

		return $field;

	}

}
