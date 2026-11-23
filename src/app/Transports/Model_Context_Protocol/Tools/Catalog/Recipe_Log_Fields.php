<?php
/**
 * Fields exposed by the MCP recipe log list and run detail.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Log_Property_Redactor;

/**
 * Select fields before the MCP response is serialized as text or structured data.
 *
 * @since 8.1.0
 */
final class Recipe_Log_Fields {

	/** Operational columns returned by the built-in view and its fallback. */
	public const LIST_DEFAULTS = array(
		'recipe_log_id',
		'recipe_id',
		'automator_recipe_id',
		'recipe_title',
		'run_number',
		'log_number',
		'status',
		'completed',
		'recipe_completed',
		'date_time',
		'recipe_date_time',
	);

	/** Run summaries omit step data and free-form error messages. */
	public const DETAIL_DEFAULTS = array(
		'log_id',
		'recipe_id',
		'recipe_title',
		'run_number',
		'execution.status',
		'execution.date_started',
		'execution.date_ended',
	);

	/** What an email address recorded in a step reads as. */
	private const EMAIL_MARKER = '[email]';

	/**
	 * Query parameters whose value is a credential, such as a password reset key, or names the
	 * account it opens, such as the login a reset link carries.
	 */
	private const CREDENTIAL_PARAMETERS = 'key|token|access_token|refresh_token|nonce|_wpnonce|password|pass|pwd|code|signature|sig|secret|login|user_login|username';

	/** @return string[] Selectable list columns. */
	public static function list_fields(): array {
		return array_merge( self::LIST_DEFAULTS, array( 'user_id', 'user_email', 'display_name', 'meta' ) );
	}

	/** @return string[] Selectable detail fields, relative to log. */
	public static function detail_fields(): array {
		return array_merge(
			self::DETAIL_DEFAULTS,
			array( 'steps', 'flow', 'execution.errors', 'triggered_by_user.id', 'triggered_by_user.display_name', 'triggered_by_user.user_email' )
		);
	}

	/** @return string[] Names published in the input schema for both modes. */
	public static function all(): array {
		return array_values( array_unique( array_merge( self::list_fields(), self::detail_fields() ) ) );
	}

	/**
	 * Keep the recipe log's nested shape. An anonymous run has no triggering user.
	 *
	 * @param array $log Run detail from the log service.
	 * @param array $fields Validated detail fields.
	 * @return array|\stdClass Selected data, or an empty JSON object when no field has a value.
	 */
	public static function project_detail( array $log, array $fields ) {
		if ( in_array( 'triggered_by_user.user_email', $fields, true ) && isset( $log['triggered_by_user']['id'] ) ) {
			$user = get_userdata( $log['triggered_by_user']['id'] );
			if ( $user ) {
				$log['triggered_by_user']['user_email'] = $user->user_email;
			}
		}

		$selected = array();
		if ( in_array( 'steps', $fields, true ) ) {
			$selected['steps'] = isset( $log['flow'] ) && is_array( $log['flow'] ) ? self::steps( $log['flow'] ) : array();
		}
		foreach ( $fields as $field ) {
			if ( 'steps' === $field ) {
				continue;
			}
			$path = explode( '.', $field, 2 );
			if ( isset( $path[1] ) ) {
				if ( isset( $log[ $path[0] ][ $path[1] ] ) ) {
					$selected[ $path[0] ][ $path[1] ] = $log[ $path[0] ][ $path[1] ];
				}
			} elseif ( array_key_exists( $field, $log ) ) {
				$selected[ $field ] = $log[ $field ];
			}
		}

		// Steps and recorded errors hold what each run saw, not what the caller asked for.
		if ( isset( $selected['flow'] ) ) {
			$selected['flow'] = self::scrub( $selected['flow'] );
		}
		if ( isset( $selected['execution']['errors'] ) ) {
			$selected['execution']['errors'] = self::scrub( $selected['execution']['errors'] );
		} elseif ( in_array( 'execution.errors', $fields, true ) ) {
			// The run tree leaves errors out when none were recorded. A caller that asked must
			// be able to tell "none" from "not returned".
			$selected['execution']['errors'] = array();
		}

		return empty( $selected ) ? new \stdClass() : $selected;
	}

