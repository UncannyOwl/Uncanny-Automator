<?php

namespace Uncanny_Automator\Integrations\WpJobManager\Tokens;

use Uncanny_Automator\Integrations\Wpjm\Wpjm_Token_Manager;

/**
 * Class WPJM_Legacy_Tokens
 *
 * @package Uncanny_Automator\Integrations\WpJobManager\Tokens
 */
class WPJM_Legacy_Tokens {

	/**
	 * Token manager
	 *
	 * @var Wpjm_Token_Manager
	 */
	private $token_manager;

	/**
	 * Trigger records
	 *
	 * @var $trigger_records
	 */

	private $trigger_records;

	/**
	 * Constructor
	 *
	 * @param Wpjm_Token_Manager $token_manager
	 * @param $trigger_records
	 */
	public function __construct( $trigger_records ) {
		$this->token_manager   = new Wpjm_Token_Manager();
		$this->trigger_records = $trigger_records;
	}

	/**
	 * Save legacy tokens values
	 *
	 * @param string $token_type
	 * @param string $token_identifier
	 * @param int $wpjm_data_id
	 */
	public function save_legacy_tokens_values( $token_type, $token_identifier, $wpjm_data_id ) {
		$wpjm_tokens = array();

		if ( 'job' === $token_type ) {
			$wpjm_tokens = $this->token_manager->get_job_tokens( $token_identifier );
		}

		if ( 'application' === $token_type ) {
			$wpjm_tokens = $this->token_manager->get_application_tokens( $token_identifier );
		}

		$tokens = array();
		foreach ( $wpjm_tokens as $wpjm_token ) {
			$token_id = $wpjm_token['tokenId'] ?? null;
			if ( null !== $token_id ) {
				if ( 'job' === $token_type ) {
					$tokens[ $token_id ] = $this->token_manager->hydrate_job_tokens( $wpjm_data_id, $token_id );
				}

				if ( 'application' === $token_type ) {
					$tokens[ $token_id ] = $this->token_manager->hydrate_application_tokens( $wpjm_data_id, $token_id );
				}
			}
		}

		// Fill Legacy Tokens for backwards compatibility.
		if ( is_numeric( $wpjm_data_id ) ) {
			$tokens['WPJMJOBID']    = $wpjm_data_id;
			$tokens['WPJMJOBTITLE'] = wpjm_get_the_job_title( $wpjm_data_id );
			$tokens['WPJMJOBURL']   = get_permalink( $wpjm_data_id );
		}

		Automator()->db->token->save( $token_identifier, wp_json_encode( $tokens ), $this->trigger_records );
	}
}
