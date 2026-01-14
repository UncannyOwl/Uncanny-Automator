<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits;

use WP_Post;

/**
 * Trait for recipe item-specific REST properties.
 *
 * Provides getters and setters for properties specific to recipe items
 * (triggers, actions, loop filters, filter conditions).
 *
 * @since 7.0
 */
trait Recipe_Item_Properties {

	/**
	 * The item type
	 *
	 * @var string
	 */
	private string $item_type = '';

	/**
	 * The item code
	 *
	 * @var string
	 */
	private string $item_code = '';

	/**
	 * The integration code
	 *
	 * @var string
	 */
	private string $integration_code = '';

	/**
	 * The item ID
	 *
	 * @var int|string|null
	 */
	private $item_id;

	/**
	 * The item post type
	 *
	 * Empty string for non-post-type items (e.g., filter conditions).
	 *
	 * @var string
	 */
	private string $item_post_type = '';

	/**
	 * The item WP_Post object (used during updates)
	 *
	 * @var WP_Post|null
	 */
	private ?WP_Post $item_post = null;

	/**
	 * Set the item type.
	 *
	 * @param string $item_type The item type.
	 *
	 * @return void
	 */
	protected function set_item_type( string $item_type ): void {
		$this->item_type = $item_type;
	}

	/**
	 * Set the item code.
	 *
	 * @param string $item_code The item code.
	 *
	 * @return void
	 */
	protected function set_item_code( string $item_code ): void {
		$this->item_code = $item_code;
	}

	/**
	 * Set item code from post meta.
	 *
	 * Uses the item_id property to fetch the 'code' meta value.
	 *
	 * @return void
	 */
	protected function set_item_code_from_meta(): void {
		$code = get_post_meta( (int) $this->get_item_id(), 'code', true );
		if ( ! empty( $code ) ) {
			$this->set_item_code( $code );
		}
	}

	/**
	 * Set the integration code.
	 *
	 * @param string $integration_code The integration code.
	 *
	 * @return void
	 */
	protected function set_integration_code( string $integration_code ): void {
		$this->integration_code = $integration_code;
	}

	/**
	 * Set integration code from post meta.
	 *
	 * Uses the item_id property to fetch the 'integration' meta value.
	 *
	 * @return void
	 */
	protected function set_integration_code_from_meta(): void {
		$integration = get_post_meta( (int) $this->get_item_id(), 'integration', true );
		if ( ! empty( $integration ) ) {
			$this->set_integration_code( $integration );
		}
	}

	/**
	 * Set the item ID.
	 *
	 * @param int|string|null $item_id The item ID.
	 *
	 * @return void
	 */
	protected function set_item_id( $item_id ): void {
		$this->item_id = $item_id;
	}

	/**
	 * Set the item post type.
	 *
	 * @return string
	 */
	protected function set_item_post_type( string $item_post_type ): void {
		$this->item_post_type = $item_post_type;
	}

	/**
	 * Set the item WP_Post object.
	 *
	 * @param WP_Post $item_post The item post object.
	 *
	 * @return void
	 */
	protected function set_item_post( WP_Post $item_post ): void {
		$this->item_post = $item_post;
	}

	/**
	 * Get item type.
	 *
	 * @return string
	 */
	protected function get_item_type(): string {
		return $this->item_type;
	}

	/**
	 * Get the item code.
	 *
	 * @return string
	 */
	protected function get_item_code(): string {
		return $this->item_code;
	}

	/**
	 * Get the integration code.
	 *
	 * @return string
	 */
	protected function get_integration_code(): string {
		return $this->integration_code;
	}

	/**
	 * Get the item ID.
	 *
	 * @return int|string|null
	 */
	protected function get_item_id() {
		return $this->item_id;
	}

	/**
	 * Get the item post type.
	 *
	 * @return string
	 */
	protected function get_item_post_type(): string {
		return $this->item_post_type;
	}

	/**
	 * Get the item WP_Post object.
	 *
	 * @return WP_Post|null
	 */
	protected function get_item_post(): ?WP_Post {
		return $this->item_post;
	}
}
