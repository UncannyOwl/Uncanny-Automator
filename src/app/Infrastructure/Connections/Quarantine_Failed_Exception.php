<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Thrown when the site-copy quarantine sweep cannot neutralize every inherited
 * connection — a SELECT or UPDATE against `uap_connections` errored, so the set
 * could not be quarantined atomically.
 *
 * Surfacing (rather than swallowing) the failure is load-bearing. The prior
 * code counted a row as quarantined even when `$wpdb->update()` returned false
 * and read an errored SELECT as "no rows to quarantine", so a copy whose cleanup
 * silently failed still called `complete()` and lifted the fail-closed block —
 * leaving the new site resolving a connection that belongs to its source.
 *
 * {@see Platform_Site_Registration::register_as_copy()} catches this and stays
 * fail-closed: it leaves the {@see Copy_Quarantine_State} block engaged and the
 * inherited rows untouched, so the copy can never resolve an inherited
 * connection. The next registration attempt resumes the sweep — the
 * already-quarantined rows are skipped by the status filter — and lifts the
 * block only once it lands cleanly.
 *
 * @since 8.0.0
 */
class Quarantine_Failed_Exception extends \Exception {
}
