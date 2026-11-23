<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Bridge;

/**
 * Seam over the legacy \Uncanny_Automator\Usage_Reports recorder.
 *
 * @since 8.1.0
 */
interface Usage_Report_Bridge {

	/**
	 * Record one event for the next usage report. Records nothing when the
	 * site has reporting off or the event's bucket is full.
	 *
	 * @param string $event The event key, e.g. `template-library-search`.
	 * @param string $value The event value.
	 * @return void
	 */
	public function record_event( string $event, string $value ): void;
}
