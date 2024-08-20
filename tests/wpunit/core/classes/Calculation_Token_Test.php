<?php

use Uncanny_Automator\Calculation_Token;

/**
 *
 */
class Calculation_Token_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		// Before...
		parent::setUp();
		$this->calc_token = new Calculation_Token();
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
	 */
	public function test_filter() {

		$has_filter = has_filter( 'automator_maybe_parse_token', array( $this->calc_token, 'parse_token' ) );

		$this->assertTrue( 999 === $has_filter );

	}

	/**
	 * @return void
	 */
	public function test_replace_brackets() {

		$string   = '««token¦something»» ««other¦something¦45»»';
		$expected = '{{token:something}} {{other:something:45}}';

		$actual = $this->calc_token->replace_brackets( $string );

		$this->assertSame( $expected, $actual );

	}

	/**
	 * @return void
	 */
	public function test_is_calculation_token() {

		$replace_args = array(
			'something'      => 'something',
			'pieces'         => array(
				'CALCULATION',
				'formula here',
			),
			'something_else' => 'something_else',
		);

		$actual = $this->calc_token->is_calculation_token( $replace_args );

		$this->assertTrue( $actual );

		$replace_args = array();

		$actual = $this->calc_token->is_calculation_token( $replace_args );

		$this->assertNotTrue( $actual );

	}

	/**
	 * @return void
	 */
	public function test_get_result() {

		$this->calc_token->result          = '7542';
		$this->calc_token->replace_42_with = '24';
		$expected                          = '7524';

		add_filter(
			'automator_calculation_result',
			function( $result, $object ) {
				return str_replace( '42', $object->replace_42_with, $result );
			},
			10,
			2
		);

		$actual = $this->calc_token->get_result();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @dataProvider formula_provider
	 */
	public function test_calculate( $formula, $expected_result ) {

		$this->calc_token->parsed_formula = $formula;

		$this->calc_token->calculate();

		$this->assertSame( $expected_result, $this->calc_token->result );

	}

	/**
	 * Provides different scensarios for test_calculate
	 */
	public function formula_provider() {
		return array(
			array( '6*7', 42 ),                                 // #0
			array( '10 - 1', 9 ),                               // #1
			array( '1+2*3-4', 3 ),                              // #2
			array( '1 + 2 * 3 - 4', 3 ),                        // #3
			array( 'pi * 2', 6.283185307179586 ),              // #4
			array( 'PI * 2 * 2', 12.566370614359172 ),          // #5
			array( 'abs(1) + min(1,2) * max(1,2,3)', 4 ),       // #6
			array( 'min(1+2, abs(-1))', 1 ),                    // #7
			array( '1 + ((2 - 3) * (5 - 7))', 3 ),              // #8
			array( '2 * (-3)', -6 ),                            // #9
		);
	}

	/**
	 * @return void
	 */
	public function test_parse_inner_tokens() {

		$this->calc_token->recipe_id    = '42';
		$this->calc_token->user_id      = '42';
		$this->calc_token->replace_args = array();

		$string    = 'The date unix timestamp is {{currentdate_unix_timestamp}}';
		$timestamp = strtotime( date( 'Y-m-d' ), current_time( 'timestamp' ) );
		$expected  = 'The date unix timestamp is ' . $timestamp;

		$actual = $this->calc_token->parse_inner_tokens( $string );

		$this->assertSame( $expected, $actual );

		$string   = 'There is no token here';
		$expected = 'There is no token here';

		$actual = $this->calc_token->parse_inner_tokens( $string );

		$this->assertSame( $expected, $actual );

		$string   = 'There is no token here';
		$expected = 'There is no token here';

		$actual = $this->calc_token->parse_inner_tokens( $string );

		$this->assertSame( $expected, $actual );

		$string   = '0+{{site_name}}+1';
		$expected = '0+0+1';

		$actual = $this->calc_token->parse_inner_tokens( $string );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function test_get_formula() {

		$replace_args = array(
			'pieces' => array(
				'CALCULATION',
				'««USERMETA¦sample_page_view_count»» + ««POSTMETA¦[[40;VIEWPAGE;WPPAGE_ID]]¦[[40;VIEWPAGE;WPPAGE]]»» + ««40¦VIEWPAGE¦WPPAGE_ID»» + 1',
			),
		);

		$expected = '{{USERMETA:sample_page_view_count}} + {{POSTMETA:[[40;VIEWPAGE;WPPAGE_ID]]:[[40;VIEWPAGE;WPPAGE]]}} + {{40:VIEWPAGE:WPPAGE_ID}} + 1';

		$actual = $this->calc_token->get_formula( $replace_args );

		$this->assertSame( $expected, $actual );

		$this->expectException( \Exception::class );

		$this->calc_token->get_formula( array() );

	}

	/**
	 * @return void
	 */
	public function test_hydrate() {
		$return       = 1;
		$pieces       = 2;
		$recipe_id    = 3;
		$trigger_data = 4;
		$user_id      = 5;
		$replace_args = array(
			'pieces' => array(
				'CALCULATION',
				'6 * 7',
			),
		);

		$actual = $this->calc_token->hydrate( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		$this->assertSame( $return, $this->calc_token->return );
		$this->assertSame( $pieces, $this->calc_token->pieces );
		$this->assertSame( $recipe_id, $this->calc_token->recipe_id );
		$this->assertSame( $trigger_data, $this->calc_token->trigger_data );
		$this->assertSame( $user_id, $this->calc_token->user_id );
		$this->assertSame( $replace_args, $this->calc_token->replace_args );

		$this->assertSame( '6 * 7', $this->calc_token->formula );
		$this->assertSame( '6 * 7', $this->calc_token->parsed_formula );
	}

	/**
	 * @return void
	 */
	public function test_parse_token() {
		$return       = 1;
		$pieces       = 2;
		$recipe_id    = 3;
		$trigger_data = 4;
		$user_id      = 5;
		$replace_args = array(
			'pieces' => array(
				'CALCULATION',
				'6 * 7',
			),
		);

		$expected = 42;
		$actual   = $this->calc_token->parse_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		$this->assertSame( $expected, $actual );

		$expected     = 1;
		$replace_args = array(
			'pieces' => array(
				'SOMETOKEN',
				'VALUE',
			),
		);

		$actual = $this->calc_token->parse_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		$this->assertSame( $expected, $actual );

		$expected = 'Error: Detected unknown or invalid string identifier.';

		$replace_args = array(
			'pieces' => array(
				'CALCULATION',
				'6 * not_a_number',
			),
		);

		$actual = $this->calc_token->parse_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		 $this->assertSame( $expected, $actual );

	}
}
