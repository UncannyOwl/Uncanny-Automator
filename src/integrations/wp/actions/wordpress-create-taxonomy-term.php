<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_CREATE_TAXONOMY_TERM
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_CREATE_TAXONOMY_TERM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_CREATE_TAX_TERM' );
		$this->set_action_meta( 'WP_TAXONOMY' );
		$this->set_requires_user( false );
		// translators: 1: Taxonomy, 2: Term name.
		$this->set_sentence( sprintf( esc_html_x( 'Create {{a term:%2$s}} in {{a taxonomy:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta(), 'WP_TERM_NAME:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create {{a term}} in {{a taxonomy}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'TERM_ID',
				'tokenName' => esc_html_x( 'Term ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TERM_NAME',
				'tokenName' => esc_html_x( 'Term name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TERM_SLUG',
				'tokenName' => esc_html_x( 'Term slug', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TAXONOMY_NAME',
				'tokenName' => esc_html_x( 'Taxonomy name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'WP_POST_TYPE',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
				'options'               => array(),
				'supports_custom_value' => true,
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types_strict' ),
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Taxonomy', 'WordPress', 'uncanny-automator' ),
				'options'               => array(),
				'supports_custom_value' => true,
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'taxonomies_by_type', array( 'WP_POST_TYPE' ) ),
			),
			array(
				'option_code' => 'WP_TERM_NAME',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Term name', 'WordPress', 'uncanny-automator' ),
				'required'    => true,
			),
			array(
				'option_code' => 'WP_TERM_SLUG',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Term slug', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'WP_TERM_DESCRIPTION',
				'input_type'  => 'textarea',
				'label'       => esc_html_x( 'Term description', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'WP_PARENT_TERM',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Parent term ID', 'WordPress', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enter a term ID for hierarchical taxonomies', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
		);
	}

	/**
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$term_name   = sanitize_text_field( $parsed['WP_TERM_NAME'] ?? '' );
		$taxonomy    = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$slug        = sanitize_text_field( $parsed['WP_TERM_SLUG'] ?? '' );
		$description = sanitize_textarea_field( $parsed['WP_TERM_DESCRIPTION'] ?? '' );
		$parent      = sanitize_text_field( $parsed['WP_PARENT_TERM'] ?? '' );

		if ( empty( $term_name ) ) {
			$this->add_log_error( esc_html_x( 'Term name is required', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		if ( empty( $taxonomy ) ) {
			$this->add_log_error( esc_html_x( 'Taxonomy is required', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		if ( false === taxonomy_exists( $taxonomy ) ) {
			// translators: %s: Taxonomy name.
			$this->add_log_error( sprintf( esc_html_x( 'The taxonomy "%s" does not exist', 'WordPress', 'uncanny-automator' ), $taxonomy ) );

			return false;
		}

		$term_args = array();

		if ( '' !== $slug ) {
			$term_args['slug'] = $slug;
		}

		if ( '' !== $description ) {
			$term_args['description'] = $description;
		}

		if ( '' !== $parent ) {
			$term_args['parent'] = absint( $parent );
		}

		$result = wp_insert_term( $term_name, $taxonomy, $term_args );

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );

			return false;
		}

		$term = get_term( $result['term_id'], $taxonomy );

		$this->hydrate_tokens(
			array(
				'TERM_ID'       => $result['term_id'],
				'TERM_NAME'     => $term_name,
				'TERM_SLUG'     => $term instanceof \WP_Term ? $term->slug : $slug,
				'TAXONOMY_NAME' => $taxonomy,
			)
		);

		return true;
	}
}
