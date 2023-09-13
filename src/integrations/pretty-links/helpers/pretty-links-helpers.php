<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class Pretty_Links_Helpers
 *
 * @package Uncanny_Automator
 */
class Pretty_Links_Helpers {

	/**
	 * @param bool $is_any
	 * @param bool $is_all
	 *
	 * @return array|array[]
	 */
	public function get_all_redirection_types( $is_any = false, $is_all = false ) {

		$redirection_types = array();

		if ( true === $is_any ) {
			$redirection_types[] = array(
				'text'  => esc_attr_x( 'Any redirection type', 'Pretty Links', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( true === $is_all ) {
			$redirection_types[] = array(
				'text'  => esc_attr_x( 'All redirection types', 'Pretty Links', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$redirection_types[] = array(
			'text'  => esc_attr_x( '301 (Permanent)', 'Pretty Links', 'uncanny-automator' ),
			'value' => 301,
		);
		$redirection_types[] = array(
			'text'  => esc_attr_x( '302 (Temporary)', 'Pretty Links', 'uncanny-automator' ),
			'value' => 302,
		);
		$redirection_types[] = array(
			'text'  => esc_attr_x( '307 (Temporary)', 'Pretty Links', 'uncanny-automator' ),
			'value' => 307,
		);

		return $redirection_types;
	}

	/**
	 * @param $is_any
	 * @param $is_all
	 *
	 * @return array
	 */
	public function get_all_pretty_links( $is_any = false, $is_all = false ) {

		$links = prli_get_all_links();

		automator_log( $links, 'pretty links', true, 'prli' );

		$all_links = array();

		if ( true === $is_any ) {
			$all_links[] = array(
				'text'  => esc_attr_x( 'Any pretty link', 'Pretty Links', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( true === $is_all ) {
			$all_links[] = array(
				'text'  => esc_attr_x( 'All pretty links', 'Pretty Links', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		foreach ( $links as $link ) {
			$all_links[] = array(
				'text'  => esc_attr_x( $link['name'], 'Pretty Links', 'uncanny-automator' ),
				'value' => $link['id'],
			);
		}

		return $all_links;
	}

	/**
	 * Get common tokens for a pretty link
	 *
	 * @return array[]
	 */
	public function prli_common_tokens_for_link_created() {

		return array(
			array(
				'tokenId'   => 'LINK_TITLE',
				'tokenName' => __( 'Link title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LINK_ID',
				'tokenName' => __( 'Link ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'LINK_REDIRECTION_TYPE',
				'tokenName' => __( 'Redirection type', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRETTY_LINK',
				'tokenName' => __( 'Pretty Link', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'LINK_TARGET_URL',
				'tokenName' => __( 'Target URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);

	}

	/**
	 * Get common tokens for a pretty link clicked
	 *
	 * @return array[]
	 */
	public function prli_common_tokens_for_link_clicked() {

		return array(
			array(
				'tokenId'   => 'LINK_ID',
				'tokenName' => __( 'Link ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CLICK_ID',
				'tokenName' => __( 'Click ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TARGET_URL',
				'tokenName' => __( 'Target URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'PRLI_REFERER',
				'tokenName' => __( 'Referer', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'PRLI_HOST',
				'tokenName' => __( 'Host', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'PRLI_BROWSER',
				'tokenName' => __( 'Browser', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);

	}

	/**
	 * Parse pretty link common token values
	 *
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_prli_common_tokens( $hook_args ) {
		$prli_id   = $hook_args[0];
		$prli_data = $hook_args[1];
		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->prli_common_tokens_for_link_created(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$tokens['LINK_ID']               = $prli_id;
		$tokens['LINK_TITLE']            = $prli_data['name'];
		$tokens['LINK_REDIRECTION_TYPE'] = $prli_data['redirect_type'];
		$tokens['PRETTY_LINK']           = prli_get_pretty_link_url( $prli_id );
		$tokens['LINK_TARGET_URL']       = $prli_data['url'];

		return $tokens;
	}

	/**
	 * Parse link clicked token values
	 *
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_prli_link_clicked_tokens( $hook_args ) {
		$prli_id       = $hook_args[0]['link_id'];
		$prli_click_id = $hook_args[0]['click_id'];
		$prli_url      = $hook_args[0]['url'];
		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->prli_common_tokens_for_link_clicked(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		global $wpdb;
		$click_data = $wpdb->get_row( $wpdb->prepare( "SELECT host,referer,browser FROM {$wpdb->prefix}prli_clicks WHERE id =%d", $prli_click_id ), ARRAY_A );

		$tokens['LINK_ID']      = $prli_id;
		$tokens['CLICK_ID']     = $prli_click_id;
		$tokens['TARGET_URL']   = $prli_url;
		$tokens['PRLI_REFERER'] = $click_data['referer'];
		$tokens['PRLI_HOST']    = $click_data['host'];
		$tokens['PRLI_BROWSER'] = $click_data['browser'];

		return $tokens;
	}

}
