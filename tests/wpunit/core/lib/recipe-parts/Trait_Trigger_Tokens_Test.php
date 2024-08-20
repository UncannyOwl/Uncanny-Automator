<?php
use Uncanny_Automator\Recipe\Trigger_Tokens;

require_once UA_ABSPATH . '/tests/wpunit/data-providers/Traits_Trigger_Token_Provider.php';

 /**
  * @group trait-trigger-tokens-test
  */
final class Trait_Trigger_Tokens extends \Codeception\TestCase\WPTestCase {

	use Trigger_Tokens;

	const FAKE_TRIGGER_CODE = 'TRIGGER_CODE';

	const FAKE_INTEGRATION = 'SUPER_INTEGRATION';

	public function setUp(): void {

		parent::setUp();

	}

	/**
	 * Problem 1
	 *
	 * Trait_Trigger_Tokens has slightly created a tight coupling with Trait_Setup
	 *
	 * Solution
	 *
	 * Inject the dependency `Trait_Setup` as parameter so we can mock it.
	 *
	 * In the meantime
	 *
	 * Just provide a temporary method.
	 */
	public function get_trigger_code() {
		return self::FAKE_TRIGGER_CODE;
	}

	/**
	 * With problem with get_trigger_code()
	 */
	public function get_integration() {
		return self::FAKE_INTEGRATION;
	}

	public function token_data_provider(): array {

		return Traits_Trigger_Token_Provider::trigger_token_data( self::FAKE_TRIGGER_CODE );

	}

	/**
	 * This is a data provider
	 *
	 * @see https://blog.martinhujer.cz/how-to-use-data-providers-in-phpunit/
	 */
	public function trigger_invalid_provider(): array {

		$mocked_trigger = $this->getMockBuilder( \Uncanny_Automator\Recipe\Trigger_Setup::class )
			->getMockForTrait();

		$mocked_trigger->set_trigger_code( self::FAKE_TRIGGER_CODE );

		return Traits_Trigger_Token_Provider::trigger_data( self::FAKE_TRIGGER_CODE . '_WONT_MATCH', $mocked_trigger );

	}

	/**
	 * This is a data provider
	 *
	 * @see https://phpunit.de/manual/3.7/en/writing-tests-for-phpunit.html
	 */
	public function trigger_valid_provider(): array {

		$mocked_trigger = $this->getMockBuilder( \Uncanny_Automator\Recipe\Trigger_Setup::class )
			->getMockForTrait();

		$mocked_trigger->set_trigger_code( self::FAKE_TRIGGER_CODE );

		return Traits_Trigger_Token_Provider::trigger_data( self::FAKE_TRIGGER_CODE, $mocked_trigger );

	}

	protected function data_provider_fake_filter() {

		return strtr(
			'automator_maybe_trigger_{{integration}}_{{code}}_tokens',
			array(
				'{{integration}}' => strtolower( self::FAKE_INTEGRATION ),
				'{{code}}'        => strtolower( self::FAKE_TRIGGER_CODE ),
			)
		);

	}

	/**
	 * When tokens are not set, the method should bail.
	 *
	 * Method under test: add_trigger_tokens_filter
	 *
	 * @return void
	 */
	public function test_add_trigger_tokens_filter__empty_tokens_should_bail() {

		$filter = $this->add_trigger_tokens_filter( self::FAKE_TRIGGER_CODE, self::FAKE_INTEGRATION );

		// Important: If no tokens are set, the filter should not be available, hence, returning false.
		$this->assertFalse( has_filter( $this->data_provider_fake_filter() ) );

		// Now set the tokens..
		$this->set_tokens(
			array(
				'FIELD' => array(
					'name' => 'This is a clean token',
				),
			)
		);

		$filter = $this->add_trigger_tokens_filter( self::FAKE_TRIGGER_CODE, self::FAKE_INTEGRATION );

		// Filter should now return true.
		$this->assertTrue( has_filter( $this->data_provider_fake_filter() ) );

	}

