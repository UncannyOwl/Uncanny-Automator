<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field;

use Uncanny_Automator\Api\Components\Field\Value_Objects\Field_Type;
use Uncanny_Automator\Api\Components\Field\Value_Objects\Field_Value;
use Uncanny_Automator\Api\Components\Field\Value_Objects\Field_Readable;
use Uncanny_Automator\Api\Components\Field\Value_Objects\Field_Custom;
use Uncanny_Automator\Api\Components\Field\Value_Objects\Field_Miscellaneous;

/**
 * Field Aggregate.
 *
 * Pure domain object representing a field instance.
 * Contains zero WordPress dependencies - pure PHP business logic only.
 * Validation happens in value object construction.
 *
 * @since 7.0
 */
class Field {

	/**
	 * Field code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Field type value object.
	 *
	 * @var Field_Type
	 */
	private Field_Type $type;

	/**
	 * Field value value object.
	 *
	 * @var Field_Value
	 */
	private Field_Value $value;

	/**
	 * Field readable value object.
	 *
	 * @var Field_Readable
	 */
	private Field_Readable $readable;

	/**
	 * Field custom value object.
	 *
	 * @var Field_Custom
	 */
	private Field_Custom $custom;

	/**
	 * Field miscellaneous value object.
	 *
	 * @var Field_Miscellaneous
	 */
	private Field_Miscellaneous $miscellaneous;

	/**
	 * Constructor.
	 *
	 * Creates VOs from config data - validation happens here.
	 * Once constructed, the Field instance is guaranteed to be valid.
	 *
	 * @param Field_Config $config Field configuration object.
	 *
	 * @return void
	 */
	public function __construct( Field_Config $config ) {
		// VO instantiation validates the data
		$this->code          = $config->get_code();
		$this->type          = new Field_Type( $config->get_type() );
		$this->value         = new Field_Value( $config->get_value() );
		$this->readable      = new Field_Readable( $config->get_readable() );
		$this->custom        = new Field_Custom( $config->get_custom() );
		$this->miscellaneous = new Field_Miscellaneous( $config->get_miscellaneous() );
	}

	/**
	 * Create from array.
	 *
	 * @param string $code Field code.
	 * @param array  $data Field data array.
	 *
	 * @return self
	 */
	public static function from_array( string $code, array $data ): self {
		return new self( Field_Config::from_array( $code, $data ) );
	}

	/**
	 * Get field code.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Get field type.
	 *
	 * @return Field_Type
	 */
	public function get_type(): Field_Type {
		return $this->type;
	}

	/**
	 * Get field value.
	 *
	 * @return Field_Value
	 */
	public function get_value(): Field_Value {
		return $this->value;
	}

	/**
	 * Get field readable.
	 *
	 * @return Field_Readable
	 */
	public function get_readable(): Field_Readable {
		return $this->readable;
	}

	/**
	 * Get field custom.
	 *
	 * @return Field_Custom
	 */
	public function get_custom(): Field_Custom {
		return $this->custom;
	}

	/**
	 * Get field miscellaneous.
	 *
	 * @return Field_Miscellaneous
	 */
	public function get_miscellaneous(): Field_Miscellaneous {
		return $this->miscellaneous;
	}

	/**
	 * Convert to array.
	 *
	 * Returns the field data as an associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'code'          => $this->code,
			'type'          => $this->type->get_value(),
			'value'         => $this->value->get_value(),
			'readable'      => $this->readable->get_value(),
			'custom'        => $this->custom->get_value(),
			'miscellaneous' => $this->miscellaneous->get_value(),
		);
	}
}
