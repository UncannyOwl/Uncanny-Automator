<?php
use Uncanny_Automator\Automator_Get_Data;
use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Recipe_Process_Complete;
use Uncanny_Automator\Recipe_Post_Rest_Api;
use Uncanny_Automator\Recipe\Trigger_Recipe_Filters;

 /**
  */
final class Trait_Triggers_Recipe_Filters_Test extends \Codeception\TestCase\WPTestCase {

	use Trigger_Recipe_Filters;

	public function setUp(): void {

		parent::setUp();

	}

	public function test_setters_getters() {

		$recipes = array();
		$where   = array( 'a', 'b' );
		$equals  = array( 'a', 'b' );
		$formats = array( 'sanitize_text_field', 'sanitize_text_field' );

		$this->find_all( $recipes );
		$this->where( $where );
		$this->equals( $equals );
		$this->format( $formats );

		$this->assertSame( $recipes, $this->get_recipes() );
		$this->assertSame( $where, $this->get_where_conditions() );
		$this->assertSame( $equals, $this->get_match_conditions() );
		$this->assertSame( $formats, $this->get_conditions_format() );

	}

	public function test_value_format() {

		$must_be_int       = $this->value_format( '-1', 'absint' );
		$must_be_lowecased = $this->value_format( 'Just a Simple String Test', 'strtolower' );
		$must_be_trimmed   = $this->value_format( 'String with trailing spaces    ', 'trim' );

		$this->assertSame( $must_be_int, 1 );
		$this->assertSame( $must_be_lowecased, 'just a simple string test' );
		$this->assertSame( $must_be_trimmed, 'String with trailing spaces' );

	}

	public function test_get_with_failing_data_set_must_return_empty_array() {

		$trigger_recipes = $this->recipes_data_provider();

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		$service_id = 1;

		$status = 'approved';

		$this->find_all( $trigger_recipes );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		// Equals specific values.
		$this->match( array( $service_id, $status ) );

		// With the following format.
		$this->format( array( 'intval', 'trim' ) );

		$matching_recipes = $this->get();

		$result = $this->explain();

		// Just making sure the explain result has specific keys because it should have.
		$this->assertArrayHasKey( 'where_conditions_values', $result );
		$this->assertArrayHasKey( 'match_conditions_values', $result );
		$this->assertArrayHasKey( 'conditions_format', $result );

		// Make sure the keys actually match with the values.
		$this->assertSame( $result['where_conditions_values'], $this->get_actual_where_values() );
		$this->assertSame( $result['match_conditions_values'], $this->get_match_conditions() );
		$this->assertSame( $result['conditions_format'], $this->get_conditions_format() );

		// This test should return empty array.
		$this->assertSame( array(), $matching_recipes );

	}

	public function test_compare() {

		$this->compare( array( '=', '>=', '<=', '>', '<', '!=' ) );

		$this->assertSame( $this->get_compare(), array( '=', '>=', '<=', '>', '<', '!=' ) );

		return $this;

	}

	public function test_compare_should_default_with_equals_sign() {

		$this->where( array( 'a', 'b', 'c' ) );

		$this->compare( array( '>=', '', '<=' ) );

		$this->assertSame( $this->get_compare( 0 ), '>=' );

		$this->assertSame( $this->get_compare( 1 ), '=' );

		$this->assertSame( $this->get_compare( 2 ), '<=' );

		return $this;

	}


	public function test_meta_mustbeless_than_3() {

		$trigger_recipes = $this->recipes_data_provider();

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		$service_id = '1';

		$status = 'approved';

		$this->find_all( $trigger_recipes );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		// Equals specific values.
		$this->equals( array( $service_id, $status ) );

		// With the following format.
		$this->format( array( 'trim', 'wp_strip_all_tags' ) );

		$matching_recipes = $this->get();

		$this->assertSame( $matching_recipes, array( '2504' => 2513 ) );

	}

	public function test_get_with_matching_data_set_must_return_non_empty_array() {

		$trigger_recipes = $this->recipes_data_provider();

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		// Set the resulting service id to 1.
		$service_id = 1;

		// Set the resulting status to 'approved'
		$status = 'approved';

		$this->find_all( $trigger_recipes );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		// Equals specific values.
		$this->equals( array( $service_id, $status ) );

		// With the following format.
		$this->format( array( 'intval' ) );

		$matching_recipes = $this->get();

		//$matching_recipes = $this->filter( $matching_recipes );

		// This test should return the recipe id and trigger id.
		$this->assertSame( $matching_recipes, array( '2504' => 2513 ) );

	}

