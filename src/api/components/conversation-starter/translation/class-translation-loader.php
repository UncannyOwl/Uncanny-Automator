<?php
/**
 * Loads bundled per-locale conversation starter overlays.
 *
 * Strings live as data in JSON files under registry/resources/i18n/, not as PHP source-code literals registered with gettext. This is a deliberate trade-off: it lets translations ship with the plugin. Do not "fix" this by reaching for __() / esc_html_x(); the choice is intentional.
 *
 * @package Uncanny_Automator\Api\Components\Conversation_Starter\Translation
 * @since 7.3
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Conversation_Starter\Translation;

/**
 * Reads `<resources_dir>/<language>.json` and applies its overlay to raw registry rows.
 */
class Translation_Loader {

	/**
	 * Directory containing per-language overlay files.
	 *
	 * @var string
	 */
	private string $resources_dir;

	/**
	 * Language code selected by Locale_Resolver.
	 *
	 * @var string
	 */
	private string $language;

	/**
	 * Cached overlay; null means "no overlay applies, return rows unchanged".
	 *
	 * @var array{pages:array<string,string>,rows:array<string,array<string,string>>}|null
	 */
	private ?array $overlay = null;

	/**
	 * Whether the overlay has been resolved (file read or determined missing).
	 *
	 * @var bool
	 */
	private bool $overlay_loaded = false;

	/**
	 * Constructor.
	 *
	 * @param string $resources_dir Directory containing `<language>.json` overlay files.
	 * @param string $language      Language code, or '' to disable translation.
	 */
	public function __construct( string $resources_dir, string $language ) {

		$this->resources_dir = rtrim( $resources_dir, '/' );
		$this->language      = $language;
	}

	/**
	 * Apply the locale overlay to a raw registry row, falling back per-key to the English source.
	 *
	 * @param array<string,mixed> $row Raw row from conversation-starters.json.
	 *
	 * @return array<string,mixed> Row with Page, Starter, and Prompt translated when available.
	 */
	public function translate_row( array $row ): array {

		$overlay = $this->get_overlay();

		if ( null === $overlay ) {
			return $row;
		}

		$page_key = isset( $row['Page'] ) && is_string( $row['Page'] ) ? $row['Page'] : '';
		if ( '' !== $page_key && isset( $overlay['pages'][ $page_key ] ) && is_string( $overlay['pages'][ $page_key ] ) ) {
			$row['Page'] = $overlay['pages'][ $page_key ];
		}

		$row_key = $this->build_row_key( $row, $page_key );
		if ( '' !== $row_key && isset( $overlay['rows'][ $row_key ] ) && is_array( $overlay['rows'][ $row_key ] ) ) {
			$translation = $overlay['rows'][ $row_key ];

			if ( isset( $translation['Starter'] ) && is_string( $translation['Starter'] ) ) {
				$row['Starter'] = $translation['Starter'];
			}

			if ( isset( $translation['Prompt'] ) && is_string( $translation['Prompt'] ) ) {
				$row['Prompt'] = $translation['Prompt'];
			}
		}

		return $row;
	}

	/**
	 * Build the stable row lookup key from the original English values.
	 *
	 * The Page lookup happens first and may have already replaced `$row['Page']` with its
	 * translation, so we receive the original English page key separately.
	 *
	 * @param array<string,mixed> $row             Raw row.
	 * @param string              $original_page   Original English Page value.
	 *
	 * @return string Composite key, or '' when no usable identifier exists.
	 */
	private function build_row_key( array $row, string $original_page ): string {

		$section = isset( $row['Section'] ) && is_string( $row['Section'] ) && '' !== $row['Section']
			? $row['Section']
			: 'Default';

		$page = '' !== $original_page ? $original_page : 'Default';

		$number = isset( $row['#'] ) && is_scalar( $row['#'] ) ? (string) $row['#'] : '';

		if ( '' === $number ) {
			return '';
		}

		return $section . '.' . $page . '.' . $number;
	}

	/**
	 * Resolve and cache the overlay for the current language.
	 *
	 * @return array{pages:array<string,string>,rows:array<string,array<string,string>>}|null
	 */
	private function get_overlay(): ?array {

		if ( $this->overlay_loaded ) {
			return $this->overlay;
		}

		$this->overlay_loaded = true;

		if ( '' === $this->language ) {
			$this->overlay = null;
			return $this->overlay;
		}

		$path = $this->resources_dir . '/' . $this->language . '.json';

		if ( ! is_readable( $path ) ) {
			$this->overlay = null;
			return $this->overlay;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $contents ) {
			$this->overlay = null;
			return $this->overlay;
		}

		$decoded = json_decode( $contents, true );

		if ( ! is_array( $decoded ) ) {
			$this->overlay = null;
			return $this->overlay;
		}

		$pages = isset( $decoded['pages'] ) && is_array( $decoded['pages'] ) ? $decoded['pages'] : array();
		$rows  = isset( $decoded['rows'] ) && is_array( $decoded['rows'] ) ? $decoded['rows'] : array();

		$this->overlay = array(
			'pages' => $pages,
			'rows'  => $rows,
		);

		return $this->overlay;
	}
}
