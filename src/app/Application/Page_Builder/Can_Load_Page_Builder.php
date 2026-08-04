<?php
/**
 * Page Builder host availability use case.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Page_Builder;

/**
 * Decides if the host can load Page Builder.
 *
 * @since 7.5
 */
final class Can_Load_Page_Builder {

	/**
	 * Availability port.
	 *
	 * @var Page_Builder_Availability_Port
	 */
	private $availability;

	/**
	 * Create the use case.
	 *
	 * @param Page_Builder_Availability_Port $availability Availability port.
	 */
	public function __construct( Page_Builder_Availability_Port $availability ) {
		$this->availability = $availability;
	}

	/**
	 * Check if Page Builder can load.
	 *
	 * @return bool
	 */
	public function execute(): bool {
		return $this->availability->is_available();
	}
}
