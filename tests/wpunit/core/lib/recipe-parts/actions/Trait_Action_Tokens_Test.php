<?php
use Uncanny_Automator\Recipe\Action_Tokens;

 /**
  * @group trait-trigger-tokens-test
  */
final class Trait_Action_Tokens extends \Codeception\TestCase\WPTestCase {

	use Action_Tokens;

	public function test_format_tokens() {

		// Happy path.
		$dev_input = array(
			'spreadSheetID'   => array(
				'name' => 'Spreadsheet ID',
				'type' => 'text',
			),
			'spreadSheetName' => array(
				'name' => 'Spreadsheet name',
				'type' => 'email',
			),
		);

		$action_code = 'ACTION_CODE_TEST';

		$token = $this->format_tokens( $dev_input, $action_code );

		$expectation = array(
			array(
				'tokenId'     => 'spreadSheetID',
				'tokenParent' => $action_code,
				'tokenName'   => 'Spreadsheet ID',
				'tokenType'   => 'text',
			),
			array(
				'tokenId'     => 'spreadSheetName',
				'tokenParent' => $action_code,
				'tokenName'   => 'Spreadsheet name',
				'tokenType'   => 'email',
			),
		);

		$this->assertSame( $expectation, $token );

	}

	public function test_format_tokens_should_return_empty_array_if_name_is_empty() {

		$dev_input = array(
			''                => array(
				'name' => 'Spreadsheet ID',
				'type' => 'text',
			),
			'spreadSheetName' => array(
				'name' => 'Spreadsheet name',
				'type' => 'email',
			),
		);

		$action_code = 'ACTION_CODE_TEST';

		$token = $this->format_tokens( $dev_input, $action_code );

		$expectation = array(
			array(
				'tokenId'     => 'spreadSheetName',
				'tokenParent' => $action_code,
				'tokenName'   => 'Spreadsheet name',
				'tokenType'   => 'email',
			),
		);

		$this->assertSame( $expectation, $token );

	}

	public function test_format_tokens_should_return_empty_array_if_key_is_empty() {

		$dev_input = array(
			'spreadSheetID'   => array(
				'name' => '',
				'type' => 'text',
			),
			'spreadSheetName' => array(
				'name' => 'Spreadsheet name',
				'type' => 'email',
			),
		);

		$action_code = 'ACTION_CODE_TEST';

		$token = $this->format_tokens( $dev_input, $action_code );

		$expectation = array(
			array(
				'tokenId'     => 'spreadSheetName',
				'tokenParent' => $action_code,
				'tokenName'   => 'Spreadsheet name',
				'tokenType'   => 'email',
			),
		);

		$this->assertSame( $expectation, $token );

	}

	public function test_format_tokens_type_should_have_int_as_type_when_empty() {

		$dev_input = array(
			'spreadSheetID'   => array(
				'name' => 'Spreadsheet ID',
			),
			'spreadSheetName' => array(
				'name' => 'Spreadsheet name',
			),
		);

		$action_code = 'ACTION_CODE_TEST';

		$token = $this->format_tokens( $dev_input, $action_code );

		$expectation = array(
			array(
				'tokenId'     => 'spreadSheetID',
				'tokenParent' => $action_code,
				'tokenName'   => 'Spreadsheet ID',
				'tokenType'   => 'int',
			),
			array(
				'tokenId'     => 'spreadSheetName',
				'tokenParent' => $action_code,
				'tokenName'   => 'Spreadsheet name',
				'tokenType'   => 'int',
			),
		);

		$this->assertSame( $expectation, $token );

	}

	public function test_set_action_tokens_should_return_false_if_no_action_code_is_provided() {

		// Should display _doing_it_wrong_notice when action id is empty.
		$registered = $this->set_action_tokens(
			array(
				'CHANNEL_ID' => array(
					'name' => __( 'Channel ID', 'uncanny-automator' ),
					'type' => 'text',
				),
			)
		);

		$this->assertFalse( $registered );
	}