	/**
	 * Method under test: add_trigger_tokens_filter
	 *
	 * @group add_trigger_tokens_filter
	 * @return void
	 */
	public function test_add_trigger_tokens_filter__should_return_renderable_token_if_exists() {

		$this->set_tokens(
			array(
				'FIELD' => array(
					'name' => 'My token',
				),
			)
		);

		$this->set_tokens_renderable( $this->get_tokens(), self::FAKE_TRIGGER_CODE );

		$this->assertTrue( $this->has_token_renderable( self::FAKE_TRIGGER_CODE ) );

		$this->assertArrayHasKey( 'tokenIdentifier', $this->get_tokens_renderable( self::FAKE_TRIGGER_CODE )[0] );

		$this->add_trigger_tokens_filter( self::FAKE_TRIGGER_CODE, self::FAKE_INTEGRATION );

		// Important: If no tokens are set, the filter should not be available.
		$filter = $this->data_provider_fake_filter();

		$this->assertTrue( has_filter( $filter ) );

		$tokens = apply_filters( $filter, $this->get_tokens(), array() );

		$this->assertArrayHasKey( 'tokenIdentifier', $tokens[0] );

	}

	/**
	 * When there is a valid set of tokens, our method should register the filter.
	 *
	 * Method under test: add_trigger_tokens_filter
	 *
	 * @return void
	 */
	public function test_add_trigger_tokens_filter__non_empty_tokens_should_register_filter() {

		$this->set_tokens(
			array(
				'FIELD' => array(
					'name' => 'My token',
				),
			)
		);

		// Since the tokens are set, get_tokens should not be empty. Otherwise, this test will fail.
		$this->assertNotEmpty( $this->get_tokens() );

		$this->add_trigger_tokens_filter( self::FAKE_TRIGGER_CODE, self::FAKE_INTEGRATION );

		$filter = $this->data_provider_fake_filter();

		$this->assertTrue( has_filter( $filter ) );

		$filter = strtr(
			'automator_maybe_trigger_{{integration}}_{{code}}_tokens',
			array(
				'{{integration}}' => strtolower( $this->get_integration() ),
				'{{code}}'        => strtolower( $this->get_trigger_code() ),
			)
		);

		$renderable_tokens = apply_filters( $filter, $this->get_tokens(), array() );

		$this->assertNotEmpty( $renderable_tokens );

	}

	/**
	 * Method under test: has_token_renderable
	 *
	 * @return void
	 */
	public function test_has_token_renderable__when_there_is_token_should_return_true() {

		$this->set_tokens(
			array(
				'FIELD' => array(
					'name' => 'My token',
				),
			)
		);

		// The follow assertion should assert false since we didn't set any renderable tokens.
		$this->assertFalse( $this->has_token_renderable( self::FAKE_TRIGGER_CODE ) );

		// Set renderable tokens.
		$this->set_tokens_renderable( $this->get_tokens(), self::FAKE_TRIGGER_CODE );

		// The following assertion should now return true.
		$this->assertTrue( $this->has_token_renderable( self::FAKE_TRIGGER_CODE ) );

	}

	/**
	 * Method under test: set_tokens_renderable
	 */
	public function test_set_tokens_renderable__should_return_wp_error_IF_trigger_code_is_empty() {

		$this->assertTrue( is_wp_error( $this->set_tokens_renderable( $this->get_tokens(), '' ) ) );

	}

	/**
	 * Method under test: set_tokens_renderable
	 */
	public function test_set_tokens_renderable__should__NOT_return_wp_error_IF_trigger_code_NOT_empty() {

		$this->set_tokens(
			array(
				'FIELD' => array(
					'name' => 'My token',
				),
			)
		);

		$this->assertFalse( is_wp_error( $this->set_tokens_renderable( $this->get_tokens(), self::FAKE_TRIGGER_CODE ) ) );

	}

	/**
	 * Method under test: set_tokens_renderable
	 */
	public function test_set_tokens_renderable__should__return_wp_error_IF_tokens_no_tokens_is_set() {

		// No $this->set_tokens(); ? Then, $this->get_tokens() is empty. Assert true.
		$this->assertTrue( is_wp_error( $this->set_tokens_renderable( $this->get_tokens(), self::FAKE_TRIGGER_CODE ) ) );

	}

