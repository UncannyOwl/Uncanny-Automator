<?php

use Uncanny_Automator\Api_Server;

/**
 * @group ignore
 */
class Api_Server_Test extends AutomatorTestCase {

	/**
	 * @var \WpunitTester
	 */
	protected $tester;

	/**
	 * @var
	 */
	private $api_server;

	/**
	 * @var
	 */
	private $response_counter;

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function setUp(): void {
		// Before...
		parent::setUp();

		// Your set up methods here.

		$this->sample_license_response = '{
            "statusCode": 200,
            "data": {
                "success": true,
                "license": "valid",
                "item_id": false,
                "item_name": "Uncanny Automator Pro",
                "checksum": "364ae79ff23dd9eee9e9bac560def0f4",
                "expires": "2022-02-01 23:59:59",
                "payment_id": 18677,
                "license_limit": 3,
                "site_count": 0,
                "activations_left": 3,
                "price_id": "1",
                "total_usage_count": 196,
                "paid_usage_count": 500,
                "usage_limit": 250,
                "license_key": "62adb7d2e22683fdd977f786d4888c36",
                "site_names": [
                    "example.com",
                    "autoajay.uncannycloud.com",
                    "uncanny-automator:7890"

                ],
                "fetched_at": 1638280971,
                "license_id": 1583
            }
        }';

