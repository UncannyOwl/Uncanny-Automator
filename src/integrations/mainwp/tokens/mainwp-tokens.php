<?php

namespace Uncanny_Automator\Integrations\Mainwp;

/**
 * Class Mainwp_Tokens
 *
 * Define/hydrate pairs for the child-site token groups the MainWP triggers share.
 * Each trigger merges exactly the groups its scope lists.
 *
 * @package Uncanny_Automator
 */
class Mainwp_Tokens {

	/**
	 * Child site ID, name and URL.
	 *
	 * @return array[]
	 */
	public static function site_tokens() {
		return array(
			array(
				'tokenId'   => 'SITE_ID',
				'tokenName' => esc_html_x( 'Child site ID', 'MainWP', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'SITE_NAME',
				'tokenName' => esc_html_x( 'Child site name', 'MainWP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SITE_URL',
				'tokenName' => esc_html_x( 'Child site URL', 'MainWP', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * @param object|null $site A MainWP site row.
	 *
	 * @return array
	 */
	public static function hydrate_site_tokens( $site ) {

		$site = is_object( $site ) ? $site : null;

		return array(
			'SITE_ID'   => null !== $site ? absint( $site->id ?? 0 ) : 0,
			'SITE_NAME' => (string) ( $site->name ?? '' ),
			'SITE_URL'  => (string) ( $site->url ?? '' ),
		);
	}

	/**
	 * The child site's admin username.
	 *
	 * @return array[]
	 */
	public static function site_admin_tokens() {
		return array(
			array(
				'tokenId'   => 'SITE_ADMIN',
				'tokenName' => esc_html_x( 'Child site admin username', 'MainWP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param object|null $site A MainWP site row.
	 *
	 * @return array
	 */
	public static function hydrate_site_admin_tokens( $site ) {
		return array(
			'SITE_ADMIN' => is_object( $site ) ? (string) ( $site->adminname ?? '' ) : '',
		);
	}

	/**
	 * The child site's tags (MainWP's code calls them groups).
	 *
	 * @return array[]
	 */
	public static function site_tags_tokens() {
		return array(
			array(
				'tokenId'   => 'SITE_GROUPS',
				'tokenName' => esc_html_x( 'Child site tags', 'MainWP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param object|null $site A MainWP site row read with its tags (`wpgroups`).
	 *
	 * @return array
	 */
	public static function hydrate_site_tags_tokens( $site ) {

		$tags = is_object( $site ) ? explode( ',', (string) ( $site->wpgroups ?? '' ) ) : array();

		return array(
			'SITE_GROUPS' => implode( ', ', array_filter( array_map( array( __CLASS__, 'tag_name' ), $tags ) ) ),
		);
	}

	/**
	 * The client the child site belongs to.
	 *
	 * @return array[]
	 */
	public static function site_client_tokens() {
		return array(
			array(
				'tokenId'   => 'SITE_CLIENT',
				'tokenName' => esc_html_x( 'Child site client', 'MainWP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param object|null $client A MainWP client row.
	 *
	 * @return array
	 */
	public static function hydrate_site_client_tokens( $client ) {
		return array(
			'SITE_CLIENT' => is_object( $client ) ? (string) ( $client->name ?? '' ) : '',
		);
	}

	/**
	 * A tag name as typed: MainWP stores tag names HTML-escaped.
	 *
	 * @param mixed $name The stored name.
	 *
	 * @return string
	 */
	public static function tag_name( $name ) {
		return trim( wp_specialchars_decode( (string) $name, ENT_QUOTES ) );
	}
}
