<?php

class AutomatorTestCase extends \Codeception\TestCase\WPTestCase {

	/**
	  * Mock the next HTTP request response
	  *
	  * @param bool   $false     False.
	  * @param array  $arguments Request arguments.
	  * @param string $url       Request URL.
	  *
	  * @return array|bool
	  */
	public function fake_next_http_response( $response ) {

		add_filter(
			'pre_http_request',
			function() use ( $response ) {
				return $response;
			},
			10,
			3
		);

	}

	/**
	  * Mirror the next HTTP request response
	  *
	  * @return array|bool
	  */
	public function mirror_next_http_response() {

		add_filter(
			'pre_http_request',
			function( $preempt, $parsed_args, $url ) {
				return $parsed_args;
			},
			10,
			3
		);

	}

	public function random_string( $length = 10 ) {
		$characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen( $characters );
		$randomString     = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$randomString .= $characters[ random_int( 0, $charactersLength - 1 ) ];
		}
		return $randomString;
	}
}
