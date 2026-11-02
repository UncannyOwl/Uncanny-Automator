<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

/**
 * Checks hosted site-read replies before anything uses them.
 *
 * A reply with a missing key, a wrong type or a value outside its list is
 * refused whole (null), so the Dashboard fails closed. An app's `brand` is the
 * one exception: it only picks an icon, so a bad one reads as null and the app
 * stays listed. Only the documented keys
 * are copied. Text the AI or its owner chose (app names, summaries) becomes
 * plain text: control and invisible formatting characters are removed, so a
 * name cannot reorder itself on screen, and its length is capped.
 */
final class Hosted_Read_Schema {

	public const VENDORS = array( 'claude', 'chatgpt', 'gemini', 'other' );

	public const OUTCOMES = array( 'success', 'tool_error', 'access_denied', 'allowance_denied', 'unconfirmed' );

	public const MAX_APPS = 500;

	private const MAX_ENTRIES = 100;

	private const NAME_LENGTH = 120;

	private const SUMMARY_LENGTH = 1000;

	/** An icon brand slug, in bytes. */
	private const BRAND_LENGTH = 64;

	/** The hosted text sort key, in bytes: never shown, only compared. */
	private const SORT_KEY_LENGTH = 1024;

	/**
	 * Apps bound to the connection.
	 *
	 * @return list<array{id:string,name:string,vendor:string,brand:?string,last_used_at:?string}>|null
	 */
	public function apps( array $reply ): ?array {
		$apps = $reply['apps'] ?? null;
		if ( ! $this->is_list( $apps ) || count( $apps ) > self::MAX_APPS ) {
			return null;
		}

		$clean = array();
		foreach ( $apps as $app ) {
			$entry = $this->app( $app );
			if ( null === $entry || ! array_key_exists( 'last_used_at', $app ) ) {
				return null;
			}
			$entry['last_used_at'] = null === $app['last_used_at'] ? null : $this->time( $app['last_used_at'] );
			if ( null !== $app['last_used_at'] && null === $entry['last_used_at'] ) {
				return null;
			}
			$clean[] = $entry;
		}

		return $clean;
	}

	/**
	 * One page of activity.
	 *
	 * @return array{entries:list<array<string,mixed>>,total:int}|null
	 */
	public function activity( array $reply ): ?array {
		$entries = $reply['entries'] ?? null;
		$total   = $reply['total'] ?? null;
		if ( ! $this->is_list( $entries ) || count( $entries ) > self::MAX_ENTRIES || ! is_int( $total ) || $total < 0 ) {
			return null;
		}

		$clean = array();
		foreach ( $entries as $entry ) {
			$row = $this->entry( $entry );
			if ( null === $row ) {
				return null;
			}
			$clean[] = $row;
		}

		return array(
			'entries' => $clean,
			'total'   => $total,
		);
	}

	/**
	 * The account's AI tool use allowance.
	 *
	 * @return array{plan:string,used:int,limit:?int,resets_at:?string}|null
	 */
	public function usage( array $reply ): ?array {
		$plan      = $reply['plan'] ?? null;
		$used      = $reply['used'] ?? null;
		$limit     = $reply['limit'] ?? null;
		$resets_at = $reply['resets_at'] ?? null;
		if (
			! in_array( $plan, array( 'lite', 'paid' ), true )
			|| ! is_int( $used ) || $used < 0
			|| ! ( null === $limit || ( is_int( $limit ) && $limit >= 0 ) )
			|| ! array_key_exists( 'resets_at', $reply )
		) {
			return null;
		}

		$resets = null === $resets_at ? null : $this->time( $resets_at );
		if ( null !== $resets_at && null === $resets ) {
			return null;
		}

		return array(
			'plan'      => $plan,
			'used'      => $used,
			'limit'     => $limit,
			'resets_at' => $resets,
		);
	}

