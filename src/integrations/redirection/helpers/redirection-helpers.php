<?php

namespace Uncanny_Automator\Integrations\Redirection;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Redirection_Helpers
 *
 * @package Uncanny_Automator
 */
class Redirection_Helpers extends Abstract_Helpers {

	/**
	 * Redirect group select options.
	 *
	 * @param bool $include_any Prepend the "Any group" sentinel — triggers pass
	 *                          true, actions pass false.
	 *
	 * @return array
	 */
	public function get_group_options( $include_any = true ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any group', 'Redirection', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		foreach ( $this->get_groups() as $group ) {
			$options[] = array(
				'text'  => esc_html( $group['name'] ),
				'value' => (int) $group['id'],
			);
		}

		return $options;
	}

	/**
	 * Fetch all Redirection groups as id/name pairs.
	 *
	 * @return array<int,array{id:int,name:string}>
	 */
	public function get_groups() {

		if ( ! class_exists( '\Red_Group' ) ) {
			return array();
		}

		$groups = \Red_Group::get_all();

		return is_array( $groups ) ? $groups : array();
	}

	/**
	 * Resolve a group's name from its ID.
	 *
	 * @param int $group_id Group ID.
	 *
	 * @return string
	 */
	public function get_group_name( $group_id ) {

		$group_id = (int) $group_id;
		if ( empty( $group_id ) ) {
			return '';
		}

		foreach ( $this->get_groups() as $group ) {
			if ( (int) $group['id'] === $group_id ) {
				return (string) $group['name'];
			}
		}

		return '';
	}

	/**
	 * Token configuration shared by the redirect-based triggers (matched,
	 * deleted, monitor). Each trigger declares the subset it exposes.
	 *
	 * @return array
	 */
	public function get_redirect_tokens_config() {
		return array(
			'REDIRECT_ID'          => array(
				'tokenId'   => 'REDIRECT_ID',
				'tokenName' => esc_html_x( 'Redirect ID', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			'REDIRECT_SOURCE_URL'  => array(
				'tokenId'   => 'REDIRECT_SOURCE_URL',
				'tokenName' => esc_html_x( 'Source URL', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'REDIRECT_TARGET_URL'  => array(
				'tokenId'   => 'REDIRECT_TARGET_URL',
				// Redirection supports non-URL action types (pass, error, random,
				// nothing) where get_action_data() is not a URL, so the label is
				// kept generic. Token type stays 'text' rather than 'url'.
				'tokenName' => esc_html_x( 'Target URL / action data', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'REDIRECT_GROUP'       => array(
				'tokenId'   => 'REDIRECT_GROUP',
				'tokenName' => esc_html_x( 'Group name', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'REDIRECT_GROUP_ID'    => array(
				'tokenId'   => 'REDIRECT_GROUP_ID',
				'tokenName' => esc_html_x( 'Group ID', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			'REDIRECT_MATCH_TYPE'  => array(
				'tokenId'   => 'REDIRECT_MATCH_TYPE',
				'tokenName' => esc_html_x( 'Match type', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'REDIRECT_ACTION_TYPE' => array(
				'tokenId'   => 'REDIRECT_ACTION_TYPE',
				'tokenName' => esc_html_x( 'Action type', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'REDIRECT_HTTP_CODE'   => array(
				'tokenId'   => 'REDIRECT_HTTP_CODE',
				'tokenName' => esc_html_x( 'HTTP code', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			'REDIRECT_HITS'        => array(
				'tokenId'   => 'REDIRECT_HITS',
				'tokenName' => esc_html_x( 'Hit count', 'Redirection', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate the full redirect token keyset from a Red_Item object.
	 *
	 * Returns every key declared in get_redirect_tokens_config(); each trigger
	 * intersects this with the subset it exposes.
	 *
	 * @param mixed $item Red_Item instance (or anything else — guarded).
	 *
	 * @return array
	 */
	public function hydrate_redirect_item( $item ) {

		$tokens = array_fill_keys( array_keys( $this->get_redirect_tokens_config() ), '' );

		if ( ! is_a( $item, '\Red_Item' ) ) {
			return $tokens;
		}

		$group_id = (int) $item->get_group_id();

		$tokens['REDIRECT_ID']          = (int) $item->get_id();
		$tokens['REDIRECT_SOURCE_URL']  = (string) $item->get_url();
		$tokens['REDIRECT_TARGET_URL']  = (string) $item->get_action_data();
		$tokens['REDIRECT_GROUP']       = $this->get_group_name( $group_id );
		$tokens['REDIRECT_GROUP_ID']    = $group_id;
		$tokens['REDIRECT_MATCH_TYPE']  = (string) $item->get_match_type();
		$tokens['REDIRECT_ACTION_TYPE'] = (string) $item->get_action_type();
		$tokens['REDIRECT_HTTP_CODE']   = (int) $item->get_action_code();
		$tokens['REDIRECT_HITS']        = (int) $item->get_hits();

		return $tokens;
	}

	// ============================================================
	// Remote_Data segments.
	// ============================================================

	/**
	 * Remote_Data segment: groups with the "Any group" sentinel. For triggers.
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups( $request ): array {
		return $this->remote_data_success( $this->get_group_options( true ) );
	}

	/**
	 * Remote_Data segment: groups without the "Any" sentinel. For actions.
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups_strict( $request ): array {
		return $this->remote_data_success( $this->get_group_options( false ) );
	}

	/**
	 * Remote_Data segment: search existing redirects by source URL (no "Any"
	 * sentinel). Consumed by the Pro actions that act on one existing redirect
	 * (enable / disable / delete).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_redirects_strict( $request ): array {
		$query = is_object( $request ) && method_exists( $request, 'get_search_query' )
			? $request->get_search_query()
			: '';

		return $this->remote_data_success( $this->get_redirect_options( $query ) );
	}

	/**
	 * Build redirect option pairs filtered by a source-URL search query.
	 *
	 * Value = redirect ID; text = the source URL (prefixed with the title when
	 * one is set). Capped at 100 results to keep the payload light on sites with
	 * many redirects.
	 *
	 * @param string $query The user's typed search term.
	 *
	 * @return array
	 */
	public function get_redirect_options( $query = '' ) {

		if ( ! class_exists( '\Red_Item' ) ) {
			return array();
		}

		$query  = trim( (string) $query );
		$params = array( 'per_page' => 100 );

		if ( '' !== $query ) {
			$params['filterBy'] = array( 'url' => $query );
		}

		$result = \Red_Item::get_filtered( $params );
		$items  = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();

		$options = array();

		foreach ( $items as $item ) {

			$id = isset( $item['id'] ) ? (int) $item['id'] : 0;

			if ( empty( $id ) ) {
				continue;
			}

			$url   = isset( $item['url'] ) ? (string) $item['url'] : '';
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';

			if ( '' !== $title && '' !== $url ) {
				$text = sprintf( '%1$s (%2$s)', $title, $url );
			} elseif ( '' !== $url ) {
				$text = $url;
			} else {
				$text = sprintf( '#%d', $id );
			}

			$options[] = array(
				'value' => $id,
				'text'  => $text,
			);
		}

		return $options;
	}
}
