<?php

namespace Uncanny_Automator\Integrations\Uncanny_Groups;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Uog_Helpers
 *
 * @package Uncanny_Automator
 */
class Uog_Helpers extends Abstract_Helpers {

	/**
	 * Uog_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Comparison operator options for numeric condition fields.
	 *
	 * @return array[] Modern select options format.
	 */
	public static function comparison_operators() {
		return array(
			array( 'value' => '<', 'text' => esc_html_x( 'less than', 'Uncanny Groups', 'uncanny-automator' ) ),
			array( 'value' => '>', 'text' => esc_html_x( 'greater than', 'Uncanny Groups', 'uncanny-automator' ) ),
			array( 'value' => '=', 'text' => esc_html_x( 'equal to', 'Uncanny Groups', 'uncanny-automator' ) ),
			array( 'value' => '!=', 'text' => esc_html_x( 'not equal to', 'Uncanny Groups', 'uncanny-automator' ) ),
			array( 'value' => '>=', 'text' => esc_html_x( 'greater or equal to', 'Uncanny Groups', 'uncanny-automator' ) ),
			array( 'value' => '<=', 'text' => esc_html_x( 'less or equal to', 'Uncanny Groups', 'uncanny-automator' ) ),
		);
	}

	/**
	 * Full NUMBERCOND field definition for numeric comparison triggers.
	 *
	 * @return array Field definition ready to include in options().
	 */
	public static function comparison_field() {
		return array(
			'option_code' => 'NUMBERCOND',
			'label'       => esc_html_x( 'Condition', 'Uncanny Groups', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => self::comparison_operators(),
		);
	}

	/**
	 * Get all LearnDash groups as dropdown options.
	 *
	 * @param bool $include_any Whether to include "Any group" option.
	 *
	 * @return array
	 */
	public function all_ld_groups_options( $include_any = true ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any group', 'Uncanny Groups', 'uncanny-automator' ),
			);
		}

		$args = array(
			'post_type'      => 'groups',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$groups = get_posts( $args );

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $group ) {
				$options[] = array(
					'value' => (string) $group->ID,
					'text'  => $group->post_title,
				);
			}
		}

		return $options;
	}

	/**
	 * Remote-data handler: load groups (with "Any group" option) for dropdown options.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups( $request ): array {
		return $this->remote_data_success( $this->all_ld_groups_options( true ) );
	}

	/**
	 * Remote-data handler: load groups (no "Any group" option) for action dropdown options.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups_strict( $request ): array {
		return $this->remote_data_success( $this->all_ld_groups_options( false ) );
	}

	/**
	 * Get all email addresses of group leaders for a given group.
	 *
	 * @param int $group_id The group ID.
	 *
	 * @return string Comma-separated email addresses.
	 */
	public function get_group_leaders_email_addresses( $group_id ) {

		$group_leaders = learndash_get_groups_administrators( $group_id );
		$emails        = array_column( $group_leaders, 'user_email' );

		if ( is_array( $emails ) && ! empty( $emails ) ) {
			return implode( ', ', $emails );
		}

		return '';
	}

	/**
	 * Validate an array of Group post IDs.
	 *
	 * @param array $group_ids Array of group post IDs to check.
	 *
	 * @return array Validated group post IDs.
	 */
	public function learndash_validate_groups( $group_ids = array() ) {

		if ( ! is_array( $group_ids ) || empty( $group_ids ) ) {
			return array();
		}

		$groups_query_args = array(
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_type'              => learndash_get_post_type_slug( 'group' ),
			'fields'                 => 'ids',
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'post__in'               => $group_ids,
			'posts_per_page'         => -1,
			'suppress_filters'       => true,
		);

		$groups_query_args = apply_filters( 'uap_option_learndash_validate_groups', $groups_query_args );

		$groups_query = new \WP_Query( $groups_query_args );

		if ( is_a( $groups_query, '\WP_Query' ) && property_exists( $groups_query, 'posts' ) ) {
			return $groups_query->posts;
		}

		return array();
	}

	/**
	 * Get the human-readable label for a number condition value.
	 *
	 * @param string $value The condition value (e.g., '>=').
	 *
	 * @return string The condition label.
	 */
	public function get_number_conditions_values( $value = '' ) {

		if ( '' === $value ) {
			return '';
		}

		foreach ( self::comparison_operators() as $option ) {
			if ( (string) $option['value'] === (string) $value ) {
				return $option['text'];
			}
		}

		return '';
	}
}
