<?php
/**
 * Keap Tag Fields Trait
 *
 * Provides tag selection, parsing, and result handling methods
 * for tag-related actions.
 *
 * @package Uncanny_Automator\Integrations\Keap
 * @since 7.0
 */

namespace Uncanny_Automator\Integrations\Keap;

/**
 * Trait Keap_Tag_Fields
 *
 * @property Keap_App_Helpers $helpers
 * @property Keap_Api_Caller $api
 */
trait Keap_Tag_Fields {

	/**
	 * Get tags select field configuration.
	 *
	 * @param string $code Field option code.
	 *
	 * @return array Field configuration.
	 */
	protected function get_tags_select_field_config( $code = 'TAG' ) {
		return array(
			'input_type'               => 'select',
			'option_code'              => $code,
			'label'                    => esc_html_x( 'Tag(s)', 'Keap', 'uncanny-automator' ),
			'required'                 => true,
			'supports_multiple_values' => true,
			'show_label_in_sentence'   => true,
			'options'                  => array(),
			'ajax'                     => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_keap_get_tags',
			),
		);
	}

	/**
	 * Get tags from parsed data.
	 *
	 * @param array  $parsed   Parsed action data.
	 * @param string $meta_key The meta key for the tag field.
	 *
	 * @return string Comma-separated tag IDs.
	 * @throws \Exception When tags are missing or invalid.
	 */
	protected function get_tags_from_parsed( $parsed, $meta_key = 'TAG' ) {

		if ( ! isset( $parsed[ $meta_key ] ) || empty( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing tag(s)', 'Keap', 'uncanny-automator' ) );
		}

		// Extract tags from the parsed data and remove empty values after converting to integers.
		$tags = array_filter( array_map( 'intval', json_decode( $parsed[ $meta_key ], true ) ) );
		if ( empty( $tags ) ) {
			throw new \Exception( esc_html_x( 'Invalid tag id(s)', 'Keap', 'uncanny-automator' ) );
		}

		// Return as CSV.
		return implode( ',', $tags );
	}

	/**
	 * Get tag names from IDs.
	 *
	 * @param mixed $tag_ids       Array or comma-separated string of tag IDs.
	 * @param bool  $string_value  Whether to return as string or array.
	 *
	 * @return mixed Array or comma-separated string of tag names.
	 */
	protected function get_tag_names_from_ids( $tag_ids, $string_value = true ) {
		$ids   = is_array( $tag_ids ) ? $tag_ids : array_map( 'trim', explode( ',', $tag_ids ) );
		$tags  = $this->helpers->get_tags();
		$names = array();
		foreach ( $ids as $tag_id ) {
			if ( isset( $tags[ $tag_id ] ) ) {
				$names[] = $tags[ $tag_id ]['text'];
			}
		}

		return $string_value ? implode( ', ', $names ) : $names;
	}

	/**
	 * Prepare tag notices from API results.
	 *
	 * @param array $results  API results keyed by tag ID.
	 * @param array $statuses Status message templates.
	 *
	 * @return array|false Array of notices or false if no errors.
	 */
	protected function prepare_tag_notices( $results, $statuses ) {
		// Group errors.
		$errors = array();
		foreach ( $results as $tag_id => $result ) {
			if ( 'SUCCESS' === $result ) {
				continue;
			}
			$errors[ $result ]   = isset( $errors[ $result ] ) ? $errors[ $result ] : array();
			$errors[ $result ][] = $tag_id;
		}

		// Prepare notices.
		$notices = ! empty( $errors ) ? array() : false;
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $status => $tag_ids ) {
				$notices[] = sprintf(
					/* translators: %s Tag ID(s) */
					$statuses[ $status ],
					$this->get_tag_names_from_ids( $tag_ids )
				);
			}
		}

		return $notices;
	}

	/**
	 * Get tag name action token definition.
	 *
	 * @return array Token configuration.
	 */
	protected function define_tag_name_action_token() {
		return array(
			'TAG_NAME' => array(
				'name' => esc_html_x( 'Tag name(s)', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}
}
