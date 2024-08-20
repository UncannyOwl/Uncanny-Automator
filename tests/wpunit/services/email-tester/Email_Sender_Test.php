<?php

use Uncanny_Automator\Services\Email_Tester\Email_Sender;

/**
 * @group email-tester
 */
class Email_Sender_Test extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// Before...
		parent::setUp();

	}

	public function test_parameters_should_match_default_and_supplied_parameter() {

		$email   = 'johndoe@example.net';
		$subject = 'Hi, there. How are we doin?';
		$body    = 'Hello how are you!';

		$admin_email = get_option( 'admin_email' );

		$expected = array(
			'from'         => 'Uncanny Automator email test <' . $admin_email . '>',
			'from-name'    => $admin_email,
			'content-type' => 'text/html; charset=UTF-8',
			'reply-to'     => null,
			'cc'           => null,
			'bcc'          => null,
			'to'           => $email,
			'subject'      => $subject,
			'body'         => $body,
		);

		$email_preview = new Email_Sender(
			array(
				'to'      => $email,
				'subject' => $subject,
				'body'    => $body,
			)
		);

		$actual = $email_preview->get_parameters();

		$this->assertSame( $actual, $expected );

	}

	public function test_parameters_should_handle_invalid_email() {

		$args = array(
			'to'      => 'johndoeexample.net',
			'subject' => 'Hi, there. How are we doin?',
		);

		$this->expectException( \Exception::class );
		$email_preview = ( new Email_Sender( $args ) )->validate_recipient();

	}

	public function test_parameters_should_handle_empty_body() {

		$args = array(
			'to'      => 'johnd2o@eexample.net',
			'subject' => 'Hi, there. How are we doin?',
			'body'    => '',
		);

		$this->expectException( \Exception::class );
		$email_preview = ( new Email_Sender( $args ) )->validate_body();
	}

	public function test_email_sending() {

		$email_preview = new Email_Sender(
			array(
				'to'      => 'johnd2o@eexample.net',
				'subject' => 'Hi, there. How are we doin?',
				'body'    => 'test',
			)
		);

		$expected = array( true, false );
		// Call the send method
		$actual = $email_preview->send();

		// We are testing the behaviour of the send method here.
		// Not the actual email sending. Email sending is the job of WordPress.
		// Assert that the 'success' key is set in the returned array and its value is a boolean
		$this->assertIsArray( $actual );
		$this->assertArrayHasKey( 'success', $actual );
		$this->assertTrue( $actual['success'] );
	}

}
