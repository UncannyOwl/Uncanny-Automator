<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_UPDATE_TAXONOMY_TERM
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_UPDATE_TAXONOMY_TERM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_UPDATE_TAX_TERM' );
		$this->set_action_meta( 'WP_TAXONOMY' );
		$this->set_requires_user( false );
		// translators: 1: Taxonomy, 2: Term.
		$this->set_sentence( sprintf( esc_html_x( 'Update {{a term:%2$s}} in {{a taxonomy:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta(), 'WP_TERM:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Update {{a term}} in {{a taxonomy}}', 'WordPress', 'uncanny-automator' ) );
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
				'option_code'           => 'WP_TERM',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Term', 'WordPress', 'uncanny-automator' ),
				'options'               => array(),
				'supports_custom_value' => true,
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'terms_by_taxonomy', array( $this->get_action_meta() ) ),
			),
			array(
				'option_code' => 'WP_TERM_NAME',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'New name', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'WP_TERM_SLUG',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'New slug', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'WP_TERM_DESCRIPTION',
				'input_type'  => 'textarea',
				'label'       => esc_html_x( 'New description', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$taxonomy    = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$term_id     = absint( sanitize_text_field( $parsed['WP_TERM'] ?? '' ) );
		$name        = sanitize_text_field( $parsed['WP_TERM_NAME'] ?? '' );
		$slug        = sanitize_text_field( $parsed['WP_TERM_SLUG'] ?? '' );
		$description = sanitize_textarea_field( $parsed['WP_TERM_DESCRIPTION'] ?? '' );

		if ( empty( $taxonomy ) ) {
			$this->add_log_error( esc_html_x( 'Taxonomy is required.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		if ( false === taxonomy_exists( $taxonomy ) ) {
			// translators: %s: Taxonomy name.
			$this->add_log_error( sprintf( esc_html_x( 'The taxonomy "%s" does not exist.', 'WordPress', 'uncanny-automator' ), $taxonomy ) );

			return false;
		}

		if ( 0 === $term_id ) {
			$this->add_log_error( esc_html_x( 'Invalid term ID.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( null === $term || is_wp_error( $term ) ) {
			// translators: %d: Term ID.
			$this->add_log_error( sprintf( esc_html_x( 'Term with ID %d does not exist.', 'WordPress', 'uncanny-automator' ), $term_id ) );

			return false;
		}

		$term_args = array();

		if ( '' !== $name ) {
			$term_args['name'] = $name;
		}

		if ( '' !== $slug ) {
			$term_args['slug'] = $slug;
		}

		if ( '' !== $description ) {
			$term_args['description'] = $description;
		}

		if ( empty( $term_args ) ) {
			$this->add_log_error( esc_html_x( 'No fields provided to update.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$result = wp_update_term( $term_id, $taxonomy, $term_args );

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );

			return false;
		}

		$updated_term = get_term( $result['term_id'], $taxonomy );

		$this->hydrate_tokens(
			array(
				'TERM_ID'       => $result['term_id'],
				'TERM_NAME'     => $updated_term instanceof \WP_Term ? $updated_term->name : $name,
				'TERM_SLUG'     => $updated_term instanceof \WP_Term ? $updated_term->slug : $slug,
				'TAXONOMY_NAME' => $taxonomy,
			)
		);

		return true;
	}
}
