<?php
use Uncanny_Automator\Webhooks;

 /**
  * @group test-response-validator
  */
final class Response_Validator_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * 1.0 Generic parameter test
	 */
	public function test_validate_webhook_response_show_throw_exception_if_response_is_invalid() {

		$this->expectException( Exception::class );

		$response  = 'invalid';
		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

	}

	/**
	 * 2.0 Should throw an exception if param#1 is an instance of WP_Error.
	 */
	public function test_validate_webhook_response_should_throw_exception_on_wp_error() {

		$this->expectException( Exception::class );

		$validator = Webhooks\Response_Validator::validate_webhook_response( new WP_Error( 'Err message', 400 ) );

	}

	/**
	 * 3.0 Should return array with null error message if the status is 200 to 299.
	 */
	public function test_validate_webhook_response_should_NOT_throw_exception_on_status_code_200_to_299() {

		// Generate a random status code ranging from 400 - 499.
		$range = range( 200, 299 );
		$index = array_rand( $range, 1 );

		$response = array(
			'body'     => '{"sample":"input"}',
			'response' => array(
				'code' => $range[ $index ],
			),
		);

		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

		// The error message should be null.
		$this->assertNull( $validator['error_message'] );

	}

	/**
	 * 4.0 Should return array with WITH error message if the status is 200 to 299 and there is an "error" object.
	 */
	public function test_validate_webhook_response_should_throw_exception_on_status_code_200_to_299_but_fail_if_there_is_error_prop() {

		$this->expectException( Exception::class );

		// Generate a random status code ranging from 400 - 499.
		$range = range( 200, 299 );
		$index = array_rand( $range, 1 );

		$response = array(
			'body'     => '{"error":"1"}',
			'response' => array(
				'code' => $range[ $index ],
			),
		);

		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

		// The error message should be null.
		$this->assertNull( $validator['error_message'] );
		$this->assertEquals( $range[ $index ], $validator['response_code'] );

	}

	/**
	 * 5.0 Should NOT throw an exception for status 300 to 399. Validator's error message and response_code should also be set.
	 */
	public function test_validate_webhook_response_should_handle_300_to_399() {

		// Generate a random status code ranging from 400 - 499.
		$range = range( 300, 399 );
		$index = array_rand( $range, 1 );

		$response = array(
			'response' => array(
				'code' => $range[ $index ],
			),
		);

		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

		// The redirect should have an error message.
		$this->assertNotEmpty( $validator['error_message'] );
		$this->assertEquals( $range[ $index ], $validator['response_code'] );

	}

	/**
	 * 6.0 Should throw an exception for status 400 to 499.
	 */
	public function test_validate_webhook_response_should_throw_exception_on_status_code_400_to_499() {

		$this->expectException( Exception::class );

		// Generate a random status code ranging from 400 - 499.
		$range = range( 400, 499 );
		$index = array_rand( $range, 1 );

		$response = array(
			'response' => array(
				'code' => $range[ $index ],
			),
		);

		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

	}

	/**
	 * 7.0 Should throw an exception for status 500 to 599.
	 */
	public function test_validate_webhook_response_should_throw_exception_on_status_code_500_to_599() {

		$this->expectException( Exception::class );

		// Generate a random status code ranging from 400 - 499.
		$range = range( 500, 599 );
		$index = array_rand( $range, 1 );

		$response = array(
			'response' => array(
				'code' => $range[ $index ],
			),
		);

		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

	}

	/**
	 * 8.0 Checking the correctness of the error message base on the status code.
	 */
	public function test_validate_webhook_response_should_send_correct_error_message() {

		// -- Part 1: 500 error -- //
		$response = array(
			'body'     => '{error:1}',
			'response' => array(
				'code' => 500,
			),
		);

		$expected = 'Server error, request responded with: 500 &mdash; Internal Server Error error.';

		try {
			$validator = Webhooks\Response_Validator::validate_webhook_response( $response );
		} catch ( Exception $e ) {
			$this->assertSame( $expected, $e->getMessage() );
		}

		// -- Part 2: 404 error -- //
		$response = array(
			'body'     => '{error:1}',
			'response' => array(
				'code' => 404,
			),
		);

		$expected = 'Client error, request responded with: 404 &mdash; Not Found error.';

		try {
			$validator = Webhooks\Response_Validator::validate_webhook_response( $response );
		} catch ( Exception $e ) {
			$this->assertSame( $expected, $e->getMessage() );
		}

		// -- Part 3: 302 redirect -- //
		$response = array(
			'body'     => '{"error":1}',
			'response' => array(
				'code' => 302,
			),
		);

		$expected  = 'Request redirected to another URL.';
		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

		$this->assertRegexp( '/' . $expected . '/', $validator['error_message'] );

		// -- Part 4: 302 redirect with error -- //
		$response = array(
			'body'     => '{"data":null}',
			'response' => array(
				'code' => 302,
			),
		);

		$expected  = 'Request redirected to another URL.';
		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

		$this->assertSame( $expected, $validator['error_message'] );
	}

	/**
	* 9.0 Should throw an exception for invalid status.
	*/
	public function test_validate_webhook_response_should_throw_exception_on_invalid_status() {

		$this->expectException( Exception::class );

		$response = array(
			'response' => array(
				'code' => null,
			),
		);

		$validator = Webhooks\Response_Validator::validate_webhook_response( $response );

	}

}
