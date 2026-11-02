<?php
namespace Uncanny_Automator\Integrations\Advanced;

use Uncanny_Automator\Tokens\Universal_Token;

class Calculation_Token extends Universal_Token {

	/**
	 * @var \ChrisKonnertz\StringCalc\StringCalc
	 */
	public $string_calc;

	/**
	 * @var
	 */
	public $recipe_id;

	/**
	 * @var
	 */
	public $user_id;

	/**
	 * @var
	 */
	public $replace_args;

	/**
	 * @var
	 */
	public $parsed_formula;

	/**
	 * @var
	 */
	public $result;

	/**
	 * @var
	 */
	public $return;

	/**
	 * @var
	 */
	public $pieces;

	/**
	 * @var
	 */
	public $trigger_data;

	/**
	 * @var
	 */
	public $formula;

	/**
	 * @var
	 */
	public $id_template;

	/**
	 * @var
	 */
	public $name_template;

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'ADVANCED';
		$this->id          = 'CALCULATION';
		$this->id_template = 'FORMULA';
		// translators: Calculation formula
		$this->name_template = sprintf( esc_attr_x( 'Calculation: %1$s', 'Token', 'uncanny-automator' ), '{{FORMULA}}' );
		$this->name          = esc_attr_x( 'Calculation', 'Token', 'uncanny-automator' );
		$this->requires_user = false;
		$this->type          = 'text';
		$this->cacheable     = true;
	}
	/**
	 * Get fields.
	 *
	 * @return mixed
	 */
	public function get_fields() {
		return array(
			array(
				'input_type'         => 'text',
				'option_code'        => 'FORMULA',
				'required'           => true,
				'label'              => esc_attr_x( 'Formula', 'Advanced', 'uncanny-automator' ),
				'description'        => esc_attr_x( 'The math formula to calculate. Use numbers, tokens that return numbers, the operators +, -, *, /, ^ and %, parentheses, and functions such as round, floor, ceil, abs, sqrt, min and max.', 'Advanced', 'uncanny-automator' ) . sprintf( ' <a href="%2$s">%1$s</a>', esc_attr_x( 'Learn more', 'Advanced', 'uncanny-automator' ) . ' <uas-icon code="arrow-up-right-from-square"></uas-icon>', 'https://automatorplugin.com/knowledge-base/calculations-math-equations/?utm_source=uncanny-automator&utm_medium=in-plugin&utm_content=add-token-calculation-formula-learn-more' ),
				'supports_tokens'    => true,
				'unsupported_tokens' => array( 'UT:ADVANCED:CALCULATION' ),
			),
		);
	}

	/**
	 * get_formula
	 *
	 * @return $this
	 */
	public function get_formula( $replace_args ) {

		if ( empty( $replace_args['pieces'][3] ) ) {
			throw new \Exception( esc_html_x( 'Error: Missing formula.', 'Advanced', 'uncanny-automator' ) );
		}

		$formula = $replace_args['pieces'][3];

		return $formula;
	}

	/**
	 * calculate
	 *
	 * @return $this
	 */
	public function calculate() {

		$this->result = $this->string_calc->calculate( $this->parsed_formula );

		return $this;
	}

	/**
	 * get_result
	 *
	 * @return string
	 */
	public function get_result() {
		return apply_filters( 'automator_calculation_result', $this->result, $this );
	}

	/**
	 * parse_integration_token
	 *
	 * @return string
	 */
	public function parse_integration_token( $parsed_value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$this->return       = $parsed_value;
		$this->pieces       = $pieces;
		$this->recipe_id    = $recipe_id;
		$this->trigger_data = $trigger_data;
		$this->user_id      = $user_id;
		$this->replace_args = $replace_args;

		$this->formula = $this->get_formula( $replace_args );

		$this->parsed_formula = $this->formula;
		$this->string_calc    = new \ChrisKonnertz\StringCalc\StringCalc();

		try {

			$this->calculate();

			$parsed_value = $this->get_result();

		} catch ( \Exception $e ) {
			$parsed_value = $e->getMessage();
		}

		return apply_filters( 'automator_calculation_token_output', $parsed_value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
	}
}
