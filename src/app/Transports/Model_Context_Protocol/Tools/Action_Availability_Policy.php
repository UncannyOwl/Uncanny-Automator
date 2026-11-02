<?php
/**
 * Decides whether an MCP caller may use an Automator action.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Application\Sub_Tooling\Action_Executor;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;

/**
 * One rule for every MCP path that names an action: run, save, search and
 * component schema.
 *
 * Actions that install, update, activate or switch plugins and themes need
 * the owner's site management switch, for every caller, as site_manage does.
 * Actions that write custom fields without the meta API's slashing rules
 * cannot be added or run by any caller, who uses the post meta operation
 * instead. Recipes a person saved still run as saved.
 * Otherwise a first-party Uncanny Agent caller may use every action. Any other
 * caller is refused actions on the banned list, actions the site owner
 * switched off, and every action of an integration the site owner switched
 * off. The recipe builder and its REST routes never consult this policy.
 */
final class Action_Availability_Policy {

	/**
	 * Automator Pro's actions that change plugins and themes.
	 *
	 * @var string[]
	 */
	public const SITE_MANAGEMENT_ACTIONS = array(
		'WP_INSTALL_PLUGIN',
		'WP_UPDATE_PLUGIN',
		'WP_ACTIVATE_PLUGIN',
		'WP_DEACTIVATE_PLUGIN',
		'WP_SWITCH_THEME',
		'WPMU_NETWORK_ACTIVATE',
		'WPMU_NETWORK_DEACTIVATE',
	);

	/**
	 * Actions that write custom fields without wp_slash(), so stored backslashes
	 * are lost. No MCP caller may add or run them; recipes a person saved still
	 * run as saved.
	 *
	 * @var array<string, string> Action code => why it is refused.
	 */
	public const UNSAFE_POST_META_ACTIONS = array(
		'SETPOSTMETA'       => 'It writes custom fields without WordPress slashing, so backslashes in values such as JSON or file paths are lost, and serialized text can be stored serialized a second time.',
		'WP_DUPLICATE_POST' => 'It copies custom fields without WordPress slashing, so backslashes in copied values such as JSON or file paths are lost.',
		'WP_DUPLICATE_PAGE' => 'It copies custom fields without WordPress slashing, so backslashes in copied values such as JSON or file paths are lost.',
		'JETENGINE_SET_POST_META' => 'It writes custom fields without WordPress slashing, so backslashes in values such as JSON or file paths are lost.',
	);

	/**
	 * Fields of otherwise safe actions that write custom fields the same way.
	 *
	 * @var array<string, string> Action code => repeater option code.
	 */
	public const UNSAFE_POST_META_FIELDS = array(
		'CREATEPOST' => 'CPMETA_PAIRS',
	);

	/** Where callers change custom fields instead. */
	private const POST_META_OPERATION = 'Change custom fields with the post meta operation instead: rest_request GET /automator/v1/posts/{post_id}/meta to read keys and their hashes, then POST changes to the same path.';

	/**
	 * Site-owner switches.
	 *
	 * @var Action_Availability_Settings
	 */
	private Action_Availability_Settings $settings;

	/**
	 * Refuses fields that would give out administrator-level access.
	 *
	 * @var Account_Access_Policy
	 */
	private Account_Access_Policy $account_access;

	/**
	 * Registry used to resolve an action's integration when the caller does not know it.
	 *
	 * @var Action_Registry_Service|null
	 */
	private ?Action_Registry_Service $registry;

	/**
	 * @param Action_Availability_Settings|null $settings Switches; defaults to the stored option.
	 * @param Action_Registry_Service|null      $registry Registry; defaults to the shared instance.
	 */
	public function __construct( ?Action_Availability_Settings $settings = null, ?Action_Registry_Service $registry = null, ?Account_Access_Policy $account_access = null ) {
		$this->settings       = $settings ?? new Action_Availability_Settings();
		$this->registry       = $registry;
		$this->account_access = $account_access ?? new Account_Access_Policy();
	}

	/**
	 * Whether the caller may use the action.
	 *
	 * @param string      $action_code Action code.
	 * @param User_Context $context    Caller context.
	 * @param string|null $integration Integration code when already known; resolved from the registry otherwise.
	 * @return bool
	 */
	public function is_available( string $action_code, User_Context $context, ?string $integration = null ): bool {
		if ( null !== Account_Access_Policy::code_refusal( $action_code ) || self::needs_site_management( $action_code ) || isset( self::UNSAFE_POST_META_ACTIONS[ $action_code ] ) ) {
			return false;
		}

		if ( $context->is_first_party_caller() ) {
			return true;
		}

		if ( isset( Action_Executor::get_banned_actions()[ $action_code ] ) ) {
			return false;
		}

		if ( $this->settings->is_action_disabled( $action_code ) ) {
			return false;
		}

		$integration = null === $integration ? $this->resolve_integration( $action_code ) : $integration;

		return '' === $integration || ! $this->settings->is_integration_disabled( $integration );
	}

