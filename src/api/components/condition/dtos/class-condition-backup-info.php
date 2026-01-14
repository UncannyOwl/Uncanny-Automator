<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Dtos;

/**
 * Condition Backup Info DTO.
 *
 * Represents the UI artifact information for an individual condition.
 * This contains the "backup" data required for legacy compatibility
 * and UI rendering.
 *
 * @since 7.0.0
 */
class Condition_Backup_Info {

	private string $name_dynamic;
	private string $title_html;
	private string $integration_name;

	/**
	 * Constructor.
	 *
	 * @param string $name_dynamic Dynamic name template with tokens.
	 * @param string $title_html HTML representation for UI display.
	 * @param string $integration_name Human-readable integration name.
	 */
	public function __construct( string $name_dynamic, string $title_html, string $integration_name ) {
		$this->name_dynamic     = $name_dynamic;
		$this->title_html       = $title_html;
		$this->integration_name = $integration_name;
	}

	/**
	 * Create backup info with placeholder values.
	 *
	 * @param string $integration_name Integration name.
	 * @return self Backup info with placeholder values.
	 */
	public static function placeholder( string $integration_name ): self {
		return new self(
			'Condition placeholder - dynamic name will be generated',
			'<span class="uap-dynamic-sentence">Condition placeholder - HTML will be generated</span>',
			$integration_name
		);
	}

	/**
	 * Get the dynamic name template.
	 *
	 * @return string Dynamic name with token placeholders.
	 */
	public function get_name_dynamic(): string {
		return $this->name_dynamic;
	}

	/**
	 * Get the HTML title for UI display.
	 *
	 * @return string HTML representation of the condition.
	 */
	public function get_title_html(): string {
		return $this->title_html;
	}

	/**
	 * Get the integration name.
	 *
	 * @return string Human-readable integration name.
	 */
	public function get_integration_name(): string {
		return $this->integration_name;
	}

	/**
	 * Convert to array representation for storage.
	 *
	 * @return array Backup info as array matching legacy format.
	 */
	public function to_array(): array {
		return array(
			'nameDynamic'     => $this->name_dynamic,
			'titleHTML'       => $this->title_html,
			'integrationName' => $this->integration_name,
		);
	}

	/**
	 * Create from array (for hydration from storage).
	 *
	 * @param array $data Array data from storage.
	 * @return self Backup info instance.
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	public static function from_array( array $data ): self {
		$required_fields = array( 'nameDynamic', 'titleHTML', 'integrationName' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'Missing required backup field: %s', $field ) );
			}
		}

		return new self(
			$data['nameDynamic'],
			$data['titleHTML'],
			$data['integrationName']
		);
	}

	/**
	 * Update the dynamic name.
	 *
	 * @param string $name_dynamic New dynamic name.
	 * @return self New instance with updated dynamic name.
	 */
	public function with_name_dynamic( string $name_dynamic ): self {
		return new self( $name_dynamic, $this->title_html, $this->integration_name );
	}

	/**
	 * Update the HTML title.
	 *
	 * @param string $title_html New HTML title.
	 * @return self New instance with updated HTML title.
	 */
	public function with_title_html( string $title_html ): self {
		return new self( $this->name_dynamic, $title_html, $this->integration_name );
	}

	/**
	 * Update the integration name.
	 *
	 * @param string $integration_name New integration name.
	 * @return self New instance with updated integration name.
	 */
	public function with_integration_name( string $integration_name ): self {
		return new self( $this->name_dynamic, $this->title_html, $integration_name );
	}
}
