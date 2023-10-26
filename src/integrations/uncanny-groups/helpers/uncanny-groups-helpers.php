<?php

namespace Uncanny_Automator;

/**
 * Class Uncanny_Groups_Helpers
 *
 * @package Uncanny_Automator
 */
class Uncanny_Groups_Helpers {

	/**
	 * @var Uncanny_Groups_Helpers
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Uncanny_Groups_Helpers constructor.
	 */
	public function __construct( $load_action_hook = true ) {

		if ( true === $load_action_hook ) {
			add_filter( 'uap_option_all_ld_groups', array( $this, 'uog_filter_groups_list' ) );
		}
	}

	/**
	 * @param Uncanny_Groups_Helpers $options
	 */
	public function setOptions( Uncanny_Groups_Helpers $options ) {
		$this->options = $options;
	}


	/**
	 * Filter for group list
	 *
	 * @param $option
	 *
	 * @return array
	 */
	public function uog_filter_groups_list( $option = array() ) {
		if ( is_array( $option ) && isset( $option['option_code'] ) && ( 'UOG_SEATSADDEDTOGROUP_META' === $option['option_code'] || 'UOG_SEATSREMOVEDFROMGROUP_META' === $option['option_code'] ) ) {
			$option['relevant_tokens'] = array();
		}
		return $option;
	}
	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_ld_groups( $label = null, $option_code = 'UOGROUP', $any_option = true ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Group', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'groups',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any group', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => array(
				$option_code                   => esc_attr__( 'Group title', 'uncanny-automator' ),
				$option_code . '_ID'           => esc_attr__( 'Group ID', 'uncanny-automator' ),
				$option_code . '_URL'          => esc_attr__( 'Group URL', 'uncanny-automator' ),
				$option_code . '_KEY'          => esc_attr__( 'Key redeemed', 'uncanny-automator' ),
				$option_code . '_KEY_BATCH_ID' => esc_attr__( 'Key batch ID', 'uncanny-automator' ),
			),
			'custom_value_description' => _x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_ld_groups', $option );
	}


	/**
	 * Getting all email addresses of group leaders
	 *
	 * @param $group_id
	 *
	 * @return mixed|void
	 */
	public function get_group_leaders_email_addresses( $group_id ) {

		$group_leaders = learndash_get_groups_administrators( $group_id );

		return ( is_array( array_column( $group_leaders, 'user_email' ) ) ) ? implode( ', ', array_column( $group_leaders, 'user_email' ) ) : array();

	}

	public function get_number_conditions_values( $key = '' ) {
		if ( empty( $key ) ) {
			return '';
		}

		return Automator()->helpers->recipe->field->less_or_greater_than()['options'][ $key ];
	}

	/**
	 * Validate an array of Group post IDs.
	 *
	 * @param array $group_ids Array of Groups post IDs to check.
	 * @return array validated Group post IDS.
	 */
	public function learndash_validate_groups( $group_ids = array() ) {
		if ( ( is_array( $group_ids ) ) && ( ! empty( $group_ids ) ) ) {
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
			if ( ( is_a( $groups_query, '\WP_Query' ) ) && ( property_exists( $groups_query, 'posts' ) ) ) {
				return $groups_query->posts;
			}
		}

		return array();
	}

}
