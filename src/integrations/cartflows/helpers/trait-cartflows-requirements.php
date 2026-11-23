<?php

namespace Uncanny_Automator\Integrations\Cartflows;

/**
 * Trait Cartflows_Requirements
 *
 * What the CartFlows checkout items state: the WooCommerce integration, since CartFlows checkout steps are
 * WooCommerce checkouts and these items read a WooCommerce order.
 *
 * @package Uncanny_Automator
 *
 * @property Cartflows_Helpers $item_helpers
 *
 * @since 8.1.0
 */
trait Cartflows_Requirements {

	/**
	 * The integration a checkout item states through set_plugin_details().
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function woocommerce_plugin_details(): array {
		return array(
			'integration_required' => 'WC',
		);
	}

	/**
	 * The item's own WooCommerce check: it answers for the WooCommerce integration on a request that did not load it.
	 *
	 * @since 8.1.0
	 *
	 * @return bool|null Whether WooCommerce is loaded.
	 */
	protected function is_plugin_compatible(): ?bool {
		return $this->item_helpers->woocommerce_active();
	}
}