	/**
	 * One activity entry, or null.
	 *
	 * @param mixed $entry Decoded entry.
	 */
	private function entry( $entry ): ?array {
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$id          = $this->identifier( $entry['id'] ?? null );
		$app         = $this->app( $entry['app'] ?? null );
		$tool_name   = $entry['tool_name'] ?? null;
		$summary     = $this->text( $entry['summary'] ?? null, self::SUMMARY_LENGTH );
		$occurred_at = $this->time( $entry['occurred_at'] ?? null );
		$sort_at     = $this->precise_time( $entry['occurred_at'] ?? null );
		$sort_key    = $entry['sort_key'] ?? null;
		if (
			null === $id || null === $app || null === $summary || null === $occurred_at || null === $sort_at
			|| ! is_string( $tool_name ) || 1 !== preg_match( '/^[A-Za-z0-9_.:-]{1,100}$/D', $tool_name )
			|| ! is_bool( $entry['intent_reported'] ?? null )
			|| ! in_array( $entry['outcome'] ?? null, self::OUTCOMES, true )
			|| ! array_key_exists( 'sort_key', $entry )
			|| ( null !== $sort_key && ( ! is_string( $sort_key ) || strlen( $sort_key ) > self::SORT_KEY_LENGTH ) )
		) {
			return null;
		}

		return array(
			'id'              => $id,
			'app'             => $app,
			'tool_name'       => $tool_name,
			'summary'         => $summary,
			'intent_reported' => $entry['intent_reported'],
			'outcome'         => $entry['outcome'],
			'occurred_at'     => $occurred_at,
			'sort_at'         => $sort_at,
			'sort_key'        => $sort_key,
		);
	}

	/**
	 * An app's identity, or null.
	 *
	 * @param mixed $app Decoded app.
	 *
	 * @return array{id:string,name:string,vendor:string,brand:?string}|null
	 */
	private function app( $app ): ?array {
		if ( ! is_array( $app ) ) {
			return null;
		}

		$id     = $this->identifier( $app['id'] ?? null );
		$name   = $this->text( $app['name'] ?? null, self::NAME_LENGTH );
		$vendor = $app['vendor'] ?? null;

		return null === $id || null === $name || ! in_array( $vendor, self::VENDORS, true )
			? null
			: array(
				'id'     => $id,
				'name'   => $name,
				'vendor' => $vendor,
				'brand'  => $this->brand( $app['brand'] ?? null ),
			);
	}

	/**
	 * The icon brand the hosted service picked from the app's registered name.
	 * It only picks the icon, so a missing or malformed brand leaves the app
	 * listed without one instead of dropping it.
	 *
	 * @param mixed $brand The reported brand.
	 */
	private function brand( $brand ): ?string {
		return is_string( $brand ) && strlen( $brand ) <= self::BRAND_LENGTH && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $brand )
			? $brand
			: null;
	}

	/**
	 * A hosted identifier as a string, or null.
	 *
	 * @param mixed $value Decoded value.
	 */
	private function identifier( $value ): ?string {
		if ( is_int( $value ) && $value > 0 ) {
			return (string) $value;
		}

		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/D', $value ) ? $value : null;
	}

	/**
	 * Plain single-line text, capped, or null when the value is not a string.
	 *
	 * @param mixed $value Decoded value.
	 */
	private function text( $value, int $length ): ?string {
		if ( ! is_string( $value ) || ( '' !== $value && '' === wp_check_invalid_utf8( $value ) ) ) {
			return null;
		}

		// Control characters become spaces; format characters (bidirectional
		// overrides, zero-width marks) are removed so text cannot disguise itself.
		$value = (string) preg_replace( '/\p{Cc}+/u', ' ', $value );
		$value = (string) preg_replace( '/\p{Cf}+/u', '', $value );
		$value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );

		return mb_substr( $value, 0, $length );
	}

	/**
	 * An ISO 8601 instant with an offset, returned in UTC, or null.
	 *
	 * @param mixed $value Decoded value.
	 */
	private function time( $value ): ?string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/D', $value ) ) {
			return null;
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? null : gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
	}

	/**
	 * A valid time as UTC with exactly six decimal places, or null. Only the
	 * merge compares it: the fixed width keeps string order chronological and
	 * keeps the fraction the hosted query ordered by.
	 *
	 * @param mixed $value Decoded value.
	 */
	private function precise_time( $value ): ?string {
		if ( null === $this->time( $value ) ) {
			return null;
		}

		try {
			return ( new \DateTimeImmutable( $value ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\\TH:i:s.u\\Z' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Whether a value is a JSON array (a list), not an object.
	 *
	 * @param mixed $value Decoded value.
	 */
	private function is_list( $value ): bool {
		return is_array( $value ) && array_values( $value ) === $value;
	}
}
