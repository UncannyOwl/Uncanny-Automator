<?php
/**
 * MCP public key license provider.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\Api_Server;

/**
 * Class Client_Public_Key_License_Provider
 */
class Client_Public_Key_License_Provider {

	/**
	 * Get metadata required by the remote API.
	 *
	 * @return array<string,string>
	 */
	public function get_license_data(): array {

		$license_key = Api_Server::get_license_key();
		$item_name   = Api_Server::get_item_name();
		$site_name   = Api_Server::get_site_name();

		return array(
			'license_key' => $this->as_scalar_string( $license_key ),
			'item_name'   => $this->as_scalar_string( $item_name ),
			'site_name'   => $this->as_scalar_string( $site_name ),
		);
	}

	/**
	 * Casts the value to string if scalar
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function as_scalar_string( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return (string) $value;
	}
}
