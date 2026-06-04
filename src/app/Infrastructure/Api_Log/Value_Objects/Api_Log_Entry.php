<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Api_Log\Value_Objects;


/**
 * Class Api_Log_Entry
 *
 * Immutable value object representing a single API log entry. Maps directly
 * to the `uap_api_log` table columns. Lives in `components/api-log/` because
 * this is domain data, not infrastructure — the wpdb adapter that persists
 * it is in `database/stores/`.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Api_Log\Value_Objects
 */
final class Api_Log_Entry {

	/**
	 * The log type (e.g. 'action', 'trigger').
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The recipe log ID.
	 *
	 * @var int
	 */
	private $recipe_log_id;

	/**
	 * The item (action/trigger) log ID.
	 *
	 * @var int
	 */
	private $item_log_id;

	/**
	 * The API endpoint path.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * The request params (Api_Request or serializable).
	 *
	 * @var mixed
	 */
	private $params;

	/**
	 * The response data (Api_Response or serializable).
	 *
	 * @var mixed
	 */
	private $response;

	/**
	 * The HTTP status code.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * The credit price for this call, or null.
	 *
	 * @var float|null
	 */
	private $price;

	/**
	 * The remaining credit balance, or null.
	 *
	 * @var float|null
	 */
	private $balance;

	/**
	 * Time spent on the API call in milliseconds.
	 *
	 * @var int
	 */
	private $time_spent;

	/**
	 * Constructor.
	 *
	 * @param string     $type          The log type.
	 * @param int        $recipe_log_id The recipe log ID.
	 * @param int        $item_log_id   The item log ID.
	 * @param string     $endpoint      The API endpoint path.
	 * @param mixed      $params        The request params.
	 * @param mixed      $response      The response data.
	 * @param int        $status        The HTTP status code.
	 * @param float|null $price         The credit price or null.
	 * @param float|null $balance       The remaining balance or null.
	 * @param int        $time_spent    Time spent in milliseconds.
	 */
	public function __construct(
		string $type,
		int $recipe_log_id,
		int $item_log_id,
		string $endpoint,
		$params,
		$response,
		int $status,
		$price,
		$balance,
		int $time_spent
	) {
		$this->type          = $type;
		$this->recipe_log_id = $recipe_log_id;
		$this->item_log_id   = $item_log_id;
		$this->endpoint      = $endpoint;
		$this->params        = $params;
		$this->response      = $response;
		$this->status        = $status;
		$this->price         = $price;
		$this->balance       = $balance;
		$this->time_spent    = $time_spent;
	}

	/**
	 * Convert to an array matching the uap_api_log table columns.
	 *
	 * @return array
	 */
	public function to_row(): array {
		return array(
			'type'          => $this->type,
			'recipe_log_id' => $this->recipe_log_id,
			'item_log_id'   => $this->item_log_id,
			'endpoint'      => $this->endpoint,
			'params'        => maybe_serialize( $this->params ),
			'request'       => '', // Kept for compat but we don't store raw request anymore.
			'response'      => maybe_serialize( $this->response ),
			'status'        => $this->status,
			'price'         => $this->price,
			'balance'       => $this->balance,
			'time_spent'    => $this->time_spent,
		);
	}

	/**
	 * Get the wpdb format strings for each column in to_row().
	 *
	 * @return array
	 */
	public function format(): array {
		return array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' );
	}
}
