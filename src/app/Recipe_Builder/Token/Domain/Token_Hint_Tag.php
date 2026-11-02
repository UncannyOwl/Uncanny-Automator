<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Token\Domain;

use InvalidArgumentException;

/**
 * Per-token display hint tag.
 *
 * Emits the same wire array shape as the integration catalog's Scope_Tag but is
 * a standalone, dependency-resolver-free producer with a closed token-hint
 * scenario enum mirrored from the frontend. Attached inside a token's tags[]
 * (never on a flow node); the frontend rejects any scenario_id it does not
 * enumerate, so new scenarios need a coordinated FE enum change first.
 */
final class Token_Hint_Tag {

	/**
	 * Wire tag-type discriminator for token hints.
	 */
	const TYPE = 'token-hint';

	/**
	 * Scenario: the token echoes a trigger/action field value.
	 */
	const FIELD_VALUE = 'field-value';

	/**
	 * Scenario: the token's value may be absent at runtime.
	 */
	const MAY_BE_EMPTY = 'may-be-empty';

	/**
	 * Closed scenario enum mirrored from the frontend token-hint schema.
	 */
	const SCENARIOS = array( self::FIELD_VALUE, self::MAY_BE_EMPTY );

	/**
	 * Allowed tag colours (the frontend StyleColorStatus set).
	 */
	const COLORS = array( 'neutral', 'info', 'warning', 'error', 'success' );

	/**
	 * Frozen scenario id, one of self::SCENARIOS.
	 *
	 * @var string
	 */
	private string $scenario_id;

	/**
	 * Frozen non-empty display label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Optional Font Awesome icon code ('' when absent).
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * Optional colour from self::COLORS ('' when absent).
	 *
	 * @var string
	 */
	private string $color;

	/**
	 * Optional helper text ('' when absent).
	 *
	 * @var string
	 */
	private string $helper;

	/**
	 * Validate and freeze the tag.
	 *
	 * @param string $scenario_id One of self::SCENARIOS.
	 * @param string $label       Non-empty display label.
	 * @param string $icon        Optional Font Awesome icon code.
	 * @param string $color       Optional colour from self::COLORS.
	 * @param string $helper      Optional helper text.
	 *
	 * @throws InvalidArgumentException When the scenario, label, or color is invalid.
	 */
	public function __construct( string $scenario_id, string $label, string $icon = '', string $color = '', string $helper = '' ) {
		if ( ! in_array( $scenario_id, self::SCENARIOS, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain/ is WordPress-free (Rule 13); developer-facing message, never echoed.
			throw new InvalidArgumentException( 'Invalid token-hint scenario_id: ' . $scenario_id );
		}
		if ( '' === $label ) {
			throw new InvalidArgumentException( 'Token-hint label must not be empty.' );
		}
		if ( '' !== $color && ! in_array( $color, self::COLORS, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain/ is WordPress-free (Rule 13); developer-facing message, never echoed.
			throw new InvalidArgumentException( 'Invalid token-hint color: ' . $color );
		}
		$this->scenario_id = $scenario_id;
		$this->label       = $label;
		$this->icon        = $icon;
		$this->color       = $color;
		$this->helper      = $helper;
	}

	/**
	 * Serialize to the frontend tag wire shape (optional keys omitted when empty).
	 *
	 * @return array<string,string>
	 */
	public function to_array(): array {
		$data = array(
			'type'        => self::TYPE,
			'scenario_id' => $this->scenario_id,
			'label'       => $this->label,
		);
		if ( '' !== $this->icon ) {
			$data['icon'] = $this->icon;
		}
		if ( '' !== $this->color ) {
			$data['color'] = $this->color;
		}
		if ( '' !== $this->helper ) {
			$data['helper'] = $this->helper;
		}
		return $data;
	}
}
