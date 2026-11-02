<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Providers\Send_Email_Tests_Provider;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Providers\Send_Webhook_Tests_Provider;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Registry\Node_Tests_Registry;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Node_Test_Run_Store;

/**
 * Registers Free's launch node-test providers. Pro registers its own
 * providers from its bootstrap (Rule 14) — same registry, no filters.
 *
 * Free registers through the exact same `automator_node_tests_register`
 * extension surface Pro, addons and third parties use — one registration
 * story, not two. {@see self::register_free_providers()}.
 */
final class Node_Tests_Bootstrap {

	/**
	 * The send-webhook family — every action rendering the shared
	 * send-webhook field group (`Automator()->send_webhook->fields`), keyed
	 * integration code => action code. One provider serves them all; each
	 * member registers under its own catalog identity so its `has_tests`
	 * flips independently.
	 *
	 * @var array<string, string>
	 */
	private const SEND_WEBHOOK_FAMILY = array(
		'WEBHOOKS'      => 'WPSENDWEBHOOK',
		'ZAPIER'        => 'SENDWEBHOOK',
		'IFTTT'         => 'IFTTTSENDWEBHOOK',
		'MAKE'          => 'MAKESENDWEBHOOK',
		'INTEGROMAT'    => 'INTSENDWEBHOOK',
		'INTEGRATELY'   => 'INTEGRATELYSENDWEBHOOK',
		'AIRTABLE'      => 'AIRTABLESENDWEBHOOK',
		'KONNECTZ_IT'   => 'KONNECTZ_IT_SENDWEBHOOK',
		'PABBLYCONNECT' => 'PBCNCTSENDWEBHOOK',
	);

	/**
	 * Wire the third-party extension hook (which Free's own providers also
	 * register through — see {@see self::register_free_providers()}), and
	 * subscribe the write-time invalidation listener (spec Stage 3).
	 *
	 * @return void
	 */
	public static function register(): void {
		// Extension surface: fires once after all plugins load so Free, Pro,
		// addons and third parties can all register providers the same way.
		add_action( 'init', array( self::class, 'fire_registration_hook' ), 5 );
		add_action( 'automator_node_tests_register', array( self::class, 'register_free_providers' ) );

		// The update paths (item/block/block_modifier stores) fire this once
		// a save actually changes a field value (Rule 2's bootstrap
		// exception). Clearing the run store is the only listener — no
		// value comparison happens here, the paths already did it.
		add_action( 'automator_node_fields_changed', array( self::class, 'invalidate_node_tests' ), 10, 2 );
	}

	/**
	 * Fire the third-party registration hook. Listeners receive the
	 * registry class name and call register_class()/register() on it.
	 *
	 * @return void
	 */
	public static function fire_registration_hook(): void {
		Dispatcher::action( 'automator_node_tests_register', Node_Tests_Registry::class );
	}

	/**
	 * Register the Free launch providers, lazily as class-strings (boot
	 * stays autoload-cold) — the `automator_node_tests_register` listener
	 * this class subscribes itself to in {@see self::register()}. Free
	 * registers through the same surface Pro, addons and third parties do;
	 * there is exactly one way a provider ends up in the registry.
	 *
	 * @return void
	 */
	public static function register_free_providers(): void {
		foreach ( self::SEND_WEBHOOK_FAMILY as $integration_code => $action_code ) {
			Node_Tests_Registry::register_class( Send_Webhook_Tests_Provider::class, 'item', $integration_code, $action_code );
		}
		Node_Tests_Registry::register_class( Send_Email_Tests_Provider::class, 'item', 'EMAILS', 'SENDEMAIL' );
	}

	/**
	 * Drop the node's stored runs and armed marker — a save just changed
	 * one of its field values, so any run on record executed against
	 * values that no longer exist.
	 *
	 * @param string $kind    item | block | block_modifier.
	 * @param int    $node_id Node post id.
	 * @return void
	 */
	public static function invalidate_node_tests( string $kind, int $node_id ): void {
		( new Node_Test_Run_Store() )->clear_all( $kind, array( 'id' => $node_id ) );
	}
}
