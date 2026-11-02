<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Default REST-namespace implementation for {@see Webhook_Handler_Interface}
 * implementers that want the legacy `uap/v2` namespace without writing out
 * the method body themselves.
 *
 * Usage:
 *
 *     class My_Integration_Handler implements Webhook_Handler_Interface {
 *         use Namespace_Default_Trait;
 *         // ...other interface methods
 *     }
 *
 * Platform controllers (Platform_Webhook_Controller,
 * Integrations_Webhook_Controller) do NOT use this trait — they implement
 * `get_rest_namespace()` directly to return `automator/platform/v1`.
 *
 * @since 8.0.0
 */
trait Namespace_Default_Trait {

	/**
	 * The legacy plugin-scoped REST namespace webhook handlers register under.
	 *
	 * @return string
	 */
	public function get_rest_namespace(): string {
		return 'uap/v2';
	}
}
