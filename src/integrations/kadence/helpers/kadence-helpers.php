<?php

namespace Uncanny_Automator\Integrations\Kadence;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Kadence_Helpers
 *
 * Extends Abstract_Helpers so the Integration auto-registers the
 * remote_data REST framework (`/wp-json/uap/v2/remote-data/kadence/{data}`)
 * that serves the trigger select fields.
 *
 * @package Uncanny_Automator\Integrations\Kadence
 */
class Kadence_Helpers extends Abstract_Helpers {

	/**
	 * Normalize raw submission hook args from either Kadence hook into the
	 * ( $fields_data, $unique_id, $post_id ) triple the triggers consume.
	 *
	 * kadence_blocks_form_submission passes ( $form_args, $fields, $form_id, $post_id )
	 * where $form_id is the block uniqueID ("{post_id}_{hash}").
	 * kadence_blocks_advanced_form_submission passes ( $form_args, $fields, $post_id )
	 * where $post_id is the kadence_form CPT post ID.
	 *
	 * The fired hook is read via current_action() — Trigger_Queue replays the
	 * hook context (call_with_filter_context) for both in-request and loopback
	 * processing, so this is reliable in every dispatch path.
	 *
	 * @param array $hook_args Raw hook arguments.
	 *
	 * @return array ( array $fields_data, string|null $unique_id, string|int $post_id )
	 */
	public function normalize_submission_hook_args( $hook_args ) {

		$fields_data = isset( $hook_args[1] ) ? (array) $hook_args[1] : array();

		if ( 'kadence_blocks_advanced_form_submission' === current_action() ) {
			return array( $fields_data, null, isset( $hook_args[2] ) ? $hook_args[2] : 0 );
		}

		return array(
			$fields_data,
			isset( $hook_args[2] ) ? $hook_args[2] : null,
			isset( $hook_args[3] ) ? $hook_args[3] : 0,
		);
	}

