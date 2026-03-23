<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration;

use Uncanny_Automator\Api\Components\Token\Integration\Integration_Token_Config;
use Uncanny_Automator\Api\Components\Token\Integration\Value_Objects\Integration_Token_Code;
use Uncanny_Automator\Api\Components\Token\Integration\Value_Objects\Integration_Token_Name;
use Uncanny_Automator\Api\Components\Token\Integration\Value_Objects\Integration_Token_Data_Type;
use Uncanny_Automator\Api\Components\Token\Integration\Value_Objects\Integration_Token_Requires_User_Data;
use InvalidArgumentException;

/**
 * Integration Token Aggregate.
 *
 * Pure domain object representing an Automator integration token.
 * Contains zero WordPress dependencies - pure PHP business logic only.
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
class Integration_Token {

	/**
	 * The code of the token.
	 *
	 * @var Integration_Token_Code<string>
	 */
	private Integration_Token_Code $code;

	/**
	 * The human-readable display name for the token.
	 *
	 * @var Integration_Token_Name<string>
	 */
	private Integration_Token_Name $name;

	/**
	 * The data type of the token.
	 *
	 * @var Integration_Token_Data_Type<string>
	 */
	private Integration_Token_Data_Type $data_type;

	/**
	 * Whether the token requires user data.
	 *
	 * @var Integration_Token_Requires_User_Data<bool>
	 */
	private Integration_Token_Requires_User_Data $requires_user_data;

	/**
	 * Constructor.
	 *
	 * @param Integration_Token_Config $config Integration token configuration object.
	 *  @property Integration_Token_Code<string> $code Integration token code.
	 *  @property Integration_Token_Name<string> $name Integration token name.
	 *  @property Integration_Token_Data_Type<string> $data_type Integration token data type.
	 *  @property Integration_Token_Requires_User_Data<bool> $requires_user_data Integration token requires user data.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid configuration.
	 */
	public function __construct( Integration_Token_Config $config ) {

		// Use value objects to ensure data integrity on instance creation instead of runtime.
		// This way, once the instance is created, we can be sure it's valid.
		// Any invalid data will throw an exception here.
		// This also makes the class immutable after creation.
		// Any changes require creating a new instance with new data.
		$this->code               = new Integration_Token_Code( $config->get_code() );
		$this->name               = new Integration_Token_Name( $config->get_name() );
		$this->data_type          = new Integration_Token_Data_Type( $config->get_data_type() );
		$this->requires_user_data = new Integration_Token_Requires_User_Data( $config->get_requires_user_data() );
	}

	/**
	 * Get the token code.
	 *
	 * @return Integration_Token_Code
	 */
	public function get_code(): Integration_Token_Code {
		return $this->code;
	}

	/**
	 * Get the token name.
	 *
	 * @return Integration_Token_Name
	 */
	public function get_name(): Integration_Token_Name {
		return $this->name;
	}

	/**
	 * Get the token data type.
	 *
	 * @return Integration_Token_Data_Type
	 */
	public function get_data_type(): Integration_Token_Data_Type {
		return $this->data_type;
	}

	/**
	 * Get whether the token requires user data.
	 *
	 * @return Integration_Token_Requires_User_Data
	 */
	public function get_requires_user_data(): Integration_Token_Requires_User_Data {
		return $this->requires_user_data;
	}

	/**
	 * Convert to array for API responses and data transfer.
	 *
	 * @return array Token data as associative array.
	 */
	public function to_array(): array {
		return array(
			'code'               => $this->code->get_value(),
			'name'               => $this->name->get_value(),
			'data_type'          => $this->data_type->get_value(),
			'requires_user_data' => $this->requires_user_data->get_value(),
		);
	}
}
