<?php

namespace Uncanny_Automator\Services\Recipe\Structure\Triggers\Tokens\Common;

/**
 * Represents a common token with properties.
 *
 * A common token contains metadata used for defining and identifying triggers.
 * This includes details such as the data type, display name, token type,
 * and a unique identifier (ID). Default values are provided for the
 * `data_type` and `token_type` properties to streamline instantiation.
 */
class Token {

	/**
	 * The data type of the token (e.g., 'text', 'int', 'date').
	 *
	 * @var string Default is 'text'.
	 */
	public $data_type = 'text';

	/**
	 * The display name of the token, typically shown to users.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The type of the token (e.g., 'trigger_common').
	 *
	 * @var string Default is 'trigger_common'.
	 */
	public $token_type = 'trigger_common';

	/**
	 * The unique identifier for the token.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Constructor to initialize the common token properties.
	 *
	 * @param string $name       The display name of the token.
	 * @param string $id         The unique identifier for the token.
	 * @param string $data_type  Optional. The data type of the token. Default 'text'.
	 * @param string $token_type Optional. The type of the token. Default 'trigger_common'.
	 */
	public function __construct( $name, $id, $data_type = null, $token_type = null ) {
		// Use default values for data_type and token_type if null.
		$this->data_type  = $data_type ?? $this->data_type;
		$this->name       = $name;
		$this->token_type = $token_type ?? $this->token_type;
		$this->id         = $id;
	}
}
