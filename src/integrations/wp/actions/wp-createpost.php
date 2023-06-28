<?php

namespace Uncanny_Automator;

/**
 * Class WP_CREATEPOST
 *
 * @package Uncanny_Automator
 */
class WP_CREATEPOST {

	use Recipe\Action_Tokens;

	/**
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * @var string
	 */
	private $action_code = 'CREATEPOST';

	/**
	 * @var string
	 */
	private $action_meta = 'WPPOSTFIELDS';

	/**
	 *
	 */
	public function __construct() {

		$this->define_action();

	}

	/**
	 * @return void
	 * @throws \Exception
	 */
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

	/**
	 * @return array
	 */
	public function load_options() {

		Automator()->helpers->recipe->wp->options->load_options = true;

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->action_code => array(

						$this->get_all_post_types(),

						Automator()->helpers->recipe->field->select_field_args(
							array(
								'option_code'           => 'PARENT_POST',
								'label'                 => esc_attr__( 'Parent post', 'uncanny-automator' ),
								'options'               => array(),
								'is_ajax'               => true,
								'endpoint'              => 'select_specific_post_type_taxonomies',
								'target_field'          => 'TAXONOMY',
								'supports_custom_value' => true,
								'relevant_tokens'       => array(),
							)
						),

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

						// The photo url field.
						array(
							'option_code'   => 'OPEN_COMMENTS',
							/* translators: Allow comment field */
							'label'         => esc_attr__( 'Allow people to submit comments', 'uncanny-automator' ),
							'input_type'    => 'checkbox',
							'is_toggle'     => true,
							'required'      => false,
							'default_value' => 'open' === get_option( 'default_comment_status', 'open' ),
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

		$post_title         = Automator()->parse->text( $action_data['meta']['WPCPOSTTITLE'], $recipe_id, $user_id, $args );
		$post_terms         = Automator()->parse->text( $action_data['meta']['TERM'], $recipe_id, $user_id, $args );
		$post_slug          = Automator()->parse->text( $action_data['meta']['WPCPOSTSLUG'], $recipe_id, $user_id, $args );
		$post_content       = Automator()->parse->text( $action_data['meta']['WPCPOSTCONTENT'], $recipe_id, $user_id, $args );
		$post_excerpt       = Automator()->parse->text( $action_data['meta']['WPCPOSTEXCERPT'], $recipe_id, $user_id, $args );
		$post_author        = Automator()->parse->text( $action_data['meta']['WPCPOSTAUTHOR'], $recipe_id, $user_id, $args );
		$post_status        = Automator()->parse->text( $action_data['meta']['WPCPOSTSTATUS'], $recipe_id, $user_id, $args );
		$post_fimage        = Automator()->parse->text( $action_data['meta']['FEATURED_IMAGE_URL'], $recipe_id, $user_id, $args );
		$post_fimage        = filter_var( $post_fimage, FILTER_SANITIZE_URL );
		$post_open_comments = 'open' === get_option( 'default_comment_status', 'closed' ) ? 'true' : 'false';
		$post_type          = $action_data['meta'][ $this->action_code ];
		$post_parent        = 0;

		if ( isset( $action_data['meta']['PARENT_POST'] ) ) {
			$post_parent = Automator()->parse->text( $action_data['meta']['PARENT_POST'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['OPEN_COMMENTS'] ) ) {
			$post_open_comments = sanitize_text_field( $action_data['meta']['OPEN_COMMENTS'] );
		}

		$post_args                   = array();
		$post_args['post_title']     = sanitize_text_field( $post_title );
		$post_args['post_name']      = sanitize_title( $post_slug );
		$post_args['post_content']   = $post_content;
		$post_args['post_excerpt']   = $post_excerpt;
		$post_args['post_type']      = $post_type;
		$post_args['post_status']    = $post_status;
		$post_args['post_author']    = 0;
		$post_args['post_parent']    = $post_parent;
		$post_args['comment_status'] = ( 'true' === $post_open_comments ) ? 'open' : 'closed';

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
					$meta_key   = $pair['KEY'];
					$meta_value = Automator()->parse->text( $pair['VALUE'], $recipe_id, $user_id, $args );
					if (
						true === apply_filters( 'automator_create_post_sanitize_meta_values', true, $meta_key, $meta_value, $recipe_id ) &&
						true === apply_filters( 'automator_create_post_sanitize_meta_values_' . $recipe_id, true, $meta_key, $meta_value ) &&
						true === apply_filters( 'automator_create_post_sanitize_meta_values_' . sanitize_title( $meta_key ), true, $meta_value, $recipe_id )
					) {
						$meta_key   = Automator()->utilities->automator_sanitize(
							$meta_key,
							apply_filters( 'automator_sanitize_get_field_type', 'text', $meta_key, array() )
						);
						$meta_value = Automator()->utilities->automator_sanitize(
							$meta_value,
							apply_filters( 'automator_sanitize_get_field_type', 'text', $meta_value, array() )
						);
					}
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

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Prevents double image downloading.
		$existing_media_id = absint( attachment_url_to_postid( $image_url ) );

		// If existing_media_id is not equals 0, it means media already exists.
		if ( 0 !== $existing_media_id ) {
			// Overwrite the image url with the existing media.
			$image_url = $existing_media_id;
		}

		// Supports numeric input.
		if ( is_numeric( $image_url ) ) {
			// The $image_url is numeric.
			return set_post_thumbnail( $post_id, $image_url );
		}

		// Otherwise, download and store the image as attachment.
		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

		// Assign the downloaded attachment ID to the post.
		set_post_thumbnail( $post_id, $attachment_id );

		if ( is_wp_error( $attachment_id ) ) {
			automator_log( $attachment_id->get_error_message(), self::class . '->add_featured_image error', true, 'wp-createpost' );
		}

		return $attachment_id;

	}

	/**
	 * @return array
	 */
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
				'endpoint'     => 'select_all_post_from_SELECTEDPOSTTYPE',
				'target_field' => 'PARENT_POST',
			)
		);

		// Removed any options.
		unset( $field['options']['-1'] );

		return $field;

	}

}
