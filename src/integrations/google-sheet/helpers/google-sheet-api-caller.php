<?php
namespace Uncanny_Automator\Integrations\Google_Sheet;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Google Sheet API Caller
 *
 * @package Uncanny_Automator\Integrations\Google_Sheet
 * @since 5.0
 */
class Google_Sheet_Api_Caller extends Api_Caller {

	//
	// Abstract overrides
	//

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override the credential request key until migration to the vault.
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Check for errors.
	 *
	 * @param array $response The response array.
	 * @param array $args The arguments.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		$code = $response['statusCode'] ?? 0;
		if ( 200 !== $code ) {
			// TODO: Why aren't we showing the actual error message?
			throw new Exception(
				sprintf(
					// translators: %s: API endpoint
					esc_html_x( '%s failed', 'Google Sheet', 'uncanny-automator' ),
					esc_html( $this->get_api_endpoint() )
				)
			);
		}
	}

	//
	// Integration specific methods
	//

	/**
	 * API call to get user info.
	 *
	 * @return array|void
	 * @throws Exception
	 */
	public function get_user_info() {
		// Validate the scope.
		$client = $this->helpers->get_credentials();
		$scope  = $client['scope'] ?? '';

		if ( empty( $scope ) ) {
			return;
		}

		$info_scope  = $this->helpers->get_const( 'SCOPE_USERINFO' );
		$email_scope = $this->helpers->get_const( 'SCOPE_USER_EMAIL' );

		if ( ! ( strpos( $scope, $info_scope ) || strpos( $scope, $email_scope ) ) ) {
			return;
		}

		return $this->api_request( 'user_info' );
	}

	/**
	 * Get worksheets from spreadsheet.
	 *
	 * @param string $spreadsheet_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_worksheets( $spreadsheet_id ) {
		return $this->api_request(
			array(
				'action'         => 'get_worksheets',
				'spreadsheet_id' => $spreadsheet_id,
			)
		);
	}

	/**
	 * API call to get rows.
	 *
	 * @param string $spreadsheet_id
	 * @param string $worksheet_id
	 *
	 * @return object|array
	 * @throws Exception
	 */
	public function get_rows( $spreadsheet_id, $worksheet_id ) {
		$options = array();

		try {
			$body = array(
				'action'         => 'get_rows',
				'spreadsheet_id' => $spreadsheet_id,
				'worksheet_id'   => $worksheet_id,
			);

			$api_response = $this->api_request( $body );

			if ( is_array( $api_response['data'] ) ) {
				$alphas = range( 'A', 'Z' );

				if ( ! empty( $api_response['data'][0] ) ) {
					foreach ( $api_response['data'][0] as $key => $heading ) {
						if ( empty( $heading ) ) {
							$heading = 'COLUMN:' . $alphas[ $key ];
						}
						$options[] = array(
							'key'  => $heading,
							'type' => 'text',
							'data' => $heading,
						);
					}

					$response = (object) array(
						'success' => true,
						'samples' => array( $options ),
					);
				}
			}
		} catch ( Exception $e ) {
			$response = (object) array(
				'success' => false,
				'error'   => 'Error: Couldn\'t fetch rows. ' . $e->getMessage(),
			);
		}

		return $response;
	}

	/**
	 * Get columns for a worksheet.
	 *
	 * @param string $spreadsheet_id
	 * @param string $worksheet_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_columns( $spreadsheet_id, $worksheet_id ) {
		$response = $this->get_rows( $spreadsheet_id, $worksheet_id );
		$fields   = array();

		if ( ! empty( $response ) && ! empty( $response->samples ) ) {
			$rows = array_shift( $response->samples );
			foreach ( $rows as $index => $r ) {
				$num2alpha = sprintf( '1-%1$s2:%1$s', $this->num2alpha( $index ) );
				$fields[]  = array(
					'value' => $num2alpha,
					'text'  => $r['key'],
				);
			}
		}

		return $fields;
	}

	/**
	 * Convert number to alpha.
	 *
	 * @param int $n
	 *
	 * @return string
	 */
	private function num2alpha( $n ) {
		for ( $r = ''; $n >= 0; $n = intval( $n / 26 ) - 1 ) {
			$r = chr( $n % 26 + 0x41 ) . $r;
		}

		return $r;
	}

	/**
	 * Append a row to a worksheet.
	 *
	 * @param string $spreadsheet_id
	 * @param string $worksheet_id
	 * @param array  $row_data
	 * @param array  $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function append_row( $spreadsheet_id, $worksheet_id, $row_data, $action_data ) {

		$body = array(
			'action'         => 'append_row',
			'spreadsheet_id' => $spreadsheet_id,
			'worksheet_id'   => $worksheet_id,
			'key_values'     => $row_data,
		);

		return $this->api_request( $body, $action_data );
	}
}
