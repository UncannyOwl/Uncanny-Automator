<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_CREATEPOST
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_CREATEPOST extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'CREATEPOST' );
		$this->set_action_meta( 'WPPOSTFIELDS' );
		$this->set_requires_user( false );
		// translators: %1$s is the post type.
		$this->set_sentence( sprintf( esc_html_x( 'Create {{a post:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create {{a post}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POST_URL',
				'tokenName' => esc_html_x( 'Post URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POST_URL_EDIT',
				'tokenName' => esc_html_x( 'Post edit URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {

		return array(

			$this->get_all_post_types(),

			array(
				'option_code'           => 'PARENT_POST',
				'label'                 => esc_html_x( 'Parent post', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => array(),
				'supports_custom_value' => true,
				'required'              => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'posts_by_post_type', array( $this->get_action_code() ) ),
			),

			array(
				'input_type'               => 'select',
				'option_code'              => 'TAXONOMY',
				'label'                    => esc_html_x( 'Taxonomies', 'WordPress', 'uncanny-automator' ),
				'custom_value_description' => esc_html_x( 'Taxonomy ID. Separated by comma.', 'WordPress', 'uncanny-automator' ),
				'supports_multiple_values' => true,
				'supports_custom_value'    => true,
				'supports_tokens'          => false,
				'required'                 => false,
				'options'                  => array(),
				'remote_data'              => $this->item_helpers->remote_data_parent_config( 'specific_post_type_taxonomies', array( $this->get_action_code() ) ),
			),

			array(
				'input_type'               => 'select',
				'option_code'              => 'TERM',
				'label'                    => esc_html_x( 'Terms', 'WordPress', 'uncanny-automator' ),
				'custom_value_description' => esc_html_x( 'Term ID. Separated by comma.', 'WordPress', 'uncanny-automator' ),
				'supports_multiple_values' => true,
				'supports_custom_value'    => true,
				'supports_tokens'          => false,
				'required'                 => false,
				'options'                  => array(),
				'remote_data'              => $this->item_helpers->remote_data_parent_config( 'specific_taxonomy_terms', array( 'TAXONOMY' ) ),
			),

			array(
				'option_code'           => 'WPCPOSTSTATUS',
				'label'                 => esc_html_x( 'Status', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_post_status_options(),
				'supports_custom_value' => true,
			),

			array(
				'option_code'   => 'WPCPOSTAUTHOR',
				'label'         => esc_html_x( 'Author', 'WordPress', 'uncanny-automator' ),
				'input_type'    => 'text',
				'required'      => true,
				'default_value' => '{{admin_email}}',
				'description'   => esc_html_x( 'Accepts user ID, email or username', 'WordPress', 'uncanny-automator' ),
			),

			array(
				'option_code' => 'WPCPOSTTITLE',
				'label'       => esc_html_x( 'Title', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),

			array(
				'option_code'   => 'IS_UNIQUE',
				'label'         => esc_html_x( 'Title must be unique', 'WordPress', 'uncanny-automator' ),
				'description'   => esc_html_x( 'If a post with the same title is found, the action will not create a new post.', 'WordPress', 'uncanny-automator' ),
				'input_type'    => 'checkbox',
				'is_toggle'     => true,
				'required'      => false,
				'default_value' => false,
			),

			array(
				'option_code' => 'WPCPOSTSLUG',
				'label'       => esc_html_x( 'Slug', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),

			array(
				'option_code' => 'WPCPOSTCONTENT',
				'label'       => esc_html_x( 'Content', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),

			array(
				'option_code' => 'WPCPOSTCONTENTCUSTOMCSSCHECKBOX',
				'label'       => esc_html_x( 'Add custom CSS', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'required'    => false,
			),

			array(
				'option_code'        => 'WPCPOSTCONTENTCUSTOMCSS',
				'label'              => esc_html_x( 'Custom CSS', 'WordPress', 'uncanny-automator' ),
				'input_type'         => 'textarea',
				'required'           => false,
				'description'        => esc_html_x( 'Enter your CSS code into the field above. Please make sure that your CSS rules are correct and targeted appropriately.', 'WordPress', 'uncanny-automator' ),
				'dynamic_visibility' => array(
					'default_state'    => 'hidden',
					'visibility_rules' => array(
						array(
							'operator'             => 'AND',
							'rule_conditions'      => array(
								array(
									'option_code' => 'WPCPOSTCONTENTCUSTOMCSSCHECKBOX',
									'compare'     => '==',
									'value'       => true,
								),
							),
							'resulting_visibility' => 'show',
						),
					),
				),
			),

			array(
				'option_code' => 'WPCPOSTCONTENTCUSTOMJSCHECKBOX',
				'label'       => esc_html_x( 'Add custom JavaScript', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'required'    => false,
			),

			array(
				'option_code'        => 'WPCPOSTCONTENTCUSTOMJS',
				'label'              => esc_html_x( 'Custom JavaScript', 'WordPress', 'uncanny-automator' ),
				'input_type'         => 'textarea',
				'required'           => false,
				'description'        => esc_html_x( "Enter your JavaScript code into the field above. Because JavaScript can affect your site's behaviour and security, only use scripts from trusted sources and make sure to validate and sanitize your inputs.", 'WordPress', 'uncanny-automator' ),
				'dynamic_visibility' => array(
					'default_state'    => 'hidden',
					'visibility_rules' => array(
						array(
							'operator'             => 'AND',
							'rule_conditions'      => array(
								array(
									'option_code' => 'WPCPOSTCONTENTCUSTOMJSCHECKBOX',
									'compare'     => '==',
									'value'       => true,
								),
							),
							'resulting_visibility' => 'show',
						),
					),
				),
			),

			array(
				'option_code' => 'WPCPOSTEXCERPT',
				'label'       => esc_html_x( 'Excerpt', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),

			array(
				'option_code' => 'FEATURED_IMAGE_URL',
				'label'       => esc_html_x( 'Featured image URL', 'WordPress', 'uncanny-automator' ),
				'placeholder' => esc_html_x( 'https://examplewebsite.com/path/to/image.jpg', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'url',
				'required'    => false,
				'description' => esc_html_x( 'The URL must include a supported image file extension (e.g. .jpg, .png, .svg, etc.). Some sites may block remote image download.', 'WordPress', 'uncanny-automator' ),
			),

			array(
				'option_code'   => 'OPEN_COMMENTS',
				'label'         => esc_html_x( 'Allow people to submit comments', 'WordPress', 'uncanny-automator' ),
				'input_type'    => 'checkbox',
				'is_toggle'     => true,
				'required'      => false,
				'default_value' => 'open' === get_option( 'default_comment_status', 'open' ),
			),

			array(
				'input_type'        => 'repeater',
				'option_code'       => 'CPMETA_PAIRS',
				'label'             => esc_html_x( 'Meta', 'WordPress', 'uncanny-automator' ),
				'required'          => false,
				'fields'            => array(
					array(
						'input_type'      => 'text',
						'option_code'     => 'KEY',
						'label'           => esc_html_x( 'Key', 'WordPress', 'uncanny-automator' ),
						'supports_tokens' => true,
						'required'        => true,
					),
					array(
						'input_type'      => 'text',
						'option_code'     => 'VALUE',
						'label'           => esc_html_x( 'Value', 'WordPress', 'uncanny-automator' ),
						'supports_tokens' => true,
						'required'        => true,
					),
				),
				'add_row_button'    => esc_html_x( 'Add pair', 'WordPress', 'uncanny-automator' ),
				'remove_row_button' => esc_html_x( 'Remove pair', 'WordPress', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$post_title = sanitize_text_field( $parsed['WPCPOSTTITLE'] ?? '' );
		$post_terms = $parsed['TERM'] ?? '';
		$post_slug  = $parsed['WPCPOSTSLUG'] ?? '';
		$content    = $parsed['WPCPOSTCONTENT'] ?? '';
		$post_type  = $parsed[ $this->get_action_code() ] ?? '';

		// Check unique title toggle.
		$unique_title_field_val = $action_data['meta']['IS_UNIQUE'] ?? '';

		if ( 'true' === $unique_title_field_val && true === $this->post_title_exists( $post_title, $post_type ) ) {
			$this->add_log_error(
				sprintf(
					// translators: %s: Post title.
					esc_html_x( "Post title must be unique. A post with the title '%s' already exists.", 'WordPress', 'uncanny-automator' ),
					esc_html( $post_title )
				)
			);
			return false;
		}

		$post_content = '';

		// Add custom CSS.
		if ( isset( $action_data['meta']['WPCPOSTCONTENTCUSTOMCSSCHECKBOX'] )
			&& 'true' === $action_data['meta']['WPCPOSTCONTENTCUSTOMCSSCHECKBOX']
		) {
			$custom_css    = $parsed['WPCPOSTCONTENTCUSTOMCSS'] ?? '';
			$post_content .= '<!-- wp:html --><style>' . $custom_css . '</style><!-- /wp:html -->';
		}

		// Add custom JS.
		if ( isset( $action_data['meta']['WPCPOSTCONTENTCUSTOMJSCHECKBOX'] )
			&& 'true' === $action_data['meta']['WPCPOSTCONTENTCUSTOMJSCHECKBOX']
		) {
			$custom_js     = $parsed['WPCPOSTCONTENTCUSTOMJS'] ?? '';
			$post_content .= '<!-- wp:html --><script>' . $custom_js . '</script><!-- /wp:html -->';
		}

		$should_wp_slash = apply_filters( 'automator_create_posts_should_wpslash', false, $action_data );

		if ( true === $should_wp_slash ) {
			$content = wp_slash( $content );
		}

		$post_content .= $content;

		$post_excerpt       = $parsed['WPCPOSTEXCERPT'] ?? '';
		$post_author        = $parsed['WPCPOSTAUTHOR'] ?? '';
		$post_status        = $parsed['WPCPOSTSTATUS'] ?? '';
		$post_fimage        = filter_var( $parsed['FEATURED_IMAGE_URL'] ?? '', FILTER_SANITIZE_URL );
		$post_open_comments = 'open' === get_option( 'default_comment_status', 'closed' ) ? 'true' : 'false';
		$post_parent        = 0;

		if ( isset( $parsed['PARENT_POST'] ) ) {
			$post_parent = absint( $parsed['PARENT_POST'] );
		}

		if ( isset( $action_data['meta']['OPEN_COMMENTS'] ) ) {
			$post_open_comments = sanitize_text_field( $action_data['meta']['OPEN_COMMENTS'] );
		}

		$post_args = array(
			'post_title'     => $post_title,
			'post_name'      => sanitize_title( $post_slug ),
			'post_content'   => $post_content,
			'post_excerpt'   => $post_excerpt,
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'post_author'    => 0,
			'post_parent'    => $post_parent,
			'comment_status' => ( 'true' === $post_open_comments ) ? 'open' : 'closed',
		);

		// Resolve author from multiple field types.
		$fields_to_check = array( 'ID', 'login', 'email', 'slug' );

		foreach ( $fields_to_check as $field ) {
			$user = get_user_by( $field, $post_author );

			if ( ! empty( $user ) ) {
				$post_args['post_author'] = absint( $user->ID );
				break;
			}
		}

		$return_wp_error  = apply_filters( 'automator_create_post_return_wp_error', true, $post_args, $recipe_id, $action_data );
		$fire_after_hooks = apply_filters( 'automator_create_post_fire_after_hooks', true, $post_args, $recipe_id, $action_data );

		$post_id = wp_insert_post( $post_args, $return_wp_error, $fire_after_hooks );

		if ( is_wp_error( $post_id ) ) {
			$this->add_log_error( $post_id->get_error_message() );
			return false;
		}

		if ( $post_id ) {
			// Handle custom value provided TERMs.
			if ( $this->is_token_custom_value_text( $action_data['meta']['TERM_readable'] ?? '' ) ) {
				$this->set_custom_taxonomy_terms( $post_id, $post_terms );
			} else {
				$this->set_taxonomy_terms( $post_id, json_decode( $post_terms ) );
			}

			$featured_image_error = '';
			if ( ! empty( $post_fimage ) ) {
				$featured_image_result = $this->add_featured_image( $post_fimage, $post_id );
				if ( is_wp_error( $featured_image_result ) ) {
					$featured_image_error = $featured_image_result->get_error_message();
				}
			}

			$meta_pairs = json_decode( $action_data['meta']['CPMETA_PAIRS'] ?? '[]', true );

			if ( ! empty( $meta_pairs ) ) {
				foreach ( $meta_pairs as $pair ) {
					$meta_key   = \Automator()->parse->text( $pair['KEY'] ?? '', $recipe_id, $user_id, $args );
					$meta_value = \Automator()->parse->text( $pair['VALUE'] ?? '', $recipe_id, $user_id, $args );

					if (
						true === apply_filters( 'automator_create_post_sanitize_meta_values', true, $meta_key, $meta_value, $recipe_id ) &&
						true === apply_filters( 'automator_create_post_sanitize_meta_values_' . $recipe_id, true, $meta_key, $meta_value ) &&
						true === apply_filters( 'automator_create_post_sanitize_meta_values_' . sanitize_title( $meta_key ), true, $meta_value, $recipe_id )
					) {
						$meta_key   = \Automator()->utilities->automator_sanitize(
							$meta_key,
							apply_filters( 'automator_sanitize_get_field_type', 'text', $meta_key, array() )
						);
						$meta_value = \Automator()->utilities->automator_sanitize(
							$meta_value,
							apply_filters( 'automator_sanitize_get_field_type', 'text', $meta_value, array() )
						);
					}
					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}
		}

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

		if ( ! empty( $featured_image_error ) ) {
			// Post was created — featured image failure is a notice, not a fatal error.
			$this->set_complete_with_notice( true );
			$this->add_log_error( $featured_image_error );
		}

		return true;
	}

	/**
	 * Check if the value is a custom value text.
	 *
	 * @param string $string_to_check The string to check.
	 *
	 * @return bool
	 */
	private function is_token_custom_value_text( $string_to_check ) {
		return esc_attr__( 'Use a token/custom value', 'uncanny-automator' ) === $string_to_check; // phpcs:ignore Uncanny_Automator.Strings
	}

	/**
	 * Set the taxonomy term relationship for the given post using post ID.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $terms   The terms array.
	 *
	 * @return bool
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
	 * Set custom taxonomy terms (single term ID or CSV).
	 *
	 * @param int    $post_id The post ID.
	 * @param string $terms   The terms string (single ID or CSV).
	 *
	 * @return bool
	 */
	public function set_custom_taxonomy_terms( $post_id = 0, $terms = '' ) {

		if ( empty( $terms ) || empty( $post_id ) ) {
			return false;
		}

		$curated_taxonomy = array();
		$term_ids         = array_map( 'trim', explode( ',', $terms ) );

		foreach ( $term_ids as $term_id ) {
			if ( ctype_digit( $term_id ) ) {
				$term_obj = get_term( $term_id );
				if ( $term_obj && ! is_wp_error( $term_obj ) ) {
					$curated_taxonomy[ $term_obj->taxonomy ][] = (int) $term_id;
				}
			}
		}

		foreach ( $curated_taxonomy as $taxonomy => $taxonomy_terms ) {
			wp_set_object_terms( $post_id, (array) $taxonomy_terms, $taxonomy, false );
		}

		return true;
	}

	/**
	 * Adds a featured image using the image URL and post ID.
	 *
	 * @param string|int $image_url The image URL or attachment ID.
	 * @param int        $post_id   The post ID.
	 *
	 * @return int|\WP_Error
	 */
	public function add_featured_image( $image_url, $post_id ) {

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Prevents double image downloading.
		$existing_media_id = absint( attachment_url_to_postid( $image_url ) );

		// If existing_media_id is not equals 0, it means media already exists.
		if ( 0 !== $existing_media_id ) {
			$image_url = $existing_media_id;
		}

		// Supports numeric input.
		if ( is_numeric( $image_url ) ) {
			return set_post_thumbnail( $post_id, $image_url );
		}

		// Block requests to private/reserved IP ranges before sideloading.
		if ( automator_resolves_to_private_ip( $image_url ) ) {
			automator_log( sprintf( 'Blocked featured image sideload — URL resolves to a private IP: %s', esc_url( $image_url ) ), self::class . '->add_featured_image', true, 'wp-createpost' );
			return new \WP_Error( 'ssrf_blocked', 'The featured image URL resolves to a private or reserved IP address and cannot be used.' );
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
	 * Get post status options for the select field.
	 *
	 * @return array
	 */
	private function get_post_status_options() {

		$post_statuses = get_post_stati( array(), 'objects' );
		$options       = array();
		$disallowed    = array( 'future', 'auto-draft', 'inherit' );

		if ( ! empty( $post_statuses ) ) {
			foreach ( $post_statuses as $key => $post_status ) {

				if ( in_array( $key, $disallowed, true ) ) {
					continue;
				}

				$source_label = '';

				if ( is_array( $post_status->label_count ) ) {
					$source_label = isset( $post_status->label_count['domain'] ) ? "({$post_status->label_count['domain']})" : '';
				}

				$options[] = array(
					'value' => $key,
					'text'  => $post_status->label . $source_label,
				);
			}
		}

		return $options;
	}

	/**
	 * Returns the post type field without the "Any" option.
	 *
	 * @return array
	 */
	private function get_all_post_types() {

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$options    = array();
		$disabled   = array( 'attachment' );

		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type->name, $disabled, true ) ) {
				continue;
			}
			$options[] = array(
				'value' => $post_type->name,
				'text'  => $post_type->labels->singular_name,
			);
		}

		return array(
			'option_code'           => $this->get_action_code(),
			'label'                 => esc_html_x( 'Type', 'WordPress', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $options,
			'supports_custom_value' => true,
		);
	}

	/**
	 * Check if a post with a specific title exists, regardless of case.
	 *
	 * @param string $title     The title to search for.
	 * @param string $post_type The post type.
	 *
	 * @return bool
	 */
	private function post_title_exists( $title, $post_type ) {

		global $wpdb;

		$title = sanitize_title( $title );

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s",
				$title,
				$post_type
			)
		);

		return ! empty( $post_id );
	}
}