	public function test_is_trigger_values_set_without_where_conditions_should_return_false() {

		$trigger_id = -123;// Unlikely trigger id.
		$recipe_id  = -123;// Unlikely Recipe id.

		$this->assertFalse( $this->is_trigger_values_set( $trigger_id, $recipe_id ) );

	}

	public function test_is_trigger_values_set_with_where_conditions_but_recipe_id_and_trigger_id_not_found_should_return_false() {

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		$trigger_recipes = $this->any_data_provider();

		$this->find_all( $trigger_recipes );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		$trigger_id = 12389127983; // Unlikely trigger id.

		$recipe_id = 2504; // Valid recipe id.

		$this->assertFalse( $this->is_trigger_values_set( $trigger_id, $recipe_id ) );

	}

	public function test_is_trigger_values_set_with_where_conditions_AND_recipe_id_and_trigger_id_is_found_should_return_true() {

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		$trigger_recipes = $this->any_data_provider();

		$this->find_all( $trigger_recipes );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		$trigger_id = 2513; // Valid trigger id.

		$recipe_id = 2504; // Valid recipe id.

		$this->assertTrue( $this->is_trigger_values_set( $trigger_id, $recipe_id ) );

	}

	public function test_get_without_where_should_return_false() {

		$trigger_recipes = $this->any_data_provider();

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		$service_id = 2;

		$status = 'approved';

		$this->find_all( $trigger_recipes );

		// Equals specific values.
		$this->equals( array( $service_id, $status ) );

		// With the following format.
		$this->format( array( 'intval', 'trim' ) );

		$matching_recipes = $this->get();

		// This test should return the recipe id and trigger id.
		$this->assertSame( $matching_recipes, array() );

	}

	public function test_get_with_any_data_set_must_return_recipe_id_trigger_id_array() {

		$trigger_recipes = $this->any_data_provider();

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		$service_id = 2;

		$status = 'approved';

		$this->find_all( $trigger_recipes );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		// Equals specific values.
		$this->equals( array( $service_id, $status ) );

		// With the following format.
		$this->format( array( 'intval', 'trim' ) );

		$matching_recipes = $this->get();

		// This test should return the recipe id and trigger id.
		$this->assertSame( $matching_recipes, array( '2504' => 2513 ) );

	}

	public function test_get_with_no_matching_recipe_id_and_trigger_id_should_return_empty_array() {

		$trigger_recipes = $this->no_matching_provider();

		$matching_recipes = $this->sample_validate_trigger( 2, 'approved', $trigger_recipes );

		// This test should return the recipe id and trigger id.
		$this->assertSame( $matching_recipes->get(), array() );

	}

	private function sample_validate_trigger( $service_id, $status, $trigger_recipes ) {

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		$this->find_all( $trigger_recipes );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		// Equals specific values.
		$this->equals( array( $service_id, $status ) );

		// With the following format.
		$this->format( array( 'intval', 'trim' ) );

		return $this;

	}


	public function test_empty_format_index_should_default_to_sanitize_text_field() {

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		// Set the resulting service id to 1.
		$service_id = 1;

		// Set the resulting status to 'approved'
		$status = 'approved';

		$this->find_all( $this->recipes_data_provider() );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		// With the following format.
		$this->format( array( 'intval', 'sanitize_text_field' ) );

		// Equals specific values.
		$this->equals( array( $service_id, $status ) );

		$matching_recipe_trigger = $this->get();

		$this->assertSame( $matching_recipe_trigger, array( '2504' => 2513 ) );

		return $this;

	}

	public function test_format_argument_can_be_array() {

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		// Set the resulting service id to 1.
		$service_id = 1;

		// Set the resulting status to 'approved'
		$status = 'approved';

		$this->find_all( $this->recipes_data_provider() );

		// Trigger metas.
		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		// With the following format.
		$this->format( array( 'intval', array( $this, 'sanitize' ) ) );

		// Equals specific values.
		$this->equals( array( $service_id, $status ) );

		$matching_recipe_trigger = $this->get();

		$this->assertSame( $matching_recipe_trigger, array( '2504' => 2513 ) );

		return $this;

	}

	public function test_calling_get_without_any_params_should_return_empty_array() {

		$this->assertSame( $this->get(), array() );
	}