		$this->sample_error_response = '{
            "statusCode": 401,
            "error": {
                "type": "SERVER_ERROR",
                "description": "Connect a free Uncanny Automator account or activate a valid Pro license to use this action."
            }
        }';

		$this->sample_out_of_credits_response = '{
            "statusCode": 402,
            "error": {
                "type": "SERVER_ERROR",
                "description": "Your free Uncanny Automator account is out of credits. Upgrade to Uncanny Automator Pro to continue using this action."
            }
        }';

		$this->sample_fb_error_response = '{
            "statusCode": 401,
            "data": {
                "error": {
                    "message": "Test FB error"
                }
            }
        }';

		// Hook a fake HTTP request response.
		//add_filter( 'pre_http_request', array( $this, 'fake_http_request' ), 10, 3 );

	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_instance() {

		Api_Server::set_instance( null );

		$api_server = Api_Server::get_instance();

		// Check if the object is the instance of a correct class.
		$this->assertInstanceOf( Api_Server::class, Api_Server::get_instance() );

		$another_api_server = Api_Server::get_instance();

		// Check if the object is the instance of a correct class.
		$this->assertSame( $another_api_server, $api_server );

		// Check the API URL static parameter.
		$this->assertEquals( Api_Server::$url, AUTOMATOR_API_URL );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_construct() {

		Api_Server::set_instance( null );

		$api_server = Api_Server::get_instance();

		// Make sure that the class added a filter to all outbound requests.
		// has_filter will return the filter's priority integer or false if the function is not attached.

		$filter_priority = has_filter( 'http_request_args', array( $api_server, 'add_api_headers' ) );

		$this->assertTrue( $filter_priority === 10 );

		$filter_priority = has_filter( 'automator_trigger_should_complete', array( $api_server, 'maybe_log_trigger' ) );

		$this->assertTrue( $filter_priority === 10 );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_license_key() {

		$license_key = '12345';

		// Pre-set the Free license status option.
		update_option( 'uap_automator_free_license_status', 'valid' );

		// Pre-set the Free license key.
		update_option( 'uap_automator_free_license_key', $license_key );

		$this->assertTrue( Api_Server::get_instance()->get_license_key() === '12345' );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_site_name() {

		$site_url  = get_home_url();
		$site_name = Api_Server::get_instance()->get_site_name();

		// Confirm that the generated site name is a part of the site URL.
		$this->assertStringContainsString( $site_name, $site_url );

		// Make sure the http part is removed.
		$this->assertStringNotContainsString( 'http', $site_name );
		$this->assertStringNotContainsString( '://', $site_name );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_add_api_headers() {

		$args                = array();
		$args_before['test'] = 'something';

		$request_url = AUTOMATOR_API_URL . '/something';

		$args = Api_Server::get_instance()->add_api_headers( $args_before, $request_url );

		$this->assertEquals( $args_before, $args );

		// Pre-set the Free license status option.
		update_option( 'uap_automator_free_license_status', 'valid' );

		// Pre-set the Free license key.
		update_option( 'uap_automator_free_license_key', '12345' );

		$args = Api_Server::get_instance()->add_api_headers( $args_before, $request_url );

		// Make sure that params are passed through.
		$this->assertEquals( $args_before['test'], $args['test'] );

		// Check if headers are set.
		$this->assertArrayHasKey( 'headers', $args );

		$this->assertEquals( '12345', $args['headers']['license-key'] );

		$this->assertEquals( 'Uncanny Automator Free Account', $args['headers']['item-name'] );

	}

	/**
	 * @return void
	 * @throws \Exception
	 * @group Full_Coverage
	 */
	public function test_api_call() {

		$params = array(
			'endpoint' => 'v2/credits',
			'body'     => array( 'action' => 'get_credits' ),
		);

		$expected = json_decode( $this->sample_license_response, true );

		$this->fake_next_http_response( array( 'body' => $this->sample_license_response ) );

		// Normal request
		$actual = Api_Server::get_instance()->api_call( $params );

		$this->assertSame( $expected, $actual );

		// Empty response
		$this->fake_next_http_response( array( 'response' => array( 'code' => 200 ) ) );

		$expected = array(
			'data'       => null,
			'statusCode' => 200,
		);

		$actual = Api_Server::get_instance()->api_call( $params );
		$this->assertEquals( $expected, $actual );

		// A WP_Error is returned
		$this->fake_next_http_response( new \WP_Error( 444, 'Some random error' ) );

		try {

			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 500, $e->getCode() );
			$this->assertEquals( 'WordPress was not able to make a request: Some random error', $e->getMessage() );
		}

		// Non-array response
		$this->fake_next_http_response( array( 'body' => 'This is not an array' ) );

		try {
			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 500, $e->getCode() );
			$this->assertEquals( 'Invalid API response', $e->getMessage() );
		}

		// Sample error response
		$this->fake_next_http_response( array( 'body' => $this->sample_error_response ) );

		try {
			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 401, $e->getCode() );
			$this->assertEquals( 'Connect a free Uncanny Automator account or activate a valid Pro license to use this action.', $e->getMessage() );
		}

		// Out of credits.
		$this->fake_next_http_response(
			array(
				'response' => array(
					'code' => 402,
				),
				'body'     => $this->sample_out_of_credits_response,
			)
		);

		try {
			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'Credit required for action/trigger. Current credits: 0. {{automator_upgrade_link}}.', $e->getMessage() );
			$this->assertEquals( 402, $e->getCode() );
		}

		// Error from FB or Instagram
		$this->fake_next_http_response( array( 'body' => $this->sample_fb_error_response ) );

		try {
			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'API has responded with an error message: Test FB error', $e->getMessage() );
			$this->assertEquals( 401, $e->getCode() );
		}

		// Unrecognized response
		$this->fake_next_http_response( array( 'body' => '{"unrecognized": "values"}' ) );

		try {
			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'Unrecognized API response', $e->getMessage() );
			$this->assertEquals( 500, $e->getCode() );
		}

		// Missing body
		try {
			unset( $params['body'] );
			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'Request body is required', $e->getMessage() );
			$this->assertEquals( 500, $e->getCode() );
		}

		// Missing endpoint
		try {
			unset( $params['endpoint'] );
			$actual = Api_Server::get_instance()->api_call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'Endpoint is required', $e->getMessage() );
			$this->assertEquals( 500, $e->getCode() );
		}

		// Test mock
		$expected                  = array( 'test' => 'string' );
		Api_server::$mock_response = $expected;

		$actual = Api_Server::get_instance()->api_call( $params );

		$this->assertSame( $expected, $actual );
		Api_server::$mock_response = null;
	}

	// /**
	//   * Mock the next HTTP request response
	//   *
	//   * @param bool   $false     False.
	//   * @param array  $arguments Request arguments.
	//   * @param string $url       Request URL.
	//   *
	//   * @return array|bool
	//   */
	// public function fake_next_http_response( $response ) {

	// 	add_filter(
	// 		'pre_http_request',
	// 		function() use ( $response ) {
	// 			return $response;
	// 		},
	// 		10,
	// 		3
	// 	);

	// }

	/**
	 * Mock the HTTP request response
	 *
	 * @param bool $false False.
	 * @param array $arguments Request arguments.
	 * @param string $url Request URL.
	 *
	 * @return array|bool
	 * @group Full_Coverage
	 */
	public function fake_http_request( $false, $arguments, $url ) {

		if ( AUTOMATOR_API_URL . 'v2/credits' !== $url ) {
			return false;
		}

		$this->response_counter ++;

		switch ( $this->response_counter ) {
			case 1:
				return array( 'body' => $this->sample_license_response );
				break;
			case 2:
				return new WP_Error();
				break;
			case 3:
				$bad_response = str_replace( '"statusCode": 200', '"statusCode": 400', $this->sample_license_response );

				return array( 'body' => $bad_response );
				break;
			case 4:
				return array( 'body' => $this->sample_error_response );
				break;

			default:
				return false;
				break;
		}
	}

	/**
	 * @return void
	 * @throws \Exception
	 * @group Full_Coverage
	 */
	public function test_get_license() {

		// Unrecognized response
		$this->fake_next_http_response( array() );

		try {
			$actual = Api_Server::get_instance()->get_license();
		} catch ( \Exception $e ) {
			$this->assertSame( $e->getMessage(), 'Unable to fetch the license: Invalid API response' );
		}

		// Remove transient to make this test work..
		delete_transient( 'automator_license_check_failed' );

		$expected = json_decode( $this->sample_license_response, true )['data'];

		$this->fake_next_http_response( array( 'body' => $this->sample_license_response ) );

		// Normal response
		$actual = Api_Server::get_instance()->get_license();

		$this->assertSame( $expected, $actual );

		// Second time to check the transient cache
		$actual = Api_Server::get_instance()->get_license();

		$this->assertSame( $expected, $actual );

	}

	/**
	 * @return void
	 * @throws \Exception
	 * @group Full_Coverage
	 */
	public function test_has_valid_license() {

		$this->fake_next_http_response( array( 'body' => $this->sample_license_response ) );

		$actual = Api_Server::get_instance()->has_valid_license();

		$this->assertTrue( ! empty( $actual['license_key'] ) );

		// Missing license
		set_transient( 'automator_api_license', false, HOUR_IN_SECONDS );

		try {
			$actual = Api_Server::get_instance()->has_valid_license();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'License is not valid', $e->getMessage() );
		}

		// Swap the cached license to an expired one
		$license            = json_decode( $this->sample_license_response, true )['data'];
		$license['license'] = 'expired';
		set_transient( 'automator_api_license', $license, HOUR_IN_SECONDS );

		try {
			$actual = Api_Server::get_instance()->has_valid_license();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'License is not valid', $e->getMessage() );
		}

	}

	/**
	 * @return void
	 * @throws \Exception
	 * @group Full_Coverage
	 */
	public function test_has_credits() {

		$this->fake_next_http_response( array( 'body' => $this->sample_license_response ) );

		$this->assertTrue( Api_Server::get_instance()->has_credits() );

		// Missing license
		set_transient( 'automator_api_license', false, HOUR_IN_SECONDS );

		try {
			$actual = Api_Server::get_instance()->has_credits();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'License is not valid', $e->getMessage() );
		}

		// Credit limit reached on a free license
		$license                     = json_decode( $this->sample_license_response, true )['data'];
		$license['item_name']        = 'Uncanny Automator Free';
		$license['paid_usage_count'] = '1000';
		set_transient( 'automator_api_license', $license, HOUR_IN_SECONDS );

		try {
			$actual = Api_Server::get_instance()->has_credits();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'Not enough credits', $e->getMessage() );
		}

		// Credit limit not reached on a free license
		$license['paid_usage_count'] = '249';
		set_transient( 'automator_api_license', $license, HOUR_IN_SECONDS );

		$this->assertTrue( Api_Server::get_instance()->has_credits() );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_charge_usage() {

		$expected = json_decode( $this->sample_license_response, true )['data'];

		set_transient( 'automator_api_license', $expected, HOUR_IN_SECONDS );

		$this->fake_next_http_response( array( 'body' => $this->sample_license_response ) );

		// Normal response
		$actual = Api_Server::get_instance()->charge_usage();

		$this->assertSame( $expected, $actual['data'] );

		// When there are not enough credits
		$expected['item_name']        = 'Uncanny Automator Free';
		$expected['paid_usage_count'] = 250;
		set_transient( 'automator_api_license', $expected, HOUR_IN_SECONDS );

		try {
			$actual = Api_Server::get_instance()->charge_usage();
		} catch ( \Exception $e ) {
			$this->assertSame( 'Not enough credits', $e->getMessage() );
		}

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_call() {

		$params = array(
			'method' => 'POST',
		);

		//Missing url
		try {
			$actual = Api_Server::get_instance()->call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'URL is required', $e->getMessage() );
			$this->assertEquals( 500, $e->getCode() );
		}

		//Missing url
		unset( $params['method'] );
		try {
			$actual = Api_Server::get_instance()->call( $params );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'Request method is required', $e->getMessage() );
			$this->assertEquals( 500, $e->getCode() );
		}
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_maybe_add_optional_params() {

		$request         = array();
		$request['test'] = 'value';

		$params             = array();
		$params['timeout']  = 60;
		$params['blocking'] = true;
		$params['cookies']  = array(
			'cookie1' => 'value1',
			'cookie2' => 'value2',
		);

		$actual = Api_Server::get_instance()->maybe_add_optional_params( $request, $params );

		$expected = array(
			'test'     => 'value',
			'timeout'  => 60,
			'blocking' => true,
			'cookies'  => array(
				'cookie1' => 'value1',
				'cookie2' => 'value2',
			),
		);

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_create_payload() {

		$expected = array(
			'data'       => null,
			'statusCode' => null,
		);

		$actual = Api_Server::get_instance()->create_payload();

		$body = 'test';
		$code = 77;

		$expected = array(
			'data'       => $body,
			'statusCode' => $code,
		);

		$actual = Api_Server::get_instance()->create_payload( $body, $code );

		$this->assertEquals( $expected, $actual );

		$error = 'test';

		$expected = array(
			'data'       => null,
			'statusCode' => null,
			'error'      => array(
				'description' => $error,
			),
		);

		$actual = Api_Server::get_instance()->create_payload( null, null, $error );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_maybe_log_action_no_action() {

		$params = array();

		$actual = Api_Server::get_instance()->maybe_log_action( $params, array(), array() );

		$this->assertNull( $actual );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_maybe_log_action_add_log() {

		$mock = $this->getMockBuilder( Api_Server::class )
					 ->onlyMethods( array( 'get_response_credits' ) )
					 ->disableOriginalConstructor()
					 ->getMock();

		$mock->method( 'get_response_credits' )
			 ->willReturn( null );

		$params = array(
			'action'   => array(
				'recipe_log_id' => 55,
				'action_log_id' => 17,
			),
			'endpoint' => 'v2/slack',
		);

		$actual = $mock->maybe_log_action( $params, array(), array() );

		$this->assertIsInt( $actual );
		$this->assertGreaterThanOrEqual( 0, $actual );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_response_credits() {

		$error = new \WP_Error( 444, 'Some random error' );

		$this->assertNull( Api_Server::get_instance()->get_response_credits( $error ) );

		$response = array(
			'body' => json_encode(
				'text',
			),
		);

		$this->assertNull( Api_Server::get_instance()->get_response_credits( $response ) );

		$expected = array(
			'balance' => 42,
			'price'   => 1,
		);

		$response = array(
			'body' => json_encode(
				array(
					'credits' => $expected,
				)
			),
		);

		$this->assertEquals( $expected, Api_Server::get_instance()->get_response_credits( $response ) );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_response_code() {
		$error = new \WP_Error( 444, 'Some random error' );

		$this->assertEquals( 444, Api_Server::get_instance()->get_response_code( $error ) );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_maybe_log_trigger_that_doesnt_use_api() {

		$trigger = $this
			->getMockBuilder( '\Uncanny_Automator\AUTONAMI_CONTACT_ADDED_TO_LIST' )
			->setMethods(
				array( 'get_uses_api' )
			)->getMock();

		$trigger->method( 'get_uses_api' )
				->willReturn( false );

		$process_further = false;

		$args = array();

		$actual = Api_Server::get_instance()->maybe_log_trigger( $process_further, $args, $trigger );

		$this->assertEquals( $process_further, $actual );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_maybe_log_trigger_that_uses_api() {

		$api_server = $this->getMockBuilder( Api_Server::class )
						   ->onlyMethods( array( 'charge_usage' ) )
						   ->disableOriginalConstructor()
						   ->getMock();

		$api_server->method( 'charge_usage' )
				->willReturn(
					array(
						'credits' => array(
							'balance' => 42,
							'price'   => 1,
						),
					)
				);

		$trigger = $this
			->getMockBuilder( '\Uncanny_Automator\AUTONAMI_CONTACT_ADDED_TO_LIST' )
			->setMethods(
				array( 'get_uses_api' )
			)->getMock();

		$trigger->method( 'get_uses_api' )
				->willReturn( true );

		$process_further = true;

		$args = array(
			'trigger_entry' => array(
				'recipe_log_id'  => 55,
				'trigger_log_id' => 17,
			),
			'trigger_args'  => array(),
		);

		$actual = $api_server->maybe_log_trigger( $process_further, $args, $trigger );

		$this->assertEquals( $process_further, $actual );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_maybe_log_trigger_that_uses_api_and_has_no_credits() {

		$api_server = $this->getMockBuilder( Api_Server::class )
						   ->onlyMethods( array( 'charge_usage' ) )
						   ->disableOriginalConstructor()
						   ->getMock();

		$api_server->method( 'charge_usage' )
				   ->willThrowException( new \Exception( 'Out of credits' ) );

		$trigger = $this
			->getMockBuilder( '\Uncanny_Automator\AUTONAMI_CONTACT_ADDED_TO_LIST' )
			->setMethods(
				array( 'get_uses_api' )
			)->getMock();

		$trigger->method( 'get_uses_api' )
				->willReturn( true );

		$process_further = true;

		$args = array(
			'trigger_entry' => array(
				'recipe_log_id'  => 55,
				'trigger_log_id' => 17,
			),
			'trigger_args'  => array(),
		);

		$actual = $api_server->maybe_log_trigger( $process_further, $args, $trigger );

		$this->assertEquals( false, $actual );
	}

	/**
	 *
	 * Below are the tests that should run with Automator Pro enabled
	 * @group Full_Coverage
	 */
	public function test_get_license_type() {

		// The function should return false by default.
		$this->assertTrue( Api_Server::get_instance()->get_license_type() === false );

		// Pre-set the Free license status option.
		update_option( 'uap_automator_free_license_status', 'valid' );

		// The function should return 'free' now.
		$this->assertTrue( Api_Server::get_instance()->get_license_type() === 'free' );

		// Pre-set the Pro license status option.
		update_option( 'uap_automator_pro_license_status', 'valid' );

		// Define the Pro constant.
		if ( ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
			define( 'AUTOMATOR_PRO_FILE', '' );
		}

		// Now it should return 'pro'.
		$this->assertTrue( Api_Server::get_instance()->get_license_type() === 'pro' );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_item_name() {

		// This function should return an empty string if there is no license.
		$this->assertTrue( Api_Server::get_instance()->get_item_name() === '' );

		// Pre-set the Free license status option.
		update_option( 'uap_automator_free_license_status', 'valid' );

		$this->assertTrue( Api_Server::get_instance()->get_item_name() === AUTOMATOR_FREE_ITEM_NAME );

		// Pre-set the Pro license status option.
		update_option( 'uap_automator_pro_license_status', 'valid' );

		// Define the legacy Pro item name constant
		define( 'AUTOMATOR_AUTOMATOR_PRO_ITEM_NAME', 'Uncanny Automator Pro' );

		$this->assertTrue( Api_Server::get_instance()->get_item_name() === AUTOMATOR_AUTOMATOR_PRO_ITEM_NAME );

		// Define the Pro item name constant
		define( 'AUTOMATOR_PRO_ITEM_NAME', 'Uncanny Automator Pro' );

		$this->assertTrue( Api_Server::get_instance()->get_item_name() === AUTOMATOR_PRO_ITEM_NAME );

	}

	/**
	 * @return void
	 * @throws \Exception
	 * @group Full_Coverage
	 */
	public function test_undefined_timeout_should_become_30() {

		$this->mirror_next_http_response();

		$params = array(
			'method'   => 'POST',
			'url'      => Api_Server::$url,
			'endpoint' => 'v2/credits',
			'body'     => array( 'action' => 'get_credits' ),
		);

		$expected = 30;

		$actual = Api_Server::get_instance()->call( $params );

		$this->assertEquals( $expected, $actual['timeout'] );

	}

	/**
	 * @return void
	 * @throws \Exception
	 * @group Full_Coverage
	 */
	public function test_custom_timeout_should_not_be_changed() {

		$this->mirror_next_http_response();

		$params = array(
			'timeout'  => 7,
			'method'   => 'POST',
			'url'      => Api_Server::$url,
			'endpoint' => 'v2/credits',
			'body'     => array( 'action' => 'get_credits' ),
		);

		$expected = 7;

		$actual = Api_Server::get_instance()->call( $params );

		$this->assertEquals( $expected, $actual['timeout'] );

	}
}