	/**
	 * Message returned to the caller when an action is refused.
	 *
	 * Tells the model why and what to do, in the same voice as the banned-action message.
	 *
	 * @param string $action_code Action code.
	 * @return string
	 */
	public function get_refusal_message( string $action_code ): string {
		$code_refusal = Account_Access_Policy::code_refusal( $action_code );
		if ( null !== $code_refusal ) {
			return $code_refusal;
		}
		if ( self::needs_site_management( $action_code ) ) {
			return sprintf(
				"Action '%s' changes the site's plugins or themes, which needs site management. It is off on this site. Ask the site owner to turn on Site management under Automator › Settings › MCP settings › Site tools, then use the site_manage tool.",
				$action_code
			);
		}

		if ( isset( self::UNSAFE_POST_META_ACTIONS[ $action_code ] ) ) {
			$copy = 'SETPOSTMETA' === $action_code ? '' : ' To copy a post, create it with its post type\'s REST route, then copy the custom fields you need.';
			return sprintf( "Action '%s' is not available to AI apps. %s %s%s", $action_code, self::UNSAFE_POST_META_ACTIONS[ $action_code ], self::POST_META_OPERATION, $copy );
		}

		$banned = Action_Executor::get_banned_actions();

		if ( isset( $banned[ $action_code ] ) ) {
			return sprintf(
				"Action '%s' is not available for AI execution. %s If you need to perform this operation, please guide the user to do it manually in WordPress admin or use a recipe instead.",
				$action_code,
				$banned[ $action_code ]
			);
		}

		return sprintf(
			"Action '%s' is switched off for AI apps on this site. Ask the site owner to turn it on under Automator › Settings › MCP settings.",
			$action_code
		);
	}

	/**
	 * Why an action's field values may not be saved or run, or null.
	 *
	 * Covers saved actions whose code was never re-checked, fields that write
	 * custom fields inside an otherwise safe action, and fields that would give
	 * out administrator-level access (Account_Access_Policy).
	 *
	 * @param string $action_code Action code.
	 * @param array  $fields      Field values keyed by option code.
	 * @param array  $saved       The action's saved field values, for an update.
	 * @return string|null
	 */
	public function field_refusal( string $action_code, array $fields, array $saved = array() ): ?string {
		if ( isset( self::UNSAFE_POST_META_ACTIONS[ $action_code ] ) ) {
			return $this->get_refusal_message( $action_code );
		}
		$field = self::UNSAFE_POST_META_FIELDS[ $action_code ] ?? null;
		if ( null !== $field && self::has_rows( $fields[ $field ] ?? null ) ) {
			return sprintf( "The %s field of action '%s' writes custom fields without WordPress slashing, so backslashes in values are lost. Leave it empty and run the action, then set the custom fields on the new post. %s", $field, $action_code, self::POST_META_OPERATION );
		}
		return $this->account_access->action_refusal( $action_code, $fields, $saved );
	}

	/**
	 * Drop search or listing items the caller may not use.
	 *
	 * Accepts both the search-result shape (code, integration_id) and the
	 * registry-definition shape (action_code, integration).
	 *
	 * @param array        $items   Action items.
	 * @param User_Context $context Caller context.
	 * @return array Re-indexed items the caller may use.
	 */
	public function filter_action_items( array $items, User_Context $context ): array {
		if ( $context->is_first_party_caller() ) {
			return array_values(
				array_filter(
					$items,
					static function ( $item ): bool {
						$code = is_array( $item ) ? (string) ( $item['code'] ?? $item['action_code'] ?? $item['name'] ?? '' ) : '';
						return null === Account_Access_Policy::code_refusal( $code ) && ! self::needs_site_management( $code ) && ! isset( self::UNSAFE_POST_META_ACTIONS[ $code ] );
					}
				)
			);
		}

		$kept = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$code        = (string) ( $item['code'] ?? $item['action_code'] ?? $item['name'] ?? '' );
			$integration = $item['integration_id'] ?? $item['integration'] ?? null;

			if ( '' === $code || $this->is_available( $code, $context, is_string( $integration ) ? $integration : null ) ) {
				$kept[] = $item;
			}
		}

		return $kept;
	}

	/**
	 * Resolve the integration an action belongs to.
	 *
	 * Unknown codes resolve to an empty string so the downstream path can
	 * report the action as unknown in its own words.
	 *
	 * @param string $action_code Action code.
	 * @return string
	 */
	private function resolve_integration( string $action_code ): string {
		$registry   = $this->registry ?? Action_Registry_Service::instance();
		$definition = $registry->get_action_definition( $action_code, false );

		if ( ! is_array( $definition ) ) {
			return '';
		}

		return (string) ( $definition['integration'] ?? '' );
	}

	/**
	 * Whether a repeater value, as JSON text or an array, holds any non-empty cell.
	 * Text that is not a JSON list counts as filled, so unreadable input is refused.
	 *
	 * @param mixed $value Repeater field value.
	 * @return bool
	 */
	private static function has_rows( $value ): bool {
		if ( null === $value || '' === $value ) {
			return false;
		}
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
			if ( ! is_array( $value ) ) {
				return true;
			}
		}
		if ( ! is_array( $value ) ) {
			return true;
		}
		foreach ( $value as $row ) {
			foreach ( is_array( $row ) ? $row : array( $row ) as $cell ) {
				if ( null !== $cell && ( ! is_scalar( $cell ) || '' !== trim( (string) $cell ) ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether an action changes plugins or themes while site management is off.
	 *
	 * @param string $action_code Action code.
	 * @return bool
	 */
	private static function needs_site_management( string $action_code ): bool {
		return in_array( $action_code, self::SITE_MANAGEMENT_ACTIONS, true ) && ! ( new Site_Management_Settings() )->is_enabled();
	}
}