	public function test_filter_without_format_should_throw_exception() {

		$this->expectException( InvalidArgumentException::class );

		// Would throw exception when calling explain.
		$this->explain();

	}

	public function test_match_condition_vs_number() {

		$this->assertTrue( Automator()->utilities->match_condition_vs_number( '=', 1, 1 ) );
		$this->assertTrue( Automator()->utilities->match_condition_vs_number( '!=', 1, 2 ) );
		$this->assertTrue( Automator()->utilities->match_condition_vs_number( '>', 1, 3 ) );
		$this->assertTrue( Automator()->utilities->match_condition_vs_number( '<', 3, 1 ) );
		$this->assertTrue( Automator()->utilities->match_condition_vs_number( '<=', 3, 3 ) );
		$this->assertTrue( Automator()->utilities->match_condition_vs_number( '>=', 3, 3 ) );
		$this->assertFalse( Automator()->utilities->match_condition_vs_number( 'unknown', 3, 3 ) );
		$this->assertFalse( Automator()->utilities->match_condition_vs_number( null, 3, 3 ) );

		return $this;

	}

	/**
	 * @group test_match_condition_contains_string
	 */
	public function test_match_condition_contains_string() {

		$sample_trigger_meta = 'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META';

		// Set the resulting service id to 1.
		$service_id = 1;

		// Set the resulting status.
		$status = 'asdas[approved]asdasd';

		$this->find_all( $this->recipes_data_provider() );

		$this->where( array( $sample_trigger_meta, $sample_trigger_meta . '_STATUS' ) );

		$this->compare( array( '=', 'string_contains' ) );

		$this->format( array( 'intval', array( $this, 'sanitize' ) ) );

		$this->equals( array( $service_id, $status ) );

		$matching_recipe_trigger = $this->get();

		$this->assertSame( $matching_recipe_trigger, array( '2504' => 2513 ) );

	}

	/**
	 * Without number condition it should return all matching recipes from quiz id.
	 *
	 * @group test_number_condition
	 */
	public function test_with_number_condition_without_number_condition_should_return_all_matching_recipes() {

		$field_option_code = 'MPC_QUIZ';

		$quid_id = '20'; // The quiz ID in our data provider is 20.

		$this->find_all( json_decode( $this->get_number_conditions_data_provider(), true ) );

		$this->where( array( $field_option_code ) );

		$this->compare( array( '=' ) );

		$this->format( array( 'intval' ) );

		$this->equals( array( $quid_id ) );

		$matching_recipe_trigger = $this->get();

		// This should match all of our recipe. We have 6 recipes, so a total of 6 should be returned as count.
		$this->assertSame( 6, count( $matching_recipe_trigger ) );

	}

	/**
	 * With number condition, it should return 3 matching recipes.
	 *
	 * @group test_number_condition
	 */
	public function test_with_number_condition_with_number_condition_should_return_3_matching_recipes() {

		$field_option_code            = 'MPC_QUIZ';
		$quid_id                      = '20'; // The quiz ID in our data provider is 20.
		$number_condition_field_value = '65'; // Lets give our number condition value as '65'.

		$this->find_all( json_decode( $this->get_number_conditions_data_provider(), true ) );

		$this->where( array( $field_option_code ) );

		$this->compare( array( '=' ) );

		$this->format( array( 'intval' ) );

		// Set the number conditions.
		$this->with_number_condition( $number_condition_field_value, 'QUIZSCORE' ); // The data provider uses 'QUIZSCORE' as option field.

		$this->equals( array( $quid_id ) );

		$matching_recipe_trigger = $this->get();

		// Test cases:
		// Is 65 >= 65 ? Matched [/]
		// Is 65 <= 65 ? Matched [/]
		// Is 65 != 65 ? False
		// Is 65 = 65  ? Matched [/]
		// Is 65 < 65  ? False
		// Is 65 > 65  ? False

		// A total of 3 recipes should match. See test cases above.
		$this->assertSame( 3, count( $matching_recipe_trigger ) );

	}

