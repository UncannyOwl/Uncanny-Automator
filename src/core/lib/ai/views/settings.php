<?php
namespace Uncanny_Automator\Core\Lib\AI\Views;

/**
 * Settings presentation data object.
 *
 * Holds display data for AI provider settings pages.
 * Used by template files for rendering.
 *
 * @since 5.6
 */
final class Settings {

	/**
	 * Settings page heading.
	 *
	 * @var string
	 */
	private $heading = '';

	/**
	 * Settings page subheading.
	 *
	 * @var string
	 */
	private $subheading = '';

	/**
	 * Settings page description.
	 *
	 * @var string
	 */
	private $description = '';

	/**
	 * Available trigger sentences.
	 *
	 * @var array<int,string>
	 */
	private $trigger_sentences = array();

	/**
	 * Available action sentences.
	 *
	 * @var array<int,string>
	 */
	private $action_sentences = array();

	/**
	 * Additional body content.
	 *
	 * @var string
	 */
	private $body = '';

	/**
	 * Initialize with settings data.
	 *
	 * @param array<string,mixed> $args Settings configuration
	 */
	public function __construct( $args ) {

		$subheading_default = sprintf(
			// translators: 1 the heading.
			esc_html_x( 'Use Uncanny Automator to connect with %1$s', 'AI Settings', 'uncanny-automator' ),
			$args['heading'] ?? ''
		);

		$this->heading           = (string) ( $args['heading'] ?? '' );
		$this->subheading        = (string) ( $args['subheading'] ?? $subheading_default );
		$this->description       = (string) ( $args['description'] ?? '' );
		$this->trigger_sentences = array_values( (array) ( $args['trigger_sentences'] ?? array() ) );
		$this->action_sentences  = array_values( (array) ( $args['action_sentences'] ?? array() ) );
		$this->body              = (string) ( $args['body'] ?? '' );
	}

	/**
	 * Get page heading.
	 *
	 * @return string Page heading
	 */
	public function get_heading() {
		return $this->heading;
	}

	/**
	 * Get page subheading.
	 *
	 * @return string Page subheading
	 */
	public function get_subheading() {
		return $this->subheading;
	}

	/**
	 * Get page description.
	 *
	 * @return string Page description
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get trigger sentences.
	 *
	 * @return array<int,string> Trigger sentences
	 */
	public function get_trigger_sentences() {
		return $this->trigger_sentences;
	}

	/**
	 * Get action sentences.
	 *
	 * @return array<int,string> Action sentences
	 */
	public function get_action_sentences() {
		return $this->action_sentences;
	}

	/**
	 * Get body content.
	 *
	 * @return string Body content
	 */
	public function get_body() {
		return $this->body;
	}
}
