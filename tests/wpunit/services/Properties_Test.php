<?php
/**
 * @group services
 */
class Properties_Test extends \Codeception\TestCase\WPTestCase {

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

	public function test_dispatch_should_register_automator_action_created_hook() {

		$properties = new Uncanny_Automator\Services\Properties();

		$properties->dispatch();

		$priority = has_action( 'automator_action_created', array( $properties, 'record_properties' ) );

		$this->assertTrue( $priority === 20 );

	}

	public function test_record_properties_method_should_remove_automator_action_created_hook_callback() {

		$properties = new Uncanny_Automator\Services\Properties();

		$properties->dispatch();

		// An action has been registered.
		$priority = has_action( 'automator_action_created', array( $properties, 'record_properties' ) );

		$this->assertTrue( $priority === 20 );

		// An action should be removed at this point after record_properties is run.
		$properties->record_properties(
			array(
				'user_id'       => 1,
				'action_log_id' => 1,
				'action_id'     => 1,
			)
		);

		$priority = has_action( 'automator_action_created', array( $properties, 'record_properties' ) );

		$this->assertFalse( $priority );
	}

	public function test_add_item_should_reflect_result_in_get_items() {

		$properties = new Uncanny_Automator\Services\Properties();

		$item_1 = array(
			'type'       => 'code',
			'label'      => 'Code',
			'value'      => '200',
			'attributes' => array(),
		);

		$properties->add_item( $item_1 );

		$this->assertSame( array( $item_1 ), $properties->get_items() );

		// -- Create new instance.
		$properties = new Uncanny_Automator\Services\Properties();

		$item_2 = array(
			'type'       => 'code',
			'label'      => 'Code',
			'value'      => '200',
			'attributes' => array(),
		);

		$item_3 = array(
			'type'  => 'text',
			'label' => 'Text',
			'value' => '250',
		);

		$properties->add_item( $item_2 );
		$properties->add_item( $item_3 );

		$this->assertSame( array( $item_2, $item_3 ), $properties->get_items() );

	}

}
