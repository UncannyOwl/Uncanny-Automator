<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_ADD_DONOR_TAG
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_ADD_DONOR_TAG extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CHARITABLE' );
		$this->set_action_code( 'CHARITABLE_ADD_DONOR_TAG' );
		$this->set_action_meta( 'DONOR_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Donor 2: Tag */
				esc_html_x( 'Add {{a tag:%2$s}} to {{a donor:%1$s}}', 'Charitable', 'uncanny-automator' ),
				$this->get_action_meta(),
				'DONOR_TAGS:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{a tag}} to {{a donor}}', 'Charitable', 'uncanny-automator' ) );
	}

	/**
	 * Action options.
	 *
	 * @return array
	 */
	public function options() {

		$helpers = $this->item_helpers;

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Donor', 'Charitable', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'remote_data'           => $helpers->remote_data_load_config( 'donors_strict' ),
			),
			array(
				'option_code'              => 'DONOR_TAGS',
				'label'                    => esc_html_x( 'Tag(s)', 'Charitable', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'supports_multiple_values' => true,
				'supports_custom_value'    => true,
				'remote_data'              => $helpers->remote_data_load_config( 'donor_tags_strict' ),
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( 'Charitable_Donor' ) ) {
			$this->add_log_error( 'Charitable is not active.' );
			return false;
		}

		$donor_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$new_tags = $this->parse_tags( $parsed, $action_data );

		if ( empty( $donor_id ) || empty( $new_tags ) ) {
			$this->add_log_error( 'Donor and tags are required.' );
			return false;
		}

		$donor = new \Charitable_Donor( $donor_id );
		if ( ! $donor || ! $donor->donor_id ) {
			$this->add_log_error( sprintf( 'Donor %d not found.', $donor_id ) );
			return false;
		}

		if ( ! method_exists( $donor, 'update_tags' ) ) {
			$this->add_log_error( 'Charitable_Donor::update_tags() is not available. Charitable Pro may be required.' );
			return false;
		}

		// Fetch existing tags so "Add" truly appends instead of replacing.
		$existing_tags = array();
		if ( method_exists( $donor, 'get_tags' ) ) {
			$raw_existing = $donor->get_tags();
			if ( is_array( $raw_existing ) ) {
				$existing_tags = array_map(
					function ( $tag ) {
						if ( is_object( $tag ) && isset( $tag->name ) ) {
							return $tag->name;
						}
						return (string) $tag;
					},
					$raw_existing
				);
			} elseif ( is_string( $raw_existing ) && '' !== $raw_existing ) {
				$existing_tags = array_map( 'trim', explode( ',', $raw_existing ) );
			}
		}

		$tags = array_values( array_unique( array_filter( array_merge( $existing_tags, $new_tags ) ) ) );

		$result = $donor->update_tags(
			array(
				'ID'   => $donor_id,
				'tags' => $tags,
			)
		);

		if ( false === $result || is_wp_error( $result ) ) {
			$this->add_log_error( is_wp_error( $result ) ? $result->get_error_message() : 'Failed to update donor tags.' );
			return false;
		}

		return true;
	}

	/**
	 * Resolve the tags field into a clean array of tag names.
	 *
	 * supports_multiple_values stores the meta as a JSON array; entries may also
	 * arrive as comma-separated strings (a single custom entry, or a token that
	 * resolves to a CSV list), so each entry is exploded too.
	 *
	 * @param array $parsed
	 * @param array $action_data
	 *
	 * @return array
	 */
	private function parse_tags( $parsed, $action_data ) {

		$raw = $parsed['DONOR_TAGS'] ?? $action_data['meta']['DONOR_TAGS'] ?? '';

		if ( ! is_array( $raw ) ) {
			$decoded = json_decode( (string) $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array( $raw );
		}

		$tags = array();
		foreach ( $raw as $entry ) {
			$entry = is_scalar( $entry ) ? (string) $entry : '';
			foreach ( explode( ',', $entry ) as $piece ) {
				$piece = sanitize_text_field( trim( $piece ) );
				if ( '' !== $piece ) {
					$tags[] = $piece;
				}
			}
		}

		return array_values( array_unique( $tags ) );
	}
}