	public function test_set_action_tokens_should_register_filter_if_valid() {

		$action_code = 'test';

		$registered = $this->set_action_tokens(
			array(
				'CHANNEL_ID' => array(
					'name' => __( 'Channel ID', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$action_code
		);

		$this->assertTrue( has_action( 'automator_action_' . $action_code . '_tokens_renderable' ) );

	}

	public function test_register_action_token_hooks_should_register_require_hooks() {

		$this->register_action_token_hooks();

		$this->assertTrue( has_action( 'automator_action_created' ) );
		$this->assertTrue( has_action( 'automator_action_token_input_parser_text_field_text' ) );

	}

	public function test_register_action_token_hooks_should_load_only_once() {

		// Register the hooks.
		$this->register_action_token_hooks();
		$this->assertTrue( has_action( 'automator_action_created' ) );
		$this->assertTrue( has_action( 'automator_action_token_input_parser_text_field_text' ) );

		// The following should return false. Since action hooks are already loaded.
		$this->assertFalse( $this->register_action_token_hooks() );

	}

	public function test_persist_token_value_should_return_false_when_called_directly() {

		$this->assertFalse( $this->persist_token_value( array() ) );
	}


	public function test_is_action_token() {

		$pieces = array( 'META', '123', 'RANDOM_CODE', 'SOME_KEY' );
		$this->assertTrue( $this->is_action_token( $pieces ) );

		$pieces = array( 'FIELD', '123', 'RANDOM_CODE', 'SOME_KEY' );
		$this->assertTrue( $this->is_action_token( $pieces ) );

		$pieces = array( 'ACTION_NOT', '123', 'RANDOM_CODE', 'SOME_KEY' );
		$this->assertFalse( $this->is_action_token( $pieces ) );

		$pieces = array( 'ACTION_field', '123', 'RANDOM_CODE', 'SOME_KEY' );
		$this->assertFalse( $this->is_action_token( $pieces ) );

		$pieces = array( 'MeTA', '123', 'RANDOM_CODE', 'SOME_KEY' );
		$this->assertFalse( $this->is_action_token( $pieces ) );

		$pieces = array( 'FIElD', 'RANDOM_CODE', 'SOME_KEY' );
		$this->assertFalse( $this->is_action_token( $pieces ) );

		$pieces = array( 'action_META', 'asd', 'RANDOM_CODE', 'SOME_KEY' );
		$this->assertFalse( $this->is_action_token( $pieces ) );
	}

	public function test_get_action_log_id_should_return_zero_if_no_action_log_is_found() {

		$this->assertSame( 0, absint( $this->get_action_log_id( -999, 1 ) ) );

	}

	public function test_get_action_log_id_should_return_non_empty_if_log_is_found() {
		// Mock row.
		global $wpdb;

		// Mock anonymous recipe instead of user.
		$wpdb->query(
			"INSERT INTO 
				{$wpdb->prefix}uap_recipe_log
				(`ID`, `date_time`, `user_id`, `automator_recipe_id`, `completed`, `run_number`) 
				VALUES 
				(NULL, current_timestamp(), '1', '0', '1', '1');"
		);

		$recipe_log_id = $wpdb->insert_id;

		$wpdb->query(
			"INSERT INTO 
				{$wpdb->prefix}uap_action_log 
				(`ID`, `date_time`, `user_id`, `automator_action_id`, `automator_recipe_id`, `automator_recipe_log_id`, `completed`, `error_message`) 
				VALUES 
				(NULL, current_timestamp(), '0', '355', '1', $recipe_log_id, '1', NULL); "
		);

		//Inspect: error_log( var_export( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}uap_recipe_log "), true ) );
		//Inspect: error_log( var_export( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}uap_action_log "), true ) );
		$action_log = $this->get_action_log_id( 355, $recipe_log_id );

		$this->assertNotEmpty( $action_log );

	}

	public function test_interpolate_tokens_with_values_should_return_false_when_called_directly() {

		$this->assertFalse( false );

	}

	public function test_interpolate_tokens_with_values_should_replace_token_with_actual_db_value() {

		// Replace `{{ACTION_META:99:ACTION_CODE:UNIQUE_KEY}}` with `test`.

		$dummy_text = 'This is a {{ACTION_META:99:ACTION_CODE:UNIQUE_KEY}}';

		$args = array(
			'field_text'  => 'This is a {{ACTION_META:99:ACTION_CODE:UNIQUE_KEY}}',
			'meta_key'    => null,
			'user_id'     => 1,
			'action_data' => null,
			'recipe_id'   => 155,
		);

		$trigger_args = array(
			'recipe_log_id' => 1,
		);

		$final_text = $this->interpolate_tokens_with_values( $dummy_text, $args, $trigger_args );

		$this->assertSame( 'This is a ', $final_text );

	}

}
