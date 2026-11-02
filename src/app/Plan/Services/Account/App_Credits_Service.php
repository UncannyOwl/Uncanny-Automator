<?php
/**
 * App Credits Service
 *
 * @package Uncanny_Automator\App\Plan\Services\Account
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Services\Account;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Plan\Addons\Services\License_Summary_Service;
use WP_Error;

/**
 * The Dashboard's app credits meter, from a refreshed license.
 *
 * An active Pro license has unlimited credits, the same test the account
 * summary's plan uses. Every other license is metered by the platform's own
 * counts; `used` may exceed `limit`. A site that holds no license has no
 * meter at all, rather than an invented free allowance.
 */
final class App_Credits_Service {

	private Fresh_License_Reader $reader;

	private License_Summary_Service $summary;

	/**
	 * Constructor.
	 *
	 * @param License_Manager|null         $license License reader. Pass the one `$summary` reads.
	 * @param License_Summary_Service|null $summary Scenario resolver.
	 */
	public function __construct( ?License_Manager $license = null, ?License_Summary_Service $summary = null ) {
		$this->reader  = new Fresh_License_Reader( $license );
		$this->summary = $summary ?? License_Summary_Service::instance();
	}

	/**
	 * The meter, null when the site holds no license, or the reader's error.
	 *
	 * @return array{unlimited:bool,used:int,limit:int|null}|null|WP_Error
	 */
	public function get_meter() {
		$license = $this->reader->read();

		if ( ! is_array( $license ) ) {
			return $license;
		}

		$unlimited = $this->summary->get_scenario()->is_active_license();

		return array(
			'unlimited' => $unlimited,
			'used'      => max( 0, (int) ( $license['paid_usage_count'] ?? 0 ) ),
			'limit'     => $unlimited ? null : (int) ( $license['usage_limit'] ?? 0 ),
		);
	}
}
