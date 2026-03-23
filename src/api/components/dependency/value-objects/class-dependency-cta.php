<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Dependency\Value_Objects;

use InvalidArgumentException;

/**
 * Dependency CTA (Call to Action) Value Object.
 *
 * Discriminated union by type:
 * - "link-open-and-wait": Opens URL in new window, frontend waits for event
 * - "link-external": Opens URL in new tab
 * - "fetch": Triggers backend REST call
 *
 * @since 7.0.0
 */
class Dependency_Cta {

	/**
	 * CTA type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * CTA icon.
	 *
	 * @var string|null
	 */
	private ?string $icon;

	/**
	 * CTA label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * CTA URL.
	 *
	 * @var string|null
	 */
	private ?string $url;

	/**
	 * Valid types and their required properties.
	 *
	 * @var array
	 */
	private array $valid_types = array(
		'link-open-and-wait' => array( 'label', 'url' ), // Opens a URL in a new window; frontend waits for an event.
		'link-external'      => array( 'label', 'url' ), // Opens a URL in a new tab.
		'fetch'              => array( 'label' ),        // Triggers a backend REST call, does not have a url property; it sends the CTA ID to the endpoint.
	);

	/**
	 * Constructor.
	 *
	 * @param array $data CTA data
	 *  @property string $type CTA type.
	 *  @property string|null $icon CTA icon.
	 *  @property string $label CTA label.
	 *  @property string|null $url CTA URL.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->type  = $data['type'];
		$this->icon  = $data['icon'] ?? null;
		$this->label = $data['label'];
		$this->url   = $data['url'] ?? null;
	}

	/**
	 * Get CTA type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get icon.
	 *
	 * @return string|null
	 */
	public function get_icon(): ?string {
		return $this->icon;
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get URL.
	 *
	 * @return string|null
	 */
	public function get_url(): ?string {
		return $this->url;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'type'  => $this->type,
			'label' => $this->label,
		);

		if ( null !== $this->icon ) {
			$data['icon'] = $this->icon;
		}

		if ( null !== $this->url ) {
			$data['url'] = $this->url;
		}

		return $data;
	}

	/**
	 * Validate CTA data.
	 *
	 * @param array $data CTA data to validate
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate( array $data ): void {

		if ( ! isset( $data['type'] ) || ! isset( $this->valid_types[ $data['type'] ] ) ) {
			throw new InvalidArgumentException( 'CTA type must be one of: ' . implode( ', ', array_keys( $this->valid_types ) ) );
		}

		$required_properties = $this->valid_types[ $data['type'] ];

		// Validate required properties based on type.
		foreach ( $required_properties as $property ) {
			if ( ! isset( $data[ $property ] ) || ! is_string( $data[ $property ] ) || empty( $data[ $property ] ) ) {
				throw new InvalidArgumentException( sprintf( 'CTA %s is required and must be a non-empty string', $property ) );
			}
		}

		// Validate optional icon property.
		if ( isset( $data['icon'] ) && null !== $data['icon'] && ! is_string( $data['icon'] ) ) {
			throw new InvalidArgumentException( 'CTA icon must be a string' );
		}
	}
}
