<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Integrations\Notion\Fields;

use Uncanny_Automator\Integrations\Notion\Notion_Helpers;

/**
 * @package Uncanny_Automator\Integrations\Notion\Fields
 */
class Mapper {

	/**
	 * @var array
	 */
	protected $notion_field_property = array();

	/**
	 * The automator field.
	 *
	 * @var array
	 */
	protected $field = array();

	/**
	 * List of not-supported fields.
	 *
	 * @var string[]
	 */
	protected static $not_supported_fields = array(
		'created_time',
		'created_by',
		'last_edited_by',
		'last_edited_time',
		'formula',
		'button',
		'relation',
		'rollup',
		'unique_id',
	);

	/**
	 * @return string[]
	 */
	public static function get_not_supported_fields() {
		return self::$not_supported_fields;
	}

	/**
	 * Accepts Notion properties.
	 *
	 * @param mixed[] $properties
	 *
	 * @return void
	 */
	public function set_properties( $notion_field_property ) {
		$this->notion_field_property = $notion_field_property;
	}

	/**
	 * Get the corresponding Automator field.
	 *
	 * @return array
	 */
	public function get_corresponding_field() {

		$field = $this->create_field()->render();

		return $field;
	}

	/**
	 * Generate a unique option code for the field based on Notion field properties.
	 *
	 * @return string
	 */
	protected function get_option_code() {

		$option_code = strtr(
			Notion_Helpers::get_option_code_field_string(),
			array(
				'{{ID}}'   => $this->notion_field_property['id'],
				'{{TYPE}}' => $this->notion_field_property['type'],
			)
		);

		return $option_code;
	}

	/**
	 * Get the label from Notion field properties.
	 *
	 * @return mixed
	 */
	protected function get_label() {
		return $this->notion_field_property['name'];
	}

	/**
	 * Create a field property.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	protected function create_field_property( $key, $value ) {
		$this->field[ $key ] = $value;
	}

	/**
	 * Generate a corresponding Automator input type from Notion field input type.
	 *
	 * @return self
	 */
	protected function create_field() {

		$props = $this->notion_field_property;

		$type = $props['type'];

		if ( self::is_field_not_supported( $type ) ) {
			return $this;
		}

		// Default input type is text.
		$this->create_field_property( 'input_type', 'text' );

		// Default input supports tokens.
		$this->create_field_property( 'supports_tokens', true );

		// Unique option code.
		$this->create_field_property( 'option_code', $this->get_option_code() );

		// Notion field name -> Automator label.
		$this->create_field_property( 'label', $this->get_label() );

		// Map the fields.
		$this->map_fields( $type );

		return $this;
	}

	/**
	 * Map fields based on the Notion field type.
	 *
	 * @param string $type
	 *
	 * @return void
	 */
	private function map_fields( $type ) {

		$callback_method = 'create_' . $type . '_field';

		if ( method_exists( self::class, $callback_method ) ) {
			return $this->$callback_method();
		}
	}

	/**
	 * Check if a field type is not supported.
	 *
	 * @param string $field_type
	 *
	 * @return bool
	 */
	public static function is_field_not_supported( $field_type ) {

		return in_array( $field_type, self::$not_supported_fields, true );
	}

	/**
	 * Create a read-only field.
	 *
	 * @return void
	 */
	protected function create_read_only_field() {
		$this->create_field_property( 'input_type', 'text' );
		$this->create_field_property( 'read_only', true );
		$this->create_field_property( 'placeholder', esc_html_x( 'Non-editable field', 'Notion', 'uncanny-automator' ) );
	}

	/**
	 * Create a select field.
	 *
	 * @return void
	 */
	protected function create_select_field() {

		$props = $this->notion_field_property;

		// Construct the select field for Automator.
		$options = self::create_default_option();

		// Access the status options, otherwise blank array.
		$notion_field_options = $props['select']['options'] ?? array();

		foreach ( $notion_field_options as $option ) {
			$options[] = array(
				'value' => $option['id'],
				'text'  => $option['name'],
			);
		}

		$this->create_field_property( 'supports_custom_value', false );
		$this->create_field_property( 'options_show_id', false );
		$this->create_field_property( 'input_type', 'select' );
		$this->create_field_property( 'options', $options );
	}