	/**
	 * Method under test: get_tokens_renderable
	 */
	public function test_get_tokens_renderable() {

		$this->set_tokens(
			array(
				'FIELD' => array(
					'name' => 'My token',
				),
			)
		);

		$this->set_tokens_renderable( $this->get_tokens(), self::FAKE_TRIGGER_CODE );

		$tokens = $this->get_tokens_renderable( self::FAKE_TRIGGER_CODE );

		$this->assertSame( $tokens[0]['tokenId'], 'FIELD' );
		$this->assertSame( $tokens[0]['tokenName'], 'My token' );
		$this->assertSame( $tokens[0]['tokenType'], 'text' );
		$this->assertSame( $tokens[0]['tokenIdentifier'], 'TRIGGER_CODE' );

		$token = $this->get_tokens_renderable( 'NEGATIVE' );

		$this->assertEmpty( $token );

	}


	/**
	 * Method under test: validate_tokens
	 */
	public function test_validate_tokens__should__return_wp_error_if_key_or_value_is_empty() {

		$this->set_tokens(
			array(
				'tokenIdentifer1' => array(
					'' => 'My token', // <-- Invalid 🙅
				),
				'tokenIdentifer2' => array(
					'non_empty' => '', // <-- Invalid 🙅
				),
				array(), //<-- Really invalid 🙈
			)
		);

		foreach ( $this->get_tokens() as $id => $props ) {
			$this->assertTrue( is_wp_error( $this->validate_token( $id, $props ) ) );
		}

	}
	/**
	 * @expectedIncorrectUseage
	 * @group incorrect-usage
	 */
	public function test_validate_tokens__should__return_incorrect_usage() {

		$this->set_tokens(
			array(
				'tokenIdentifer1' => array(
					'' => 'My token', // <-- Invalid 🙅
				),
				'tokenIdentifer2' => array(
					'non_empty' => '', // <-- Invalid 🙅
				),
				array(), //<-- Really invalid 🙈
			)
		);

		$renderable = $this->set_tokens_renderable( $this->get_tokens(), self::FAKE_TRIGGER_CODE );

		$this->assertFalse( is_wp_error( $renderable ) );

	}

	 /**
	 * Method under test: validate_tokens
	 */
	public function test_validate_tokens__should__return_true_if_key_AND_value_is_NOT_empty() {

		$this->set_tokens(
			array(
				'tokenIdentifer1' => array(
					'name' => 'My token',
				),
				'tokenIdentifer2' => array(
					'name' => 'My Token 2',
				),
			)
		);

		foreach ( $this->get_tokens() as $id => $props ) {
			// Should not throw wp_error..
			$this->assertFalse( is_wp_error( $this->validate_token( $id, $props ) ) );
			// Instead, it should assert as true since proposed tokens are good 🙋
			$this->assertTrue( $this->validate_token( $id, $props ) );
		}

	}

	/**
	 * Method under test: save_token_data
	 */
	public function test_save_token_data__should_bail_if_trigger_args_OR_trigger_code_is_empty() {

		$args    = array();
		$trigger = array();

		$this->assertNull( $this->save_token_data( $args, $trigger ) );

	}

	/**
	 * Method under test: save_token_data
	 *
	 * @dataProvider trigger_invalid_provider
	 */
	public function test_save_token_data__should_bail_if_entry_args_code_is_NOT_equals_to_trigger_code( $args, $mocked_trigger ) {

		$this->assertEquals( self::FAKE_TRIGGER_CODE, $mocked_trigger->get_trigger_code() );

		$this->assertNotEmpty( $args );

		$this->assertNotEmpty( $args['entry_args']['code'] );

		$this->assertNotEquals( $args['entry_args']['code'], $mocked_trigger->get_trigger_code() );

		$this->assertNotSame( $mocked_trigger->get_trigger_code(), $args['entry_args']['code'] );

		// This is a failing test.
		$this->assertNull( $this->save_token_data( $args, $mocked_trigger ) );

	}

