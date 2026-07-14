<?php

namespace Uncanny_Automator\Integrations\Wp_Activity_Log;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Wp_Activity_Log_Helpers
 *
 * Shared logic for the WP Activity Log (formerly WP Security Audit Log)
 * integration. Every trigger fires from one upstream hook —
 * `wsal_logged_alert` — so the option builders and the token define/hydrate
 * pair live here and are reused by all five triggers (and by Pro's anonymous
 * variants + conditions via the composed Pro helper).
 *
 * @package Uncanny_Automator\Integrations\Wp_Activity_Log
 */
class Wp_Activity_Log_Helpers extends Abstract_Helpers {

	/**
	 * The "Any" sentinel value shared by every selectable dropdown.
	 *
	 * @var string
	 */
	const ANY = '-1';

	/**
	 * Event codes WSAL logs for a failed login attempt.
	 *
	 * 1002 = wrong password, 1003 = non-existing user. Filterable so sites that
	 * register additional failed-login codes (or third-party sensors) can extend
	 * the match without editing the integration.
	 *
	 * @return int[]
	 */
	public function failed_login_codes() {
		return apply_filters( 'automator_wp_activity_log_failed_login_codes', array( 1002, 1003 ) );
	}

	// =========================================================================
	// Remote-data handlers — every dropdown loads via REST.
	//
	// Resolved via REST: POST /wp-json/uap/v2/remote-data/wp_activity_log/{seg}.
	// Triggers consume the bare segment (incl. "Any X" → -1); actions /
	// conditions / loop filters consume the `_strict` segment (no "Any").
	// =========================================================================