	/**
	 * Create a multi-select field.
	 *
	 * @return void
	 */
	protected function create_multi_select_field() {

		$props = $this->notion_field_property;

		// Construct the select field for Automator.
		$options = array();

		// Access the status options, otherwise blank array.
		$notion_field_options = $props['multi_select']['options'] ?? array();

		foreach ( $notion_field_options as $option ) {
			$options[] = array(
				'value' => $option['id'],
				'text'  => $option['name'],
			);
		}

		$this->create_field_property( 'supports_custom_value', false );
		$this->create_field_property( 'options_show_id', false );
		$this->create_field_property( 'supports_multiple_values', 'select' );
		$this->create_field_property( 'input_type', 'select' );
		$this->create_field_property( 'options', $options );
	}

	/**
	 * Create a files field.
	 *
	 * @return void
	 */
	protected function create_files_field() {
		$this->create_field_property( 'input_type', 'url' );
		$this->create_field_property( 'description', esc_html_x( 'Supports file paths that begin with http:// or https://', 'Notion', 'uncanny-automator' ) );
	}

	/**
	 * Create an email field.
	 *
	 * @return void
	 */
	protected function create_email_field() {
		$this->create_field_property( 'input_type', 'email' );
	}

	/**
	 * Create a date field.
	 *
	 * @return void
	 */
	protected function create_date_field() {
		$this->create_field_property( 'input_type', 'date' );
	}

	/**
	 * Create a title field.
	 *
	 * @return void
	 */
	protected function create_title_field() {
		// Default is text. This one is redundant but just to make it clear.
		$this->create_field_property( 'input_type', 'text' );
	}

	/**
	 * Create a URL field.
	 *
	 * @return void
	 */
	protected function create_url_field() {

		$this->create_field_property( 'input_type', 'url' );
		$this->create_field_property( 'placeholder', 'https://' );
	}

	/**
	 * Create a people field.
	 *
	 * @return void
	 */
	protected function create_people_field() {

		$ajax_config = array(
			'endpoint' => 'automator_notion_list_users',
			'event'    => 'search_options',
		);

		$persons_cached = get_transient( Notion_Helpers::PERSONS_TRANSIENT_KEY );

		$this->create_field_property( 'input_type', 'select' );
		$this->create_field_property( 'supports_multiple_values', true );
		$this->create_field_property( 'placeholder', esc_html_x( 'Click to select a person from the list', 'Notion', 'uncanny-automator' ) );
		$this->create_field_property( 'options_show_id', false );

		if ( false !== $persons_cached ) {
			// No need to send ajax request if it is cached already.
			$this->create_field_property( 'options', $persons_cached );
			return;
		}

		$this->create_field_property( 'options', array() );
		$this->create_field_property( 'ajax', $ajax_config );
	}

	/**
	 * Create a checkbox field.
	 *
	 * @return void
	 */
	protected function create_checkbox_field() {

		$props = $this->notion_field_property;

		$this->create_field_property( 'label', $props['name'] );
		$this->create_field_property( 'input_type', 'checkbox' );
	}

	/**
	 * Create a status field.
	 *
	 * @return void
	 */
	protected function create_status_field() {

		$props = $this->notion_field_property;

		// Construct the select field for Automator.
		$options = self::create_default_option();

		// Access the status options, otherwise blank array.
		$notion_field_options = $props['status']['options'] ?? array();

		foreach ( $notion_field_options as $option ) {
			$options[] = array(
				'value' => $option['id'],
				'text'  => $option['name'],
			);
		}

		$this->create_field_property( 'supports_custom_value', false );
		$this->create_field_property( 'options_show_id', false );
		$this->create_field_property( 'input_type', 'select' );
		$this->create_field_property( 'options', $options );
	}

	/**
	 * Render the field properties.
	 *
	 * @return array
	 */
	protected function render() {
		return $this->field;
	}

	/**
	 * Creates a default option for various select types.
	 *
	 * @return string[][]
	 */
	public static function create_default_option() {
		return array(
			array(
				'text'  => '-',
				'value' => '',
			),
		);
	}
}
