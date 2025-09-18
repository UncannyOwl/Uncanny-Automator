<?php

namespace Uncanny_Automator\Integrations\Bitly;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class BITLY_SHORTEN_URL
 *
 * @package Uncanny_Automator
 *
 * @property Bitly_Api_Caller $api
 */
class BITLY_SHORTEN_URL extends \Uncanny_Automator\Recipe\App_Action {

	use Log_Properties;

	/**
	 * @var string
	 */
	public $prefix = 'BITLY_SHORTEN_URL';

	/**
	 * Spins up new action inside "Bitly" integration.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'BITLY' );
		$this->set_action_code( 'BITLY_SHORTEN_URL_CODE' );
		$this->set_action_meta( 'BITLY_SHORTEN_URL_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/bitly/' ) );
		$this->set_requires_user( false );
		// translators: 1: Long URL
		$this->set_sentence( sprintf( esc_attr_x( 'Shorten {{a URL:%1$s}}', 'Bitly', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Shorten {{a URL}}', 'Bitly', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_attr_x( 'Long URL', 'Bitly', 'uncanny-automator' ),
				'input_type'      => 'url',
				'required'        => true,
				'placeholder'     => 'https://www.example.com/my-page',
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'BITLY_DOMAIN',
				'label'           => esc_attr_x( 'Domain', 'Bitly', 'uncanny-automator' ),
				'description'     => esc_html_x( 'Enter custom domain for your Bitly account (only available for Paid accounts).', 'Bitly', 'uncanny-automator' ),
				'input_type'      => 'url',
				'required'        => false,
				'placeholder'     => 'example.com',
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function define_tokens() {
		return array(
			'DOMAIN'    => array(
				'name' => esc_html_x( 'Domain', 'Bitly', 'uncanny-automator' ),
				'type' => 'text',
			),
			'LONG_URL'  => array(
				'name' => esc_html_x( 'Long URL', 'Bitly', 'uncanny-automator' ),
				'type' => 'url',
			),
			'SHORT_URL' => array(
				'name' => esc_html_x( 'Short URL', 'Bitly', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param       $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$long_url = sanitize_url( $parsed[ $this->get_action_meta() ] );
		$domain   = sanitize_text_field( $parsed['BITLY_DOMAIN'] );

		// Set request body.
		$body = array(
			'action'   => 'shorten_url',
			'long_url' => $long_url,
			'domain'   => $domain,
		);

		$response = $this->api->api_request( $body, $action_data );

		if ( false === $response ) {
			$this->add_log_error( sprintf( 'Error: Unable to shorten the given URL: "%s"', $long_url ) );

			return false;
		}

		// Populate the custom token values
		$this->hydrate_tokens(
			array(
				'DOMAIN'    => $domain,
				'LONG_URL'  => $long_url,
				'SHORT_URL' => $response['data']['short_url'],
			)
		);

		// Set log properties.
		$this->set_log_properties(
			array(
				'type'  => 'url',
				'label' => esc_html_x( 'Shorten URL', 'Bitly', 'uncanny-automator' ),
				'value' => $response['data']['short_url'],
			)
		);

		return true;
	}
}
