<?php
/**
 * Fresh License Reader
 *
 * @package Uncanny_Automator\App\Plan\Services\Account
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Services\Account;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use WP_Error;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;
use function Uncanny_Automator\App\Infrastructure\automator_platform_client;

/**
 * The license the Dashboard reads: refreshed from the platform on every read
 * unless a recent failure is backing off.
 *
 * While the platform cannot answer, the last good license stands in. With
 * none cached, the read is a retryable error, never "no license": the site
 * holds a key, so reporting it unlinked would be untrue.
 */
final class Fresh_License_Reader {

	private License_Manager $license;

	/**
	 * Constructor.
	 *
	 * @param License_Manager|null $license License reader; the shared one by default.
	 */
	public function __construct( ?License_Manager $license = null ) {
		if ( null === $license ) {
			// The shared license manager refreshes only once the platform client is wired into it.
			automator_platform_client();
		}
		$this->license = $license ?? automator_license_manager();
	}

	/**
	 * The license reader this reads through.
	 */
	public function license_manager(): License_Manager {
		return $this->license;
	}

	/**
	 * The refreshed license, null when the site holds none, or a 503 error
	 * with `retry_after` when the platform cannot answer and none is cached.
	 *
	 * @return array<string,mixed>|null|WP_Error
	 */
	public function read() {
		$license = $this->license->get_fresh_license_data();

		if ( null === $license && '' !== $this->license->get_key() && $this->license->is_platform_unreachable() ) {
			return new WP_Error(
				'license_unavailable',
				'Your Automator account details could not be loaded. Try again in a minute.',
				array(
					'status'      => 503,
					'retry_after' => MINUTE_IN_SECONDS,
				)
			);
		}

		return $license;
	}
}
