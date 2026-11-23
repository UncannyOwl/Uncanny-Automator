<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Bridge;

use Uncanny_Automator\Automator_Load;
use Uncanny_Automator\Usage_Reports;

/**
 * Usage_Report_Bridge over the boot-time core Usage_Reports instance.
 *
 * @since 8.1.0
 */
final class Automator_Usage_Report_Bridge implements Usage_Report_Bridge {

	/**
	 * Record one event through Usage_Reports::record_event(), which applies
	 * the reporting switch, the per-event cap and the value sanitising.
	 *
	 * Records nothing when the core instance is missing: a second instance
	 * would register the report's schedule and send hooks again.
	 *
	 * @param string $event The event key.
	 * @param string $value The event value.
	 * @return void
	 */
	public function record_event( string $event, string $value ): void {
		$reports = Automator_Load::get_core_class_instance( 'Usage_Reports' );

		if ( $reports instanceof Usage_Reports ) {
			$reports->record_event( $event, $value );
		}
	}
}
