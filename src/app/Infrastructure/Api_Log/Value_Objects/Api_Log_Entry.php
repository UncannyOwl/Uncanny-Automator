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
	 * The request params as a plain array (never an object: rows are read with allowed_classes => false).
	 *
	 * @var mixed
	 */
	private $params;

	/**
	 * The response data as a plain array (never an object: rows are read with allowed_classes => false).
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
	 * `params` and `response` are RAW arrays here, not encoded strings. The
	 * persistence adapter ({@see \Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Api_Log_Store::persist()})
	 * applies the `automator_log_api_responses` filter to the actual payload and
	 * then encodes both columns once. Serializing them here as well made the
	 * adapter compress a string, which decodes to an empty array: no row could
	 * be resent and the redaction below never ran.
	 *
	 * @return array
	 */
	public function to_row(): array {
		return array(
			'type'          => $this->type,
			'recipe_log_id' => $this->recipe_log_id,
			'item_log_id'   => $this->item_log_id,
			'endpoint'      => $this->endpoint,
			'params'        => $this->compact_params(),
			'request'       => '', // Kept for compat but we don't store raw request anymore.
			'response'      => $this->response,
			'status'        => $this->status,
			'price'         => $this->price,
			'balance'       => $this->balance,
			'time_spent'    => $this->time_spent,
		);
	}

	/**
	 * The replay payload as it is stored: the params array with credentials
	 * redacted. Anything that is not an array has no replayable shape and is
	 * stored as an empty one.
	 *
	 * @return array<string, mixed>
	 */
	private function compact_params(): array {

		return self::redact_secrets( is_array( $this->params ) ? $this->params : array() );
	}

	/**
	 * Redact credential-bearing keys before an outbound request body is
	 * persisted to uap_api_log. Legacy `/v2/{integration}` dispatches send
	 * third-party credentials in the body; the log store only gzip+base64s
	 * the row (reversible), so raw keys would sit recoverably at rest,
	 * readable by the admin API-log viewer and any DB/backup access.
	 *
	 * Targeted key allow-list (recursive) rather than whole-body redaction,
	 * so non-secret fields stay useful for debugging. Safe against replay:
	 * resend re-resolves the CURRENT credentials, it does not re-send the
	 * logged body.
	 *
	 * @param mixed $data Request body (array) or any nested value.
	 *
	 * @return mixed
	 */
	private static function redact_secrets( $data ) {

		if ( ! is_array( $data ) ) {
			return $data;
		}

		static $sensitive = array(
			'password',
			'passwd',
			'pwd',
			'secret',
			'client_secret',
			'api_secret',
			'app_secret',
			'token',
			'access_token',
			'refresh_token',
			'auth_token',
			'bearer_token',
			'api_key',
			'apikey',
			'app_key',
			'private_key',
			'authorization',
			'signing_secret',
		);

		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && in_array( strtolower( $key ), $sensitive, true ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			$out[ $key ] = is_array( $value ) ? self::redact_secrets( $value ) : $value;
		}

		return $out;
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