	/**
	 * Build the form select options across classic form blocks and advanced
	 * (CPT) forms, optionally prefixed with an "Any form" / "All forms" entry.
	 *
	 * @param bool $is_any Whether to prepend the "Any form" (-1) option.
	 * @param bool $is_all Whether to prepend the "All forms" (-1) option.
	 *
	 * @return array List of { text, value } option arrays.
	 */
	public function get_all_kadence_form_options( $is_any = false, $is_all = false ) {
		$all_forms = array();

		if ( true === $is_all ) {
			$all_forms[] = array(
				'text'  => esc_attr_x( 'All forms', 'Kadence', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( true === $is_any ) {
			$all_forms[] = array(
				'text'  => esc_attr_x( 'Any form', 'Kadence', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$forms_options = $this->get_forms_attributes_from_content();

		if ( defined( 'KADENCE_BLOCKS_VERSION' ) ) {
			$args = array(
				'orderby'        => 'title',
				'order'          => 'DESC',
				'post_type'      => 'kadence_form',
				'post_status'    => 'publish',
				'posts_per_page' => 99999,
			);

			$forms = automator_wp_query( $args, 'legacy' );
			foreach ( $forms as $k => $form ) {
				$all_forms[] = array(
					'text'  => $form,
					'value' => $k,
				);
			}
		}

		return array_merge( $all_forms, $forms_options );
	}

	/**
	 * Parse classic kadence/form blocks out of post content, returning either
	 * the form select options or the fields of a single form.
	 *
	 * @param bool|string $all_forms   True to scan all posts, or a form uniqueID
	 *                                 ("{post_id}_{hash}") to scope to one form.
	 * @param string      $result_type Either 'options' (form list) or 'fields'
	 *                                 (fields of the matching form).
	 *
	 * @return array List of { text, value } options, or { label, type } fields.
	 */
	public function get_forms_attributes_from_content( $all_forms = true, $result_type = 'options' ) {
		global $wpdb;

		$form_options = array();
		$form_fields  = array();
		if ( true !== $all_forms ) {
			$form_uid = explode( '_', $all_forms );
			if ( is_array( $form_uid ) ) {
				$post_id = $form_uid[0];
			}
			$forms = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM $wpdb->posts WHERE post_content LIKE %s AND ID = %d", '%%<!-- wp:kadence/form%%', $post_id ) );
		} else {
			$forms = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM $wpdb->posts WHERE post_content LIKE %s", '%%<!-- wp:kadence/form%%' ) );
		}

		foreach ( $forms as $post ) {
			$content_array = explode( '<!--', $post->post_content );
			$content       = array();

			foreach ( $content_array as $key => $value ) {
				if ( str_contains( $value, ' wp:kadence/form' ) ) {
					$temp      = str_replace( ' wp:kadence/form', '', $value );
					$temp1     = explode( '-->', $temp, 2 );
					$content[] = json_decode( $temp1[0] );
				}
			}

			if ( is_array( $content ) ) {
				foreach ( $content as $form ) {
					$unique_id = $form->uniqueID; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Kadence block attribute name.

					if ( 'options' === $result_type ) {
						$form_options[] = array(
							'text'  => $post->post_title . ' - ' . $unique_id,
							'value' => $unique_id,
						);
					}

					if ( 'fields' === $result_type && $all_forms === $unique_id ) {
						foreach ( $form->fields as $field ) {
							$form_fields[] = array(
								'label' => $field->label,
								'type'  => $field->type,
							);
						}
					}
				}
			}
		}

		return ( 'fields' === $result_type ) ? $form_fields : $form_options;
	}

	/**
	 * Get the fields of a Kadence form. Advanced (CPT) forms read the stored
	 * `_kad_form_fields` meta; classic block forms are parsed from content.
	 *
	 * @param string|int $form_id Advanced-form post ID, or a classic form uniqueID.
	 *
	 * @return array|string List of { label, type } field arrays, or '' when none are stored.
	 */
	public function get_kadence_form_fields( $form_id ) {
		$fields = $this->get_forms_attributes_from_content( $form_id, 'fields' );

		if ( defined( 'KADENCE_BLOCKS_VERSION' ) && is_numeric( $form_id ) ) {
			$fields = maybe_unserialize( get_post_meta( $form_id, '_kad_form_fields', true ) );
		}

		return $fields;
	}

	/**
	 * Append a token definition for each field of the given form to the
	 * provided token list.
	 *
	 * @param string|int $form_id Advanced-form post ID, or a classic form uniqueID.
	 * @param array      $tokens  Existing token definitions to append to.
	 *
	 * @return array The token definitions including one per form field.
	 */
	public function get_kadence_form_tokens( $form_id, $tokens = array() ) {
		$fields = $this->get_kadence_form_fields( $form_id );

		foreach ( $fields as $field ) {
			$tokens[] = array(
				'tokenId'   => 'KADENCE_' . str_replace( ' ', '_', $field['label'] ),
				'tokenName' => $field['label'],
				'tokenType' => $field['type'],
			);
		}

		return $tokens;
	}

	/**
	 * Fetch all Kadence forms (classic blocks + advanced CPT) as select options.
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/kadence/forms`.
	 *
	 * @param Remote_Data_Request $request The remote-data request (unused; all
	 *                                     forms are returned regardless of input).
	 *
	 * @return array Success envelope: { success: true, options: [ { text, value } ] }.
	 */
	protected function remote_data_get_forms( $request ): array {
		return $this->remote_data_success( $this->get_all_kadence_form_options( true ) );
	}

	/**
	 * Fetch the selected form's fields as select options. Returns an empty list
	 * for the "Any form" (-1) sentinel, whose fields can't be enumerated.
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/kadence/form_fields`.
	 *
	 * @param Remote_Data_Request $request The remote-data request; reads the
	 *                                     parent KADENCE_FORMS field value.
	 *
	 * @return array Success envelope: { success: true, options: [ { text, value } ] }.
	 */
	protected function remote_data_get_form_fields( $request ): array {

		$form_id = $request->get_field_value( 'KADENCE_FORMS' );
		$options = array();

		if ( '' === $form_id || '-1' === $form_id ) {
			return $this->remote_data_success( $options );
		}

		$fields = $this->get_kadence_form_fields( $form_id );
		$fields = is_array( $fields ) ? $fields : array();

		foreach ( $fields as $field ) {
			if ( ! isset( $field['label'] ) ) {
				continue;
			}
			$options[] = array(
				'value' => str_replace( ' ', '_', strtolower( $field['label'] ) ),
				'text'  => $field['label'],
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Legacy AJAX endpoint (wp_ajax_get_all_form_fields) — outputs the selected
	 * form's fields as JSON and exits.
	 *
	 * @deprecated 7.4 — Superseded by the remote_data `form_fields` handler
	 *                 (remote_data_get_form_fields()); retained only for older
	 *                 Pro builds that still register and call this endpoint.
	 *
	 * @return void
	 */
	public function get_all_form_fields() {
		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();
		$options = array();
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) || empty( automator_filter_input( 'value', INPUT_POST ) ) ) {
			echo wp_json_encode( $options );
			die();
		}
		$form_id = automator_filter_input( 'value', INPUT_POST );

		$fields = $this->get_kadence_form_fields( $form_id );

		foreach ( $fields as $field ) {
			$options[] = array(
				'value' => str_replace( ' ', '_', strtolower( $field['label'] ) ),
				'text'  => $field['label'],
			);
		}

		echo wp_json_encode( $options );
		die();
	}
}
