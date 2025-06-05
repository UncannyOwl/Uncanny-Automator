<?php

namespace Uncanny_Automator\Integrations\OpenAI\Actions\Fields;

class Image_Generate_Fields {

	protected $fields = array();
	protected $field_meta;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct( $field_meta ) {
		$this->field_meta = $field_meta;
		$this->define_fields();
	}

	/**
	 * Define the fields.
	 *
	 * @return void
	 */
	public function define_fields() {

		if ( empty( $this->field_meta ) ) {
			throw new \Exception( 'Field meta is required' );
		}

		$model = array(
			'option_code'     => $this->field_meta,
			'label'           => esc_html_x( 'Model', 'OpenAI', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->format_select_options(
				array(
					'gpt-image-1' => 'GPT-Image-1',
				)
			),
			'ajax'            => array(
				'endpoint' => 'automator_openai_fetch_image_generation_models',
				'event'    => 'on_load',
			),
			'default_value'   => 'gpt-image-1',
			'description'     => esc_html_x( 'The OpenAI model to use for image generation.', 'OpenAI', 'uncanny-automator' ),
			'options_show_id' => false,
		);

		$prompt = array(
			'option_code'   => 'PROMPT',
			'label'         => esc_html_x( 'Prompt', 'OpenAI', 'uncanny-automator' ),
			'input_type'    => 'textarea',
			'required'      => true,
			'default_value' => '',
			'placeholder'   => esc_html_x( 'Describe the image you want to generate.', 'OpenAI', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Example: "A golden retriever surfing on a sunny beach, digital art".', 'OpenAI', 'uncanny-automator' ),
		);

		$size = array(
			'option_code'     => 'SIZE',
			'label'           => esc_html_x( 'Image size', 'OpenAI', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->format_select_options(
				array(
					'1024x1024' => '1024×1024 (Square)',
					'1024x1536' => '1024×1536 (Portrait)',
					'1536x1024' => '1536×1024 (Landscape)',
					'auto'      => 'Auto',
				)
			),
			'default_value'   => '1024x1024',
			'description'     => esc_html_x( 'Choose the image size.', 'OpenAI', 'uncanny-automator' ),
			'options_show_id' => false,
		);

		$quality = array(
			'option_code'     => 'QUALITY',
			'label'           => esc_html_x( 'Quality', 'OpenAI', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->format_select_options(
				array(
					'low'    => 'Low',
					'medium' => 'Medium',
					'high'   => 'High',
					'auto'   => 'Auto',
				)
			),
			'default_value'   => 'high',
			'description'     => esc_html_x( 'Image quality (higher quality uses more tokens).', 'OpenAI', 'uncanny-automator' ),
			'options_show_id' => false,
		);

		$output_format = array(
			'option_code'     => 'OUTPUT_FORMAT',
			'label'           => esc_html_x( 'Image Format', 'OpenAI', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->format_select_options(
				array(
					'png'  => 'PNG (supports transparency)',
					'jpeg' => 'JPEG',
					'webp' => 'WebP',
				)
			),
			'default_value'   => 'png',
			'description'     => esc_html_x( 'Output image file format.', 'OpenAI', 'uncanny-automator' ),
			'options_show_id' => false,
		);

		$compression_level = array(
			'option_code'        => 'COMPRESSION',
			'label'              => esc_html_x( 'Compression level', 'OpenAI', 'uncanny-automator' ),
			'input_type'         => 'int',
			'required'           => false,
			'default_value'      => '',
			'description'        => esc_html_x( 'Compression level (0-100) for JPEG and WebP formats. Leave empty for no compression.', 'OpenAI', 'uncanny-automator' ),
			'options_show_id'    => false,
			'dynamic_visibility' => array(
				'default_state'    => 'hidden',
				'visibility_rules' => array(
					array(
						'operator'             => 'OR',
						'rule_conditions'      => array(
							array(
								'option_code' => 'OUTPUT_FORMAT',
								'compare'     => '==',
								'value'       => 'jpeg',
							),
							array(
								'option_code' => 'OUTPUT_FORMAT',
								'compare'     => '==',
								'value'       => 'webp',
							),
						),
						'resulting_visibility' => 'show',
					),
				),
			),

		);

		$background = array(
			'option_code'     => 'BACKGROUND',
			'label'           => esc_html_x( 'Background', 'OpenAI', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->format_select_options(
				array(
					'opaque'      => esc_html_x( 'Opaque', 'OpenAI', 'uncanny-automator' ),
					'transparent' => esc_html_x( 'Transparent', 'OpenAI', 'uncanny-automator' ),
					'auto'        => esc_html_x( 'Auto', 'OpenAI', 'uncanny-automator' ),
				)
			),
			'default_value'   => 'opaque',
			'description'     => esc_html_x( 'Choose the image background type. Transparent requires PNG or WebP.', 'OpenAI', 'uncanny-automator' ),
			'options_show_id' => false,
		);

		$moderation = array(
			'option_code'     => 'MODERATION',
			'label'           => esc_html_x( 'Moderation Level', 'OpenAI', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->format_select_options(
				array(
					'auto' => esc_html_x( 'Standard (recommended)', 'OpenAI', 'uncanny-automator' ),
					'low'  => esc_html_x( 'Low (less restrictive)', 'OpenAI', 'uncanny-automator' ),
				)
			),
			'default_value'   => 'auto',
			'description'     => esc_html_x( 'Content moderation for generated images.', 'OpenAI', 'uncanny-automator' ),
			'options_show_id' => false,
		);

		$this->fields = array(
			$model,
			$prompt,
			$size,
			$quality,
			$output_format,
			$compression_level,
			$background,
			$moderation,
		);
	}

	/**
	 * Format the select options.
	 *
	 * @param array $options
	 * @return array
	 */
	public function format_select_options( $given_options ) {

		$options = array();
		foreach ( (array) $given_options as $key => $option ) {
			$options[] = array(
				'value' => $key,
				'text'  => $option,
			);
		}

		return $options;
	}

	/**
	 * Get the fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return $this->fields;
	}
}
