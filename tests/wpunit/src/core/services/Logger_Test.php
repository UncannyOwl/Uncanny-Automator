<?php
/**
 * @group logger
 */
class Logger_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var \WpunitTester
	 */
	protected $tester;

	public function setUp(): void {
		// Before...
		parent::setUp();

		// Your set up methods here.
	}

	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	// Tests
	public function test_delete_user_logger() {

		//$user_logs_deleted = Uncanny_Automator\Logger\user_logs_delete();

		//$this->assertFalse( true );
	}
}
