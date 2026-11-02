<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use OMAPI;

/**
 * Class Optinmonster_Helpers
 *
 * @package Uncanny_Automator
 */
class Optinmonster_Helpers {

	/**
	 * Set by setOptions().
	 *
	 * @var Optinmonster_Helpers|null
	 */
	public $options;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * @param Optinmonster_Helpers $options
	 */
	public function setOptions( Optinmonster_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- public API; a rename would break callers.
		$this->options = $options;
	}

	/**
	 * get_campaigns
	 *
	 * @return void
	 */
	public function get_campaigns() {

		$omapi = OMAPI::get_instance();

		$om_campaigns = $omapi->get_campaigns();

		$options = array();

		foreach ( $om_campaigns as $campaign ) {

			if ( 'inline' === $campaign->campaign_type ) {
				continue;
			}

			if ( false === $campaign->enabled ) {
				continue;
			}

			$options[] = array(
				'value' => $campaign->post_name,
				'text'  => $campaign->post_title,
			);
		}

		usort( $options, array( $this, 'sortByName' ) );

		return $options;
	}

	/**
	 * sortByName
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return void
	 */
	public function sortByName( $a, $b ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- public API; a rename would break callers.
		return strcmp( $a['text'], $b['text'] );
	}

	/**
	 * campaign_is_active
	 *
	 * @param string $campaign
	 *
	 * @return boolean
	 */
	public function campaign_is_active( $campaign_id ) {

		$omapi = OMAPI::get_instance();

		$om_campaigns = $omapi->get_campaigns();

		$options = array();

		foreach ( $om_campaigns as $campaign ) {

			if ( $campaign_id === $campaign->post_name ) {

				if ( false === $campaign->enabled ) {
					return false;
				}

				return true;
			}
		}

		return false;
	}
}