	/**
	 * Each trigger, action, block and condition of a run, in run order, without field values.
	 *
	 * What "why did this run fail" needs: where each step sits, its status and the
	 * message it recorded. flow carries the same steps with every configured and
	 * resolved field value, which is far larger and holds the values a run used.
	 *
	 * @since 8.1.0
	 *
	 * @param array $flow The run tree's flow.
	 * @return array<int,array<string,mixed>>
	 */
	private static function steps( array $flow ): array {
		$steps = array();
		foreach ( (array) ( $flow['entry']['triggers'] ?? array() ) as $trigger ) {
			self::collect_step( (array) $trigger, null, $steps );
		}
		foreach ( (array) ( $flow['content'] ?? array() ) as $node ) {
			self::collect_step( (array) $node, null, $steps );
		}
		return $steps;
	}

	/**
	 * Add one node, then whatever it contains, to the step list.
	 *
	 * @param array      $node  A wire node: item, block or block modifier.
	 * @param array|null $where The block and path it sits in, or null at the top level.
	 * @param array      $steps Steps so far.
	 * @return void
	 */
	private static function collect_step( array $node, ?array $where, array &$steps ): void {
		$type = (string) ( $node['type'] ?? '' );
		$step = array( 'id' => $node['id'] ?? null );

		if ( 'block' === $type ) {
			$step['type'] = (string) ( $node['block_type'] ?? 'block' );
		} elseif ( 'block_modifier' === $type ) {
			$step['type'] = (string) ( $node['block_modifier_type'] ?? 'condition' );
		} else {
			$step['type'] = (string) ( $node['item_type'] ?? 'item' );
		}
		foreach ( array( 'integration_code', 'code', 'custom_name' ) as $key ) {
			if ( isset( $node[ $key ] ) && '' !== $node[ $key ] ) {
				$step[ $key ] = $node[ $key ];
			}
		}
		if ( null !== $where ) {
			$step['in'] = $where;
		}

		$execution = (array) ( $node['execution'] ?? array() );
		if ( isset( $execution['status']['code'] ) ) {
			$step['status']       = $execution['status']['code'];
			$step['status_label'] = $execution['status']['label'] ?? $execution['status']['code'];
		}
		foreach ( array( 'date_started', 'date_ended', 'result_message', 'progress' ) as $key ) {
			if ( isset( $execution[ $key ] ) && '' !== $execution[ $key ] ) {
				$step[ $key ] = self::scrub( $execution[ $key ] );
			}
		}
		$steps[] = $step;

		if ( 'block' !== $type ) {
			return;
		}
		$block_id = $node['id'] ?? null;
		foreach ( (array) ( $node['modifiers'] ?? array() ) as $modifier ) {
			self::collect_step( (array) $modifier, array( 'block_id' => $block_id ), $steps );
		}
		foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
			$path  = (array) $path;
			$inner = array(
				'block_id'  => $block_id,
				'path_code' => (string) ( $path['path_code'] ?? '' ),
			);
			if ( isset( $path['execution']['status']['code'] ) ) {
				$inner['path_status'] = $path['execution']['status']['code'];
			}
			foreach ( (array) ( $path['content'] ?? array() ) as $child ) {
				self::collect_step( (array) $child, $inner, $steps );
			}
		}
	}

	/**
	 * Remove the values a run recorded about people and credentials.
	 *
	 * Token values (the user's name, email, role and password reset link) are
	 * dropped, credential query parameters are replaced, and email addresses
	 * become a placeholder, so a step still shows whether a value was empty.
	 * The recipe log screen reads the same tree without this.
	 *
	 * @since 8.1.0
	 *
	 * @param mixed $value Part of a run tree.
	 * @return mixed
	 */
	private static function scrub( $value ) {
		if ( is_string( $value ) ) {
			return self::scrub_text( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$clean = array();
		foreach ( $value as $key => $item ) {
			if ( 'tokens' === $key ) {
				continue;
			}
			$clean[ is_string( $key ) ? self::scrub_text( $key ) : $key ] = self::scrub( $item );
		}
		return $clean;
	}

	/**
	 * @param string $text Recorded text, such as a rendered email body.
	 * @return string
	 */
	private static function scrub_text( string $text ): string {
		$text = preg_replace(
			'/([?&](?:amp;|#038;)?(?:' . self::CREDENTIAL_PARAMETERS . ')=)[^&\s"\'<>#]+/i',
			'$1' . Log_Property_Redactor::MARKER,
			$text
		) ?? '';
		$text = preg_replace( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', self::EMAIL_MARKER, $text ) ?? '';
		// A recorded PHP error names files by their absolute server path; keep them relative to WordPress.
		return defined( 'ABSPATH' ) && '/' !== ABSPATH ? str_replace( ABSPATH, '', $text ) : $text;
	}
}