	/**
	 * Method has_number_condition_matched should return false.
	 *
	 * @group test_number_condition
	 */
	public function test_has_number_condition_matched_should_return_false() {

		$field_option_code            = 'MPC_QUIZ';
		$quid_id                      = '20'; // The quiz ID in our data provider is 20.
		$number_condition_field_value = '65'; // Lets give our number condition value as '65'.

		$this->find_all( json_decode( $this->get_number_conditions_data_provider(), true ) );

		$this->where( array( $field_option_code ) );

		$this->compare( array( '=' ) );

		$this->format( array( 'intval' ) );

		// Set the number conditions.
		$this->with_number_condition( $number_condition_field_value, 'UNKNOWN_INVALID' ); // The data provider uses 'QUIZSCORE' as option field.

		$this->equals( array( $quid_id ) );

		$matching_recipe_trigger = $this->get();

		$this->assertSame( 0, count( $matching_recipe_trigger ) );

	}

	public function sanitize( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Pass anything to get a matching recipe trigger.
	 */
	private function any_data_provider() {

		// A sample return of $this->trigger_recipes() in json_format.
		$recipe_triggers = '{"2504":{"ID":"2504","post_status":"publish","recipe_type":"user","triggers":[{"ID":"2513","post_status":"publish","menu_order":"0","meta":{"code":"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS","integration":"AMELIABOOKING","uap_trigger_version":"4.1","add_action":"a:2:{i:0;s:26:\\"AmeliaBookingStatusUpdated\\";i:1;s:21:\\"AmeliaBookingCanceled\\";}","integration_name":"Amelia","sentence":"A user&#039;s booking of an appointment for {{a service:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META}} has been changed to {{a specific status:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS}}","sentence_human_readable":"A user&#039;s booking of an appointment for {{-1}} has been changed to {{Any status}}","sentence_human_readable_html":"<div><span class=\\"item-title__normal\\">A user\'s booking of an appointment for <\\/span><span class=\\"item-title__token item-title__token--filled\\" data-token-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META\\" data-options-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META\\">-1<\\/span><span class=\\"item-title__normal\\"> has been changed to <\\/span><span class=\\"item-title__token item-title__token--filled\\" data-token-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS\\" data-options-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS\\">Any status<\\/span><\\/div>","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_SERVICES_readable":"Another category","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_SERVICES":"2","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_readable":"Any service","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META":"-1","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS_readable":"Any status","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS":"-1"},"tokens":[]}],"actions":[{"ID":"2506","post_status":"publish","menu_order":"0","meta":{"code":"SENDEMAIL","integration":"WP","uap_action_version":"4.1","integration_name":"WordPress","sentence":"Send an email to {{email address:EMAILTO}}","sentence_human_readable":"Send an email to {{{{admin_email}}}}","sentence_human_readable_html":"<div><span class=\\"item-title__normal\\">Send an email to <\\/span><span class=\\"item-title__token\\" data-token-id=\\"EMAILTO\\" data-options-id=\\"EMAILTO\\"><span class=\\"uap-text-with-tokens\\"><span class=\\"uap-token\\"><uo-icon id=\\"bolt\\"><\\/uo-icon><span class=\\"uap-token__name\\">Admin email<\\/span><\\/span><\\/span><\\/span><\\/div>","EMAILCONTENTTYPE_readable":"HTML","EMAILCONTENTTYPE":"html","EMAILFROM":"{{admin_email}}","EMAILFROMNAME":"{{site_name}}","EMAILTO":"{{admin_email}}","EMAILCC":"","EMAILBCC":"","EMAILSUBJECT":"test","EMAILBODY":"<!DOCTYPE html>\\n<html>\\n<head>\\n<\\/head>\\n<body>\\n<p>This is a test<\\/p><p>Status: {{2505:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS}}<br><br>Appointment ID: {{2505:AMELIA_ID:id}}<br><br>Appointment booking end: {{2505:AMELIA_BOOKINGEND:bookingEnd}}<br><br>Appointment booking start: {{2505:AMELIA_BOOKINGSTART:bookingStart}}<br><br>Appointment provider ID: {{2505:AMELIA_PROVIDERID:providerId}}<br><br>Appointment status: {{2505:AMELIA_STATUS:status}}<br><br>Booking ID: {{2505:AMELIA_BOOKING_ID:booking_id}}<br><br>Booking appointment ID: {{2505:AMELIA_BOOKING_APPOINTMENTID:booking_appointmentId}}<br><br>Booking number of persons: {{2505:AMELIA_BOOKING_PERSONS:booking_persons}}<br><br>Booking price: {{2505:AMELIA_BOOKING_PRICE:booking_price}}<br><br>Booking status: {{2505:AMELIA_BOOKING_STATUS:booking_status}}<br><br>Customer ID: {{2505:AMELIA_CUSTOMER_ID:customer_id}}<br><br>Customer email: {{2505:AMELIA_CUSTOMER_EMAIL:customer_email}}<br><br>Customer first name: {{2505:AMELIA_CUSTOMER_FIRSTNAME:customer_firstName}}<br><br>Customer last name: {{2505:AMELIA_CUSTOMER_LASTNAME:customer_lastName}}<br><br>Customer locale: {{2505:AMELIA_CUSTOMER_TRANSLATIONS:customer_translations}}<br><br>Customer phone: {{2505:AMELIA_CUSTOMER_PHONE:customer_phone}}<br><br>Customer timezone: {{2505:AMELIA_CUSTOMER_TIMEZONE:customer_timeZone}}<br><br>Employee ID: {{2505:AMELIA_EMPLOYEE_ID:employee_id}}<br><br>Employee email: {{2505:AMELIA_EMPLOYEE_EMAIL:employee_email}}<br><br>Employee first name: {{2505:AMELIA_EMPLOYEE_FIRSTNAME:employee_firstname}}<br><br>Employee last name: {{2505:AMELIA_EMPLOYEE_LASTNAME:employee_lastname}}<br><br>Employee phone: {{2505:AMELIA_EMPLOYEE_PHONE:employee_phone}}<br><br><\\/p>\\n<\\/body>\\n<\\/html>"}}],"closures":[],"completed_by_current_user":false,"actions_conditions":"[]"}}';

		// Decode for actual usage.
		return (array) json_decode( $recipe_triggers, true );
	}

	/**
	 * Pass unlikely parameters get a non matching recipe trigger.
	 */
	private function no_matching_provider() {

		// A sample return of $this->trigger_recipes() in json_format.
		$recipe_triggers = '{"9999":{"ID":"9999","post_status":"publish","recipe_type":"user","triggers":[{"ID":"2513","post_status":"publish","menu_order":"0","meta":{"code":"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS","integration":"AMELIABOOKING","uap_trigger_version":"4.1","add_action":"a:2:{i:0;s:26:\\"AmeliaBookingStatusUpdated\\";i:1;s:21:\\"AmeliaBookingCanceled\\";}","integration_name":"Amelia","sentence":"A user&#039;s booking of an appointment for {{a service:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META}} has been changed to {{a specific status:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS}}","sentence_human_readable":"A user&#039;s booking of an appointment for {{Database Optimization}} has been changed to {{Approved}}","sentence_human_readable_html":"<div><span class=\\"item-title__normal\\">A user\'s booking of an appointment for <\\/span><span class=\\"item-title__token item-title__token--filled\\" data-token-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META\\" data-options-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META\\">1<\\/span><span class=\\"item-title__normal\\"> has been changed to <\\/span><span class=\\"item-title__token\\" data-token-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS\\" data-options-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS\\">Approved<\\/span><\\/div>","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_SERVICES_readable":"Another category","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_SERVICES":"2","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_readable":"Database Optimization","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META":"1","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS_readable":"Approved","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS":"approved"},"tokens":[]}],"actions":[{"ID":"2506","post_status":"publish","menu_order":"0","meta":{"code":"SENDEMAIL","integration":"WP","uap_action_version":"4.1","integration_name":"WordPress","sentence":"Send an email to {{email address:EMAILTO}}","sentence_human_readable":"Send an email to {{{{admin_email}}}}","sentence_human_readable_html":"<div><span class=\\"item-title__normal\\">Send an email to <\\/span><span class=\\"item-title__token\\" data-token-id=\\"EMAILTO\\" data-options-id=\\"EMAILTO\\"><span class=\\"uap-text-with-tokens\\"><span class=\\"uap-token\\"><uo-icon id=\\"bolt\\"><\\/uo-icon><span class=\\"uap-token__name\\">Admin email<\\/span><\\/span><\\/span><\\/span><\\/div>","EMAILCONTENTTYPE_readable":"HTML","EMAILCONTENTTYPE":"html","EMAILFROM":"{{admin_email}}","EMAILFROMNAME":"{{site_name}}","EMAILTO":"{{admin_email}}","EMAILCC":"","EMAILBCC":"","EMAILSUBJECT":"test","EMAILBODY":"<!DOCTYPE html>\\n<html>\\n<head>\\n<\\/head>\\n<body>\\n<p>This is a test<\\/p><p>Status: {{2505:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS}}<br><br>Appointment ID: {{2505:AMELIA_ID:id}}<br><br>Appointment booking end: {{2505:AMELIA_BOOKINGEND:bookingEnd}}<br><br>Appointment booking start: {{2505:AMELIA_BOOKINGSTART:bookingStart}}<br><br>Appointment provider ID: {{2505:AMELIA_PROVIDERID:providerId}}<br><br>Appointment status: {{2505:AMELIA_STATUS:status}}<br><br>Booking ID: {{2505:AMELIA_BOOKING_ID:booking_id}}<br><br>Booking appointment ID: {{2505:AMELIA_BOOKING_APPOINTMENTID:booking_appointmentId}}<br><br>Booking number of persons: {{2505:AMELIA_BOOKING_PERSONS:booking_persons}}<br><br>Booking price: {{2505:AMELIA_BOOKING_PRICE:booking_price}}<br><br>Booking status: {{2505:AMELIA_BOOKING_STATUS:booking_status}}<br><br>Customer ID: {{2505:AMELIA_CUSTOMER_ID:customer_id}}<br><br>Customer email: {{2505:AMELIA_CUSTOMER_EMAIL:customer_email}}<br><br>Customer first name: {{2505:AMELIA_CUSTOMER_FIRSTNAME:customer_firstName}}<br><br>Customer last name: {{2505:AMELIA_CUSTOMER_LASTNAME:customer_lastName}}<br><br>Customer locale: {{2505:AMELIA_CUSTOMER_TRANSLATIONS:customer_translations}}<br><br>Customer phone: {{2505:AMELIA_CUSTOMER_PHONE:customer_phone}}<br><br>Customer timezone: {{2505:AMELIA_CUSTOMER_TIMEZONE:customer_timeZone}}<br><br>Employee ID: {{2505:AMELIA_EMPLOYEE_ID:employee_id}}<br><br>Employee email: {{2505:AMELIA_EMPLOYEE_EMAIL:employee_email}}<br><br>Employee first name: {{2505:AMELIA_EMPLOYEE_FIRSTNAME:employee_firstname}}<br><br>Employee last name: {{2505:AMELIA_EMPLOYEE_LASTNAME:employee_lastname}}<br><br>Employee phone: {{2505:AMELIA_EMPLOYEE_PHONE:employee_phone}}<br><br><\\/p>\\n<\\/body>\\n<\\/html>"}}],"closures":[],"completed_by_current_user":false,"actions_conditions":"[]"}}';

		// Decode for actual usage.
		return (array) json_decode( $recipe_triggers, true );

	}

	/**
	 * Pass $service_id = 1 and $status = 'approved' to get a matching recipe trigger.
	 */
	private function recipes_data_provider() {

		// A sample return of $this->trigger_recipes() in json_format.
		$recipe_triggers = '{"2504":{"ID":"2504","post_status":"publish","recipe_type":"user","triggers":[{"ID":"2513","post_status":"publish","menu_order":"0","meta":{"code":"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS","integration":"AMELIABOOKING","uap_trigger_version":"4.1","add_action":"a:2:{i:0;s:26:\\"AmeliaBookingStatusUpdated\\";i:1;s:21:\\"AmeliaBookingCanceled\\";}","integration_name":"Amelia","sentence":"A user&#039;s booking of an appointment for {{a service:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META}} has been changed to {{a specific status:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS}}","sentence_human_readable":"A user&#039;s booking of an appointment for {{Database Optimization}} has been changed to {{Approved}}","sentence_human_readable_html":"<div><span class=\\"item-title__normal\\">A user\'s booking of an appointment for <\\/span><span class=\\"item-title__token item-title__token--filled\\" data-token-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META\\" data-options-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META\\">1<\\/span><span class=\\"item-title__normal\\"> has been changed to <\\/span><span class=\\"item-title__token\\" data-token-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS\\" data-options-id=\\"AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS\\">Approved<\\/span><\\/div>","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_SERVICES_readable":"Another category","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_SERVICES":"2","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_readable":"Database Optimization","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META":"1","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS_readable":"Approved","AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS":"approved<script></script>"},"tokens":[]}],"actions":[{"ID":"2506","post_status":"publish","menu_order":"0","meta":{"code":"SENDEMAIL","integration":"WP","uap_action_version":"4.1","integration_name":"WordPress","sentence":"Send an email to {{email address:EMAILTO}}","sentence_human_readable":"Send an email to {{{{admin_email}}}}","sentence_human_readable_html":"<div><span class=\\"item-title__normal\\">Send an email to <\\/span><span class=\\"item-title__token\\" data-token-id=\\"EMAILTO\\" data-options-id=\\"EMAILTO\\"><span class=\\"uap-text-with-tokens\\"><span class=\\"uap-token\\"><uo-icon id=\\"bolt\\"><\\/uo-icon><span class=\\"uap-token__name\\">Admin email<\\/span><\\/span><\\/span><\\/span><\\/div>","EMAILCONTENTTYPE_readable":"HTML","EMAILCONTENTTYPE":"html","EMAILFROM":"{{admin_email}}","EMAILFROMNAME":"{{site_name}}","EMAILTO":"{{admin_email}}","EMAILCC":"","EMAILBCC":"","EMAILSUBJECT":"test","EMAILBODY":"<!DOCTYPE html>\\n<html>\\n<head>\\n<\\/head>\\n<body>\\n<p>This is a test<\\/p><p>Status: {{2505:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS:AMELIA_USER_APPOINTMENT_BOOKED_SERVICE_SPECIFIC_STATUS_META_STATUS}}<br><br>Appointment ID: {{2505:AMELIA_ID:id}}<br><br>Appointment booking end: {{2505:AMELIA_BOOKINGEND:bookingEnd}}<br><br>Appointment booking start: {{2505:AMELIA_BOOKINGSTART:bookingStart}}<br><br>Appointment provider ID: {{2505:AMELIA_PROVIDERID:providerId}}<br><br>Appointment status: {{2505:AMELIA_STATUS:status}}<br><br>Booking ID: {{2505:AMELIA_BOOKING_ID:booking_id}}<br><br>Booking appointment ID: {{2505:AMELIA_BOOKING_APPOINTMENTID:booking_appointmentId}}<br><br>Booking number of persons: {{2505:AMELIA_BOOKING_PERSONS:booking_persons}}<br><br>Booking price: {{2505:AMELIA_BOOKING_PRICE:booking_price}}<br><br>Booking status: {{2505:AMELIA_BOOKING_STATUS:booking_status}}<br><br>Customer ID: {{2505:AMELIA_CUSTOMER_ID:customer_id}}<br><br>Customer email: {{2505:AMELIA_CUSTOMER_EMAIL:customer_email}}<br><br>Customer first name: {{2505:AMELIA_CUSTOMER_FIRSTNAME:customer_firstName}}<br><br>Customer last name: {{2505:AMELIA_CUSTOMER_LASTNAME:customer_lastName}}<br><br>Customer locale: {{2505:AMELIA_CUSTOMER_TRANSLATIONS:customer_translations}}<br><br>Customer phone: {{2505:AMELIA_CUSTOMER_PHONE:customer_phone}}<br><br>Customer timezone: {{2505:AMELIA_CUSTOMER_TIMEZONE:customer_timeZone}}<br><br>Employee ID: {{2505:AMELIA_EMPLOYEE_ID:employee_id}}<br><br>Employee email: {{2505:AMELIA_EMPLOYEE_EMAIL:employee_email}}<br><br>Employee first name: {{2505:AMELIA_EMPLOYEE_FIRSTNAME:employee_firstname}}<br><br>Employee last name: {{2505:AMELIA_EMPLOYEE_LASTNAME:employee_lastname}}<br><br>Employee phone: {{2505:AMELIA_EMPLOYEE_PHONE:employee_phone}}<br><br><\\/p>\\n<\\/body>\\n<\\/html>"}}],"closures":[],"completed_by_current_user":false,"actions_conditions":"[]"}}';

		// Decode for actual usage.
		return (array) json_decode( $recipe_triggers, true );

	}

	/**
	 * The number conditions data provider. These data are taken from MemberPress courses.
	 *
	 * The data provider contains 6 recipes in total with different comparison symbol for each recipes.
	 *
	 * Go to Traits_Triggers_Recipe_Filters_Provider class and json_decode the data to inspect.
	 *
	 * @return array The recipes to tests against with.
	 */
	private function get_number_conditions_data_provider() {

		require_once UA_ABSPATH . 'tests/wpunit/data-providers/Traits_Trigger_Recipe_Filters_Provider.php';

		return ( new Traits_Trigger_Recipe_Filters_Provider() )->get_number_conditions_provider();

	}

}
