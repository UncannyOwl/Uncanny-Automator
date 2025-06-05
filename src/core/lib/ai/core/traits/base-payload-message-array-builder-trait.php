<?php
namespace Uncanny_Automator\Core\Lib\AI\Core\Traits;

/**
 * Trait for building AI message arrays.
 *
 * Provides helper methods for creating message arrays in AI provider format.
 * Used by action classes to format conversations for various AI providers.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Traits
 * @since 5.6
 */
trait Base_Payload_Message_Array_Builder_Trait {

	/**
	 * Build simple system and user message array.
	 *
	 * Creates a standardized message array format that works across
	 * most AI providers. Includes optional system message and required user message.
	 *
	 * @param string $system_content System message content (optional)
	 * @param string $prompt         User message content
	 *
	 * @return array<string, string>[] Message array with role and content
	 */
	protected function create_simple_message( string $system_content, string $prompt ): array {

		$messages = array();

		if ( '' !== $system_content ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_content,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		return $messages;
	}
}
