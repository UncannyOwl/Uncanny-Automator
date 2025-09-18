<?php

namespace Uncanny_Automator\Settings;

/**
 * Trait for premium integration templating helper methods
 *
 * This trait contains helper methods that can be shared between
 * free and Pro settings classes without the page rendering complexity.
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Templating_Helpers {

	/**
	 * Output a panel separator
	 *
	 * @param string|array $classes Additional CSS classes to add. Can be a string or array of classes.
	 * @return void
	 */
	public function output_panel_separator( $classes = '' ) {
		$class_string = $this->build_css_class_string( 'uap-settings-panel-content-separator', $classes );
		?>
		<div class="<?php echo esc_attr( $class_string ); ?>"></div>
		<?php
	}

	/**
	 * Output a settings table using the uo-settings-table component
	 *
	 * @param array $columns The columns to display
	 * @param array $data The data to display
	 * @param string $layout The layout to use for the table
	 * @param mixed  $show_headings Whether to show the headings. If null, the headings will default to true for table layout only.
	 * @param array $queue_config The queue configuration.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_settings_table( $columns, $data, $layout = 'table', $show_headings = null, $queue_config = array() ) {
		$show_headings = is_null( $show_headings ) ? 'table' === $layout : $show_headings;
		$queue_config  = is_array( $queue_config ) && ! empty( $queue_config ) ? wp_json_encode( $queue_config ) : '';
		?>
		<div id="uap-settings-table">
			<uap-app-integration-settings-list 
				columns='<?php echo esc_attr( wp_json_encode( $columns ) ); ?>'
				data='<?php echo esc_attr( wp_json_encode( $data ) ); ?>'
				layout='<?php echo esc_attr( $layout ); ?>'
				show-headings="<?php echo $show_headings ? 'yes' : 'no'; ?>"
				queue='<?php echo esc_attr( $queue_config ); ?>'
			>
			</uap-app-integration-settings-list>
		</div>
		<?php
	}

	/**
	 * Output an automator_action submit button with consistent styling and behavior
	 *
	 * @param string $action The action value to submit
	 * @param string $label  The button label
	 * @param array  $args   Optional. Additional button arguments {
	 *     @type string $color       Optional. Button color (primary, secondary, danger)
	 *     @type string $icon        Optional. Icon ID to show before label
	 *     @type string $class       Optional. Additional CSS classes
	 *     @type bool   $disabled    Optional. Whether the button is disabled
	 *     @type bool   $loading     Optional. Whether to show loading state
	 *     @type array  $confirm     Optional. Confirmation dialog settings {
	 *         @type string $heading   Dialog heading
	 *         @type string $content   Dialog content
	 *         @type string $button    Button label for confirmation
	 *     }
	 * }
	 * @return void
	 */
	/**
	 * Output action button.
	 *
	 * @param mixed $action The action.
	 * @param mixed $label The label.
	 * @param mixed $args The arguments.
	 * @return mixed
	 */
	public function output_action_button( $action, $label, $args = array() ) {
		$defaults = array(
			'color'    => 'primary',
			'icon'     => '',
			'class'    => '',
			'disabled' => false,
			'loading'  => false,
			'confirm'  => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Build button attributes
		$attrs = array(
			'type'  => 'button',
			'name'  => 'automator_action',
			'value' => $action,
		);

		// Add integration-id if provided
		if ( ! empty( $args['integration-id'] ) ) {
			$attrs['integration-id'] = $args['integration-id'];
		}

		// Only add non-empty attributes
		if ( ! empty( $args['color'] ) ) {
			$attrs['color'] = $args['color'];
		}

		if ( ! empty( $args['class'] ) ) {
			$attrs['class'] = $args['class'];
		}

		if ( $args['disabled'] ) {
			$attrs['disabled'] = '';
		}

		if ( $args['loading'] ) {
			$attrs['loading'] = '';
		}

		// Add confirmation dialog if specified
		if ( ! empty( $args['confirm'] ) ) {
			$attrs['needs-confirmation'] = '';
			if ( ! empty( $args['confirm']['heading'] ) ) {
				$attrs['confirmation-heading'] = $args['confirm']['heading'];
			}
			if ( ! empty( $args['confirm']['content'] ) ) {
				$attrs['confirmation-content'] = $args['confirm']['content'];
			}
			if ( ! empty( $args['confirm']['button'] ) ) {
				$attrs['confirmation-button-label'] = $args['confirm']['button'];
			}
		}

		// Output the button
		?>
		<uap-app-integration-settings-button 
		<?php
		echo implode(
			' ',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are already escaped with esc_attr()
			array_map(
				function ( $key, $value ) {
					return sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
				},
				array_keys( $attrs ),
				$attrs
			)
		);
		?>
		>
			<?php if ( ! empty( $args['icon'] ) ) : ?>
				<uo-icon id="<?php echo esc_attr( $args['icon'] ); ?>"></uo-icon>
			<?php endif; ?>
			<?php echo esc_html( $label ); ?>
		</uap-app-integration-settings-button>
		<?php
	}

	/**
	 * Output a redirect button
	 *
	 * @param string $label The button label
	 * @param string $url The URL to redirect to
	 * @param string $color The button color
	 * @return void
	 */
	public function redirect_button( $label, $url, $color = 'primary' ) {
		?>
		<uo-button 
			href="<?php echo esc_attr( $url ); ?>" 
			color="<?php echo esc_attr( $color ); ?>"
			target="_self"
			unsafe-force-target
		>
			<?php echo esc_attr( $label ); ?>
		</uo-button>
		<?php
	}

	/**
	 * Output the HTML for a text input field
	 *
	 * @param array $input The input configuration
	 * @return void
	 */
	public function text_input_html( $input ) {
		$default = array(
			'id'          => '',
			'value'       => '',
			'label'       => '',
			'required'    => '',
			'class'       => '',
			'hidden'      => '',
			'disabled'    => '',
			'placeholder' => '',
			'helper'      => '',
		);

		$input = wp_parse_args( $input, $default );
		?>
		<uo-text-field
			id="<?php echo esc_attr( $input['id'] ); ?>"
			value="<?php echo esc_attr( $input['value'] ); ?>"
			label="<?php echo esc_attr( $input['label'] ); ?>"
			class="<?php echo esc_attr( $input['class'] ); ?>"
			<?php echo ! empty( $input['required'] ) ? 'required' : ''; ?>
			<?php echo ! empty( $input['hidden'] ) ? 'hidden' : ''; ?>
			<?php echo ! empty( $input['disabled'] ) ? 'disabled' : ''; ?>
			<?php echo ! empty( $input['placeholder'] ) ? 'placeholder="' . esc_attr( $input['placeholder'] ) . '"' : ''; ?>
			<?php echo ! empty( $input['helper'] ) ? 'helper="' . esc_attr( $input['helper'] ) . '"' : ''; ?>
		></uo-text-field>
		<?php
	}

	/**
	 * Output a switch
	 *
	 * @param array $args The switch configuration
	 *
	 * @return void
	 */
	public function output_switch( $args ) {
		$default = array(
			'id'                 => '',
			'name'               => '',
			'label-on'           => '',
			'label-off'          => '',
			'checked'            => false,
			'required'           => false,
			'disabled'           => false,
			'data-state-control' => '',
			'helper'             => '',
		);

		$args = wp_parse_args( $args, $default );
		?>
		<uo-field-input-switch
			id="<?php echo esc_attr( $args['id'] ); ?>"
			<?php echo ! empty( $args['name'] ) ? 'name="' . esc_attr( $args['name'] ) . '"' : ''; ?>
			<?php echo ! empty( $args['label-on'] ) ? 'label-on="' . esc_attr( $args['label-on'] ) . '"' : ''; ?>
			<?php echo ! empty( $args['label-off'] ) ? 'label-off="' . esc_attr( $args['label-off'] ) . '"' : ''; ?>
			<?php echo $args['checked'] ? 'checked' : ''; ?>
			<?php echo $args['required'] ? 'required' : ''; ?>
			<?php echo $args['disabled'] ? 'disabled' : ''; ?>
			<?php echo ! empty( $args['data-state-control'] ) ? 'data-state-control="' . esc_attr( $args['data-state-control'] ) . '"' : ''; ?>
			<?php echo ! empty( $args['helper'] ) ? 'helper="' . esc_attr( $args['helper'] ) . '"' : ''; ?>
		></uo-field-input-switch>
		<?php
	}

	/**
	 * Output an app integration settings section
	 *
	 * @param array $section The section configuration
	 *
	 * @return void
	 */
	public function output_app_integration_section( $section ) {
		$default = array(
			'id'           => '',
			'section-type' => '',
			'state'        => '',
			'show-when'    => '',
			'content'      => '',
		);

		$section = wp_parse_args( $section, $default );
		?>
		<uap-app-integration-settings-section
			<?php echo ! empty( $section['id'] ) ? 'id="' . esc_attr( $section['id'] ) . '"' : ''; ?>
			<?php echo ! empty( $section['section-type'] ) ? 'section-type="' . esc_attr( $section['section-type'] ) . '"' : ''; ?>
			<?php echo ! empty( $section['state'] ) ? 'state="' . esc_attr( $section['state'] ) . '"' : ''; ?>
			<?php echo ! empty( $section['show-when'] ) ? 'show-when="' . esc_attr( $section['show-when'] ) . '"' : ''; ?>
		>
			<?php echo $section['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</uap-app-integration-settings-section>
		<?php
	}

	/**
	 * Build a CSS class string from a default class and additional classes
	 *
	 * @param string $default_class The default/base class that should always be included
	 * @param string|array $additional_classes Additional classes to add. Can be a string or array of classes.
	 *
	 * @return string The merged and sanitized class string
	 */
	protected function build_css_class_string( $default_class, $additional_classes = '' ) {
		// If no additional classes are provided, return the default class.
		if ( empty( $additional_classes ) ) {
			return $default_class;
		}

		// Ensure we have an array of classes
		$class_array = is_array( $additional_classes )
			? $additional_classes
			: explode( ' ', $additional_classes );

		// Add the default class
		$class_array[] = $default_class;

		// Remove any empty values and join
		return implode( ' ', array_filter( $class_array ) );
	}

	/**
	 * Get an escaped link with optional attributes
	 *
	 * @param string $url The URL to link to
	 * @param string $text The text to display for the link
	 * @param array $args {
	 *     Optional. Additional link attributes.
	 *     @type string $title    The title attribute for the link. Defaults to $text
	 *     @type string $target   The target attribute. Defaults to '_blank' for external links
	 *     @type bool   $external Whether this is an external link. Default true
	 *     @type string $class    Additional CSS classes to add to the link
	 * }
	 *
	 * @return string The escaped link HTML
	 */
	public function get_escaped_link( $url, $text, $args = array() ) {
		// Parse the arguments.
		$args = wp_parse_args(
			$args,
			array(
				'title'    => $text, // Set the title to text by default
				'external' => true,
				'target'   => '_blank',
				'class'    => '',
			)
		);

		// Add external link icon if external
		$icon = $args['external'] ? ' <uo-icon id="external-link"></uo-icon>' : '';

		return sprintf(
			'<a href="%1$s" title="%2$s" target="%3$s"%4$s>%5$s</a>',
			esc_url( $url ),
			esc_attr( $args['title'] ),
			esc_attr( $args['target'] ),
			! empty( $args['class'] ) ? ' class="' . esc_attr( $args['class'] ) . '"' : '',
			esc_html( $text ) . $icon
		);
	}

	/**
	 * Get connect button label
	 *
	 * @return string
	 */
	public function get_connect_button_label() {
		return sprintf(
			// translators: %s: Integration name
			esc_html_x( 'Connect %s account', 'Integration settings', 'uncanny-automator' ),
			$this->get_name()
		);
	}

	/**
	 * Get disconnect button label
	 *
	 * @return string
	 */
	public function get_disconnect_button_label() {
		return esc_html_x( 'Disconnect', 'Integration settings', 'uncanny-automator' );
	}

	/**
	 * Output the disconnect button
	 *
	 * @return void
	 */
	protected function output_disconnect_button() {
		$this->output_action_button(
			'disconnect',
			$this->get_disconnect_button_label(),
			array(
				'color' => 'danger',
			)
		);
	}

	/**
	 * Output the save settings button
	 *
	 * @return void
	 */
	protected function output_save_settings_button() {
		$this->output_action_button(
			'save_settings',
			esc_html_x(
				'Save settings',
				'Integration settings',
				'uncanny-automator'
			)
		);
	}

	/**
	 * Output setup instructions in an alert box
	 *
	 * @param string $initial_text The initial text explaining what the user needs to obtain
	 * @param array  $steps Array of steps to display in the list
	 * @param string $heading Optional. The heading for the alert. If empty, defaults to "Setup instructions"
	 *
	 * @return void - Outputs HTML directly
	 */
	protected function output_setup_instructions( $initial_text, $steps = array(), $heading = '' ) {
		$heading = empty( $heading )
			? esc_html_x( 'Setup instructions', 'Integration settings', 'uncanny-automator' )
			: $heading;

		$kses_args = $this->filter_content_kses_args();
		?>
		<uo-alert heading="<?php echo esc_attr( $heading ); ?>">
			<?php echo wp_kses( $initial_text, $kses_args ); ?>
			<?php echo wp_kses( $this->generate_steps_list( $steps ), $kses_args ); ?>
		</uo-alert>
		<?php
	}

	/**
	 * Generate a list of steps
	 *
	 * @param array $steps The steps to display
	 *
	 * @return string The HTML list of steps
	 */
	public function generate_steps_list( $steps = array() ) {
		if ( empty( $steps ) ) {
			return '';
		}

		$kses_args = $this->filter_content_kses_args();
		$html      = '<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">';
		foreach ( $steps as $step ) {
			$html .= '<li>' . wp_kses( $step, $kses_args ) . '</li>';
		}
		$html .= '</ol>';
		return $html;
	}

	/**
	 * Output a panel subtitle
	 *
	 * @param string $subtitle The subtitle to display
	 * @param string|array $classes Additional CSS classes to add. Can be a string or array of classes.
	 * @return void
	 */
	public function output_panel_subtitle( $subtitle = '', $classes = '' ) {
		$class_string = $this->build_css_class_string( 'uap-settings-panel-content-subtitle', $classes );
		?>
		<div class="<?php echo esc_attr( $class_string ); ?>">
			<?php echo esc_html( $subtitle ); ?>
		</div>
		<?php
	}

	/**
	 * Output a panel paragraph
	 *
	 * @param string $paragraph The paragraph to display
	 * @param string|array $classes Additional CSS classes to add. Can be a string or array of classes.
	 * @return void
	 */
	public function output_subtle_panel_paragraph( $paragraph = '', $classes = '' ) {
		$class_string = $this->build_css_class_string( 'uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle', $classes );
		?>
		<div class="<?php echo esc_attr( $class_string ); ?>">
			<?php echo wp_kses( $paragraph, $this->filter_content_kses_args() ); ?>
		</div>
		<?php
	}

	/**
	 * Get the hook status key
	 *
	 * @return string
	 */
	protected function get_hook_status_key() {
		return $this->is_connected ? 'connected' : 'disconnected';
	}

	/**
	 * Filter callback to modify the kses arguments for content
	 *
	 * @return array The modified kses arguments
	 */
	protected function filter_content_kses_args() {
		$kses_args = array(
			'a'             => array(
				'href'   => array(),
				'target' => array(),
			),
			'strong'        => array(),
			'i'             => array(),
			'em'            => array(),
			'br'            => array(),
			'ol'            => array(
				'class' => array(),
			),
			'li'            => array(),
			'uo-button'     => array(
				'type'                      => array(),
				'name'                      => array(),
				'value'                     => array(),
				'disabled'                  => array(),
				'loading'                   => array(),
				'color'                     => array(),
				'size'                      => array(),
				'href'                      => array(),
				'target'                    => array(),
				'needs-confirmation'        => array(),
				'confirmation-heading'      => array(),
				'confirmation-content'      => array(),
				'confirmation-button-label' => array(),
			),
			'uo-icon'       => array(
				'id'           => array(),
				'integration'  => array(),
				'size'         => array(),
				'animation'    => array(),
				'icon-style'   => array(),
				'is-duotone'   => array(),
				'show-tooltip' => array(),
			),
			'uo-text-field' => array(
				'id'          => array(),
				'value'       => array(),
				'label'       => array(),
				'class'       => array(),
				'required'    => array(),
				'disabled'    => array(),
				'name'        => array(),
				'placeholder' => array(),
				'helper'      => array(),
			),
		);

		// Custom button args
		$kses_args['uap-app-integration-settings-button'] = $kses_args['uo-button'];

		return apply_filters( 'automator_content_kses_args', $kses_args );
	}
}
