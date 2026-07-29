<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Pretty_Links_Helpers
 *
 * @package Uncanny_Automator
 */
class Pretty_Links_Helpers extends Abstract_Helpers {

	/**
	 * Get the redirection type dropdown options.
	 *
	 * @param bool $is_any Whether to prepend the "Any redirection type" option.
	 *
	 * @return array[]
	 */
	public function get_all_redirection_types( $is_any = false ) {

		$redirection_types = array();

		if ( true === $is_any ) {
			$redirection_types[] = array(
				'text'  => esc_attr_x( 'Any redirection type', 'Pretty Links', 'uncanny-automator' ),
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
	 * Remote data handler: pretty link dropdown options, with the
	 * "Any pretty link" option prepended.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_links( $request ): array {

		$options = array(
			array(
				'text'  => esc_attr_x( 'Any pretty link', 'Pretty Links', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		foreach ( $this->get_links() as $link ) {
			$options[] = array(
				'text'  => esc_attr( $link['name'] ),
				'value' => $link['id'],
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote data handler: redirection type dropdown options for actions.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_redirection_types( $request ): array {
		return $this->remote_data_success( $this->get_all_redirection_types() );
	}

	/**
	 * Remote data handler: redirection type dropdown options for triggers,
	 * with the "Any redirection type" option prepended.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_redirection_types_for_triggers( $request ): array {
		return $this->remote_data_success( $this->get_all_redirection_types( true ) );
	}

	/**
	 * Fetch every link through whichever API the installed Pretty Links version provides.
	 *
	 * Pretty Links 4.0 removed the v3 models in favour of the namespaced Links
	 * repository; the v3 API functions survive only as deprecated shims.
	 *
	 * @return array[] Link rows, each with at least 'id' and 'name' keys.
	 */
	private function get_links() {

		// Pretty Links 4.0+.
		if ( class_exists( '\PrettyLinks\Repositories\Links' ) ) {
			$result = ( new \PrettyLinks\Repositories\Links() )->search(
				array(
					'per_page' => 10000,
					'orderby'  => 'name',
					'order'    => 'asc',
				)
			);

			return isset( $result['items'] ) ? $result['items'] : array();
		}

		// Pretty Links 3.x public API function (also a deprecated shim in 4.x).
		if ( function_exists( 'prli_get_all_links' ) ) {
			return prli_get_all_links();
		}

		return array();
	}

	/**
	 * Get the common token definitions for a created pretty link.
	 *
	 * @return array[]
	 */
	public function prli_common_tokens_for_link_created() {

		return array(
			array(
				'tokenId'   => 'LINK_TITLE',
				'tokenName' => esc_html_x( 'Link title', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LINK_ID',
				'tokenName' => esc_html_x( 'Link ID', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'LINK_REDIRECTION_TYPE',
				'tokenName' => esc_html_x( 'Redirection type', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRETTY_LINK',
				'tokenName' => esc_html_x( 'Pretty Link', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'LINK_TARGET_URL',
				'tokenName' => esc_html_x( 'Target URL', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Get the common token definitions for a clicked pretty link.
	 *
	 * @return array[]
	 */
	public function prli_common_tokens_for_link_clicked() {

		return array(
			array(
				'tokenId'   => 'LINK_ID',
				'tokenName' => esc_html_x( 'Link ID', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CLICK_ID',
				'tokenName' => esc_html_x( 'Click ID', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TARGET_URL',
				'tokenName' => esc_html_x( 'Target URL', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'PRLI_REFERER',
				'tokenName' => esc_html_x( 'Referer', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'PRLI_HOST',
				'tokenName' => esc_html_x( 'Host', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'PRLI_BROWSER',
				'tokenName' => esc_html_x( 'Browser', 'Pretty Links', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Hydrate the common created-link token values from the hook arguments.
	 *
	 * @param array $hook_args The prli-create-link hook arguments: link ID and link row.
	 *
	 * @return array The hydrated token values keyed by token ID.
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
	 * Hydrate the clicked-link token values from the hook arguments.
	 *
	 * @param array $hook_args The prli_record_click hook arguments.
	 *
	 * @return array The hydrated token values keyed by token ID.
	 */
	public function hydrate_prli_link_clicked_tokens( $hook_args ) {
		$prli_id       = $hook_args[0]['link_id'];
		$prli_click_id = $hook_args[0]['click_id'];
		$prli_url      = $hook_args[0]['url'];
		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->prli_common_tokens_for_link_clicked(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$click = $this->get_click( (int) $prli_click_id, (int) $prli_id );

		$tokens['LINK_ID']      = $prli_id;
		$tokens['CLICK_ID']     = $prli_click_id;
		$tokens['TARGET_URL']   = $prli_url;
		$tokens['PRLI_REFERER'] = $click['referer'];
		$tokens['PRLI_HOST']    = $click['host'];
		$tokens['PRLI_BROWSER'] = $click['browser'];

		return $tokens;
	}

	/**
	 * Fetch a click's details through whichever API the installed Pretty Links version provides.
	 *
	 * Pretty Links 4.0 exposes clicks through the Clicks repository — it has no
	 * single-click accessor, so the link's recent clicks are searched (newest
	 * first) and matched by ID. 3.x exposes the PrliClick model.
	 *
	 * The visitor host is resolved from the recorded IP when the stored value is
	 * empty: 4.0's default (normal) tracking mode skips the reverse-DNS lookup
	 * that populated the column in earlier versions.
	 *
	 * @param int $click_id The click ID from the prli_record_click hook.
	 * @param int $link_id  The clicked link ID.
	 *
	 * @return array Click details with 'referer', 'host', 'browser', and 'ip' keys ('' when unavailable).
	 */
	private function get_click( $click_id, $link_id ) {

		$click = array(
			'referer' => '',
			'host'    => '',
			'browser' => '',
			'ip'      => '',
		);

		// Pretty Links 4.0+.
		if ( class_exists( '\PrettyLinks\Repositories\Clicks' ) ) {
			$result = ( new \PrettyLinks\Repositories\Clicks() )->search( array( 'link_id' => $link_id ) );

			foreach ( $result['items'] as $item ) {
				if ( $click_id === $item['id'] ) {
					$click['referer'] = $item['referer'];
					$click['host']    = $item['host'];
					$click['browser'] = $item['browser'];
					$click['ip']      = $item['ip'];
					break;
				}
			}
		} elseif ( class_exists( '\PrliClick' ) ) {
			// Pretty Links 3.x model.
			$row = ( new \PrliClick() )->getOne( $click_id );

			if ( is_object( $row ) ) {
				$click['referer'] = isset( $row->referer ) ? (string) $row->referer : '';
				$click['host']    = isset( $row->host ) ? (string) $row->host : '';
				$click['browser'] = isset( $row->browser ) ? (string) $row->browser : '';
				$click['ip']      = isset( $row->ip ) ? (string) $row->ip : '';
			}
		}

		if ( '' === $click['host'] && '' !== $click['ip'] ) {
			$click['host'] = (string) gethostbyaddr( $click['ip'] );
		}

		return $click;
	}
}
