<?php

namespace Uncanny_Automator;

/**
 * Class Advanced_Ads_Helpers
 *
 * @package Uncanny_Automator
 */
class Advanced_Ads_Helpers {
	/**
	 * helpers __construct
	 */
	public function __construct() {
	}

	/**
	 * @param $option_code
	 * @param bool $is_any
	 * @param bool $all_option
	 *
	 * @return array|mixed|void
	 */
	public function get_all_ads( $option_code, $is_any = false, $all_option = false, $label = null ) {
		$options = array();
		$args    = array(
			//phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_select_all_posts_limit', 999, 'post' ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'advanced_ads',
			'post_status'    => array( 'publish', 'draft' ),
		);
		if ( true === $all_option ) {
			$all_ads = Automator()->helpers->recipe->options->wp_query( $args, $all_option, __( 'All ads', 'uncanny-automator' ) );
		} else {
			$all_ads = Automator()->helpers->recipe->options->wp_query( $args, $is_any, __( 'Any ad', 'uncanny-automator' ) );
		}

		foreach ( $all_ads as $ad_id => $title ) {
			if ( empty( $title ) ) {
				$title = sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $ad_id );
			}

			$options[ $ad_id ] = $title;
		}

		if ( $label === null ) {
			$label = esc_attr__( 'Ad', 'uncanny-automator' );
		}

		$option = array(
			'input_type'            => 'select',
			'option_code'           => $option_code,
			/* translators: HTTP request method */
			'label'                 => $label,
			'required'              => true,
			'supports_custom_value' => true,
			'relevant_tokens'       => array(),
			'options'               => $options,
		);

		return apply_filters( 'uap_option_get_all_ads', $option );
	}

	/**
	 * @param $option_code
	 * @param $is_any
	 *
	 * @return array|mixed|void
	 */
	public function ad_statuses( $option_code, $is_any = false, $label = null, $tokens = array() ) {
		$expiry_key = defined( '\Advanced_Ads_Ad_Expiration::POST_STATUS' ) ? \Advanced_Ads_Ad_Expiration::POST_STATUS : 'advanced_ads_expired';

		$statuses = array(
			'draft'     => __( 'Draft', 'uncanny-automator' ),
			'pending'   => __( 'Pending Review', 'uncanny-automator' ),
			'publish'   => __( 'Publish', 'uncanny-automator' ),
			$expiry_key => __( 'Expired', 'uncanny-automator' ),
		);

		if ( true === $is_any ) {
			$statuses = array( '-1' => __( 'Any status', 'uncanny-automator' ) ) + $statuses;
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => ( empty( $label ) ) ? esc_attr__( 'Status', 'uncanny-automator' ) : $label,
			'input_type'      => 'select',
			'required'        => true,
			'options_show_id' => false,
			'relevant_tokens' => $tokens,
			'options'         => $statuses,
		);

		return apply_filters( 'uap_option_ad_statuses', $option );
	}

}
