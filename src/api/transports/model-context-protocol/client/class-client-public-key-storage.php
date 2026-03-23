<?php
/**
 * MCP public key storage helper.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

/**
 * Class Client_Public_Key_Storage
 *
 * Handles reading and writing the public key record from WordPress options.
 */
class Client_Public_Key_Storage {

	/**
	 * Option name used to persist the record.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Getter callback.
	 *
	 * @var callable
	 */
	private $getter;

	/**
	 * Setter callback.
	 *
	 * @var callable
	 */
	private $setter;

	/**
	 * Constructor.
	 *
	 * @param string|null   $option_name Option key.
	 * @param callable|null $getter      Option getter.
	 * @param callable|null $setter      Option setter.
	 */
	public function __construct( ?string $option_name = null, ?callable $getter = null, ?callable $setter = null ) {
		$this->option_name = $option_name ? $option_name : 'automator_mcp_public_key';
		$this->getter      = $getter ? $getter : 'automator_get_option';
		$this->setter      = $setter ? $setter : 'automator_update_option';
	}

	/**
	 * Load the stored record.
	 *
	 * @return Client_Public_Key_Record
	 */
	public function load(): Client_Public_Key_Record {
		$value = call_user_func( $this->getter, $this->option_name, array() );

		return Client_Public_Key_Record::from_array( is_array( $value ) ? $value : array() );
	}

	/**
	 * Persist the supplied record.
	 *
	 * @param Client_Public_Key_Record $record Record to persist.
	 * @return void
	 */
	public function save( Client_Public_Key_Record $record ): void {
		call_user_func( $this->setter, $this->option_name, $record->to_array(), false );
	}
}