	/**
	 * Method under test: save_token_data
	 *
	 * @group token_fetching
	 *
	 * @dataProvider trigger_valid_provider
	 */
	public function test_save_token_data__should_return_1_if_trigger_data_is_valid( $args, $mocked_trigger ) {

		$this->set_tokens(
			array(
				'tokenIdentifier'  => array(
					'name'         => 'Awesome token',
					'hydrate_with' => array( $this, 'callback_function' ),
				),
				'tokenIdentifier2' => array(
					'name'         => 'Awesome token 2',
					'hydrate_with' => 'trigger_args|2',
				),
				'tokenIdentifier3' => array(
					'name' => 'Awesome token 3',
				),
			)
		);

		$this->assertEquals( self::FAKE_TRIGGER_CODE, $mocked_trigger->get_trigger_code() );

		$this->assertNotEmpty( $args );

		$this->assertNotEmpty( $args['entry_args']['code'] );

		$this->assertSame( $args['entry_args']['code'], $mocked_trigger->get_trigger_code() );

		$this->assertSame( $mocked_trigger->get_trigger_code(), $args['entry_args']['code'] );

		// This is a failing test.
		$this->assertSame( 1, $this->save_token_data( $args, $mocked_trigger ) );

	}

	public function test_hydrate_token() {

		$value = time();

		$this->hydrate_token( 'sample_key', $value );

		$token_values = $this->get_values();

		$this->assertArrayHasKey( 'sample_key', $token_values );

		$this->assertSame( $value, $token_values['sample_key'] );

	}

	/**
	 * @dataProvider trigger_valid_provider
	 */
	public function test_hydrate_tokens( $args, $mocked_trigger ) {

		$value = time();

		$this->set_tokens(
			array(
				'tokenIdentifier'  => array(
					'name'         => 'Awesome token',
					'hydrate_with' => array( $this, 'callback_function' ),
				),
				'tokenIdentifier2' => array(
					'name'         => 'Awesome token 2',
					'hydrate_with' => 'trigger_args|2',
				),
			)
		);

		$parsed = $this->hydrate_tokens( $args, $mocked_trigger );

		$this->assertSame( $parsed['tokenIdentifier'], 'RETURN_VALUE' );
		$this->assertSame( $parsed['tokenIdentifier2'], 'UncannyAutomatorFTW!' );

	}

	public function callback_function( ...$args ) {
		return 'RETURN_VALUE';
	}

	/**
	 * @dataProvider token_data_provider
	 *
	 * @depends test_save_token_data__should_return_1_if_trigger_data_is_valid
	 *
	 * @group token_fetching
	 */
	public function test_fetch_token_data( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		global $wpdb;

		$token = $this->fetch_token_data( 'Existing', $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		$this->assertSame( $token, 'Existing' );

		// Fill with dummy data.
		$wpdb->insert(
			"{$wpdb->prefix}uap_trigger_log_meta",
			array(
				'user_id'                  => 1,
				'automator_trigger_log_id' => 1,
				'automator_trigger_id'     => 1,
				'meta_key'                 => self::FAKE_TRIGGER_CODE,
				'meta_value'               => wp_json_encode( array( self::FAKE_TRIGGER_CODE => 'AutomatorFtW!Whazaa!@#' ) ),
			)
		);

		$token = $this->fetch_token_data( '', $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		$this->assertSame( $token, 'AutomatorFtW!Whazaa!@#' );

		// Positive testing. Empty $trigger_data.
		$token = $this->fetch_token_data( '', $pieces, $recipe_id, array(), $user_id, $replace_args );
		$this->assertEmpty( $token );

		// Negative testing. Empty $pieces.
		$token = $this->fetch_token_data( '', array(), $recipe_id, $trigger_data, $user_id, $replace_args );

		$this->assertEmpty( $token );

	}

	public function test_set_trigger_tokens() {

		$this->set_trigger_tokens( 'apple' );

		$this->assertSame( 'apple', $this->get_trigger_tokens() );

	}

	public function test_enqueue_token_action_and_filter() {

		$this->enqueue_token_action_and_filter();

		$this->assertTrue( has_action( 'automator_before_trigger_completed' ) );

		$this->assertTrue( has_filter( 'automator_parse_token_for_trigger_' . strtolower( self::FAKE_INTEGRATION ) . '_' . strtolower( self::FAKE_TRIGGER_CODE ) ) );

	}

}