	/**
	 * Remote-data handler: severity options for triggers (incl. "Any severity").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_severities( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_severity_options( true ) );
	}

	/**
	 * Remote-data handler: severity options without the "Any" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_severities_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_severity_options( false ) );
	}

	/**
	 * Remote-data handler: object-type options for triggers (incl. "Any").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_object_types( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_object_type_options( true ) );
	}

	/**
	 * Remote-data handler: object-type options without the "Any" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_object_types_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_object_type_options( false ) );
	}

	/**
	 * Remote-data handler: event-type options for triggers (incl. "Any").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_event_types( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_event_type_options( true ) );
	}

	/**
	 * Remote-data handler: event-type options without the "Any" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_event_types_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_event_type_options( false ) );
	}

	/**
	 * Severity option pairs (Critical/High/Medium/Low/Info), value = numeric code.
	 *
	 * @param bool $include_any Whether to prepend the "Any severity" sentinel.
	 *
	 * @return array
	 */
	private function build_severity_options( $include_any ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any severity', 'WP Activity Log', 'uncanny-automator' ),
			);
		}

		if ( ! $this->wsal_ready() ) {
			return $options;
		}

		foreach ( \WSAL\Controllers\Constants::get_severities_with_codes() as $code => $label ) {
			$options[] = array(
				'value' => (string) $code,
				'text'  => $label,
			);
		}

		return $options;
	}

	/**
	 * Object-type option pairs (User, Post, Plugin, …), value = the WSAL object key.
	 *
	 * @param bool $include_any Whether to prepend the "Any object type" sentinel.
	 *
	 * @return array
	 */
	private function build_object_type_options( $include_any ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any object type', 'WP Activity Log', 'uncanny-automator' ),
			);
		}

		if ( ! $this->wsal_ready() ) {
			return $options;
		}

		foreach ( \WSAL\Controllers\Alert_Manager::get_event_objects_data() as $key => $label ) {
			$options[] = array(
				'value' => (string) $key,
				'text'  => $label,
			);
		}

		return $options;
	}

	/**
	 * Event-type option pairs (Login, Created, Deleted, …), value = the WSAL type key.
	 *
	 * @param bool $include_any Whether to prepend the "Any event type" sentinel.
	 *
	 * @return array
	 */
	private function build_event_type_options( $include_any ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any event type', 'WP Activity Log', 'uncanny-automator' ),
			);
		}

		if ( ! $this->wsal_ready() ) {
			return $options;
		}

		foreach ( \WSAL\Controllers\Alert_Manager::get_event_type_data() as $key => $label ) {
			$options[] = array(
				'value' => (string) $key,
				'text'  => $label,
			);
		}

		return $options;
	}

	// =========================================================================
	// Remote-data handlers — the event-code picker (500+ codes → search).
	// =========================================================================

	/**
	 * Remote-data handler: search event codes for the {{An event}} trigger.
	 *
	 * Includes the "Any event" sentinel at the top when no query is typed.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_events( $request ): array {
		return $this->remote_data_success(
			$this->search_event_options( $request->get_search_query(), true )
		);
	}

	/**
	 * Remote-data handler: search event codes WITHOUT the "Any event" sentinel.
	 *
	 * For the Pro "Log a custom event" action, which must target one concrete
	 * registered alert code (a wildcard has no meaning when writing an event).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_events_strict( $request ): array {
		return $this->remote_data_success(
			$this->search_event_options( $request->get_search_query(), false )
		);
	}

	/**
	 * Build event-code option pairs filtered by a search query.
	 *
	 * Each option's value is the integer alert code; the text is
	 * "Code – Description" so users can find an event by either. Capped at 100
	 * results to keep the payload light for the 500+ registered codes.
	 *
	 * @param string $query       The user's typed search term.
	 * @param bool   $include_any Whether to prepend the "Any event" sentinel.
	 *
	 * @return array
	 */
	private function search_event_options( $query, $include_any ) {

		$options = array();
		$query   = trim( (string) $query );

		if ( true === $include_any && '' === $query ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any event', 'WP Activity Log', 'uncanny-automator' ),
			);
		}

		if ( ! $this->wsal_ready() ) {
			return $options;
		}

		$needle = strtolower( $query );
		$count  = 0;

		foreach ( \WSAL\Controllers\Alert_Manager::get_alerts() as $code => $alert ) {

			$desc = isset( $alert['desc'] ) ? (string) $alert['desc'] : '';

			if ( '' !== $needle ) {
				$haystack = strtolower( $code . ' ' . $desc );
				if ( false === strpos( $haystack, $needle ) ) {
					continue;
				}
			}

			$options[] = array(
				'value' => (string) $code,
				'text'  => '' !== $desc ? sprintf( '%1$s – %2$s', $code, $desc ) : (string) $code,
			);

			if ( ++$count >= 100 ) {
				break;
			}
		}

		return $options;
	}

	// =========================================================================
	// Shared token define / hydrate — reused by every trigger.
	// =========================================================================

	/**
	 * Token definitions shared by all WP Activity Log triggers.
	 *
	 * @return array
	 */
	public function get_event_tokens() {
		return array(
			array(
				'tokenId' => 'EVENT_ID',
				'tokenName' => esc_html_x( 'Event code', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId' => 'EVENT_DESCRIPTION',
				'tokenName' => esc_html_x( 'Event description', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_MESSAGE',
				'tokenName' => esc_html_x( 'Event message', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_SEVERITY',
				'tokenName' => esc_html_x( 'Event severity', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_SEVERITY_CODE',
				'tokenName' => esc_html_x( 'Event severity code', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId' => 'EVENT_OBJECT',
				'tokenName' => esc_html_x( 'Object type', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_OBJECT_KEY',
				'tokenName' => esc_html_x( 'Object type key', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_TYPE',
				'tokenName' => esc_html_x( 'Event type', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_TYPE_KEY',
				'tokenName' => esc_html_x( 'Event type key', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_USERNAME',
				'tokenName' => esc_html_x( 'Username', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId' => 'EVENT_USER_ROLES',
				'tokenName' => esc_html_x( 'User roles', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_CLIENT_IP',
				'tokenName' => esc_html_x( 'Client IP address', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_USER_AGENT',
				'tokenName' => esc_html_x( 'User agent', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId' => 'EVENT_POST_TYPE',
				'tokenName' => esc_html_x( 'Post type', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_POST_STATUS',
				'tokenName' => esc_html_x( 'Post status', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_DATE',
				'tokenName' => esc_html_x( 'Event date', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId' => 'EVENT_SITE_ID',
				'tokenName' => esc_html_x( 'Site ID', 'WP Activity Log', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate the shared token values from the `wsal_logged_alert` hook payload.
	 *
	 * Source signature (wp-security-audit-log/classes/Loggers/class-database-logger.php):
	 *   do_action( 'wsal_logged_alert', null, $type, $data, $date, $site_id )
	 * Arg 0 is always `null` (a placeholder for the alert object) — the real
	 * event code is arg 1 ($type). Every trigger therefore reads $type from
	 * $hook_args[1] and skips $hook_args[0].
	 *
	 * Always returns the full keyset (empty strings for absent values) so a
	 * recipe never resolves a partial token map.
	 *
	 * @param int   $type    The alert/event code.
	 * @param array $data    The enriched event data array.
	 * @param mixed $date    The event timestamp (float with microseconds).
	 * @param int   $site_id The site/blog ID.
	 *
	 * @return array
	 */
	public function hydrate_event_tokens( $type, $data, $date, $site_id ) {

		$data = is_array( $data ) ? $data : array();

		$severity_code = isset( $data['Severity'] ) ? (int) $data['Severity'] : 0;
		$object_key    = isset( $data['Object'] ) ? (string) $data['Object'] : '';
		$type_key      = isset( $data['EventType'] ) ? (string) $data['EventType'] : '';
		$roles         = isset( $data['CurrentUserRoles'] ) ? $data['CurrentUserRoles'] : array();

		return array(
			'EVENT_ID'            => (int) $type,
			'EVENT_DESCRIPTION'   => $this->get_event_description( $type ),
			'EVENT_MESSAGE'       => $this->get_event_message( $type, $data ),
			'EVENT_SEVERITY'      => $this->get_severity_label( $severity_code ),
			'EVENT_SEVERITY_CODE' => $severity_code,
			'EVENT_OBJECT'        => $this->get_object_label( $object_key ),
			'EVENT_OBJECT_KEY'    => $object_key,
			'EVENT_TYPE'          => $this->get_event_type_label( $type_key ),
			'EVENT_TYPE_KEY'      => $type_key,
			'EVENT_USERNAME'      => isset( $data['Username'] ) ? (string) $data['Username'] : '',
			'EVENT_USER_ID'       => isset( $data['CurrentUserID'] ) ? (int) $data['CurrentUserID'] : 0,
			'EVENT_USER_ROLES'    => is_array( $roles ) ? implode( ', ', $roles ) : (string) $roles,
			'EVENT_CLIENT_IP'     => isset( $data['ClientIP'] ) ? (string) $data['ClientIP'] : '',
			'EVENT_USER_AGENT'    => isset( $data['UserAgent'] ) ? (string) $data['UserAgent'] : '',
			'EVENT_POST_ID'       => isset( $data['PostID'] ) ? (int) $data['PostID'] : 0,
			'EVENT_POST_TYPE'     => isset( $data['PostType'] ) ? (string) $data['PostType'] : '',
			'EVENT_POST_STATUS'   => isset( $data['PostStatus'] ) ? (string) $data['PostStatus'] : '',
			'EVENT_DATE'          => $this->format_event_date( $date ),
			'EVENT_SITE_ID'       => (int) $site_id,
		);
	}

	// =========================================================================
	// Label resolvers — also consumed by Pro conditions for readable logs.
	// =========================================================================

	/**
	 * Human-readable severity label from its numeric code.
	 *
	 * @param int $code The severity code (200-500).
	 *
	 * @return string
	 */
	public function get_severity_label( $code ) {

		if ( empty( $code ) || ! $this->wsal_ready() ) {
			return '';
		}

		return (string) \WSAL\Controllers\Constants::get_severity_name_by_code( (int) $code );
	}

	/**
	 * Human-readable object-type label from its key.
	 *
	 * @param string $key The object key (e.g. 'post', 'user').
	 *
	 * @return string
	 */
	public function get_object_label( $key ) {

		if ( '' === (string) $key || ! $this->wsal_ready() ) {
			return '';
		}

		$label = \WSAL\Controllers\Alert_Manager::get_event_objects_data( $key );

		return is_string( $label ) ? $label : '';
	}

	/**
	 * Human-readable event-type label from its key.
	 *
	 * @param string $key The event-type key (e.g. 'login', 'created').
	 *
	 * @return string
	 */
	public function get_event_type_label( $key ) {

		if ( '' === (string) $key || ! $this->wsal_ready() ) {
			return '';
		}

		$label = \WSAL\Controllers\Alert_Manager::get_event_type_data( $key );

		return is_string( $label ) ? $label : '';
	}

	/**
	 * Short description of an event code, from its alert definition.
	 *
	 * @param int $type The alert/event code.
	 *
	 * @return string
	 */
	public function get_event_description( $type ) {

		if ( ! $this->wsal_ready() ) {
			return '';
		}

		$desc = \WSAL\Controllers\Alert_Manager::get_alert_property( (int) $type, 'desc' );

		return is_string( $desc ) ? $desc : '';
	}

	/**
	 * Rendered alert message for an event, falling back to the description.
	 *
	 * WSAL message strings are templates with `%Expression%` placeholders (e.g.
	 * "User %Username% logged in from %ClientIP%"). `Alert::get_message()`
	 * substitutes those placeholders from the event data array. The
	 * 'dashboard-widget' formatter context renders the message line only — no
	 * metadata/hyperlink blocks — and we strip HTML so the token resolves to
	 * clean plain text rather than the raw template.
	 *
	 * @param int   $type The alert/event code.
	 * @param array $data The event data array (placeholder substitution values).
	 *
	 * @return string
	 */
	public function get_event_message( $type, $data ) {

		if ( ! class_exists( '\WSAL\Controllers\Alert' ) ) {
			return $this->get_event_description( $type );
		}

		$meta_data = is_array( $data ) ? $data : array();
		$rendered  = \WSAL\Controllers\Alert::get_message( $meta_data, null, (int) $type, 0, 'dashboard-widget' );

		if ( ! is_string( $rendered ) || '' === $rendered ) {
			return $this->get_event_description( $type );
		}

		return trim( wp_strip_all_tags( $rendered ) );
	}

	/**
	 * Format the event timestamp using the site's date/time format.
	 *
	 * @param mixed $date The raw timestamp (float with microseconds).
	 *
	 * @return string
	 */
	private function format_event_date( $date ) {

		$timestamp = (int) floor( (float) $date );

		if ( $timestamp <= 0 ) {
			return '';
		}

		$format = get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' );

		return wp_date( $format, $timestamp );
	}

	/**
	 * Whether the WSAL controllers needed by this helper are loadable.
	 *
	 * @return bool
	 */
	private function wsal_ready() {
		return class_exists( '\WSAL\Controllers\Alert_Manager' ) && class_exists( '\WSAL\Controllers\Constants' );
	}
}
