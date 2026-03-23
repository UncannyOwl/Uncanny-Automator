<?php

namespace Uncanny_Automator\Integrations\Telegram;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class TELEGRAM_SEND_MESSAGE
 *
 * @package Uncanny_Automator
 *
 * @property Telegram_App_Helpers $helpers
 * @property Telegram_Api_Caller $api
 * @property Telegram_Webhooks $webhooks
 */
class TELEGRAM_SEND_MESSAGE extends App_Action {

	/**
	 * Telegram API HTML documentation link
	 *
	 * @var string
	 */
	private const TELEGRAM_HTML_DOCS_URL = 'https://core.telegram.org/bots/api#html-style';

	/**
	 * Telegram API Markdown documentation link
	 *
	 * @var string
	 */
	private const TELEGRAM_MARKDOWN_DOCS_URL = 'https://core.telegram.org/bots/api#markdownv2-style';

	/**
	 * External link icon
	 *
	 * @var string
	 */
	private const EXTERNAL_LINK_ICON = ' <uo-icon id="external-link"></uo-icon>';

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'TELEGRAM' );
		$this->set_action_code( 'SEND_MESSAGE' );
		$this->set_action_meta( 'CHAT_ID' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/telegram/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: chat ID
				esc_attr_x( 'Send {{a text message:%1$s}}', 'Telegram', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Send {{a text message}}', 'Telegram', 'uncanny-automator' ) );
		$this->set_wpautop( false );
	}

	/**
	 * Define action options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'              => $this->get_action_meta(),
				'label'                    => esc_attr_x( 'Chat/Channel', 'Telegram', 'uncanny-automator' ),
				'input_type'               => 'select',
				'options'                  => array(),
				'supports_tokens'          => true,
				'required'                 => true,
				'description'              => esc_html_x( 'Select a registered chat/channel or enter in a custom value. Note: your bot must be an admin in the chat or channel to send messages.', 'Telegram', 'uncanny-automator' ),
				'custom_value_description' => esc_html_x( 'Enter a numeric chat ID (e.g., -123456789) or a public channel username (e.g., @mychannel). Note: private channels require numeric IDs only.', 'Telegram', 'uncanny-automator' ),
				'ajax'                     => array(
					'endpoint' => 'automator_telegram_get_channels_chats',
					'event'    => 'on_load',
				),
			),
			array(
				'option_code'           => 'MESSAGE_FORMAT',
				'label'                 => esc_attr_x( 'Message format', 'Telegram', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'default_value'         => 'Markdown',
				'options_show_id'       => false,
				'supports_custom_value' => false,
				'options'               => array(
					array(
						'text'  => esc_attr_x( 'Text', 'Telegram', 'uncanny-automator' ),
						'value' => 'Markdown',
					),
					array(
						'text'  => esc_attr_x( 'HTML', 'Telegram', 'uncanny-automator' ),
						'value' => 'HTML',
					),
				),
				'description'           => sprintf(
					// translators: %1$s: opening anchor tag for Markdown docs, %2$s: closing anchor tag, %3$s: opening anchor tag for HTML docs, %4$s: closing anchor tag
					esc_html_x( 'Use %1$sText%2$s for simple Markdown support or %3$sHTML%4$s for advanced visual editing with allowed tags.', 'Telegram', 'uncanny-automator' ),
					'<a href="' . self::TELEGRAM_MARKDOWN_DOCS_URL . '" target="_blank" rel="noopener noreferrer">',
					' ' . self::EXTERNAL_LINK_ICON . '</a>',
					'<a href="' . self::TELEGRAM_HTML_DOCS_URL . '" target="_blank" rel="noopener noreferrer">',
					' ' . self::EXTERNAL_LINK_ICON . '</a>'
				),
			),
			array(
				'option_code'        => 'TEXT',
				'label'              => esc_attr_x( 'Text', 'Telegram', 'uncanny-automator' ),
				'input_type'         => 'textarea',
				'supports_tokens'    => true,
				'required'           => false,
				'dynamic_visibility' => $this->get_format_visibility_rules( 'show', 'Markdown' ),
				'description'        => sprintf(
					// translators: %1$s: opening anchor tag for Markdown docs, %2$s: closing anchor tag
					esc_html_x( 'Simple text - supports Markdown flavored content. %1$sMore info%2$s', 'Telegram', 'uncanny-automator' ),
					'<a href="' . self::TELEGRAM_MARKDOWN_DOCS_URL . '" target="_blank" rel="noopener noreferrer">',
					' ' . self::EXTERNAL_LINK_ICON . '</a>'
				),
			),
			array(
				'option_code'        => 'TEXT_HTML',
				'label'              => esc_attr_x( 'HTML text', 'Telegram', 'uncanny-automator' ),
				'input_type'         => 'textarea',
				'supports_tokens'    => true,
				'required'           => false,
				'dynamic_visibility' => $this->get_format_visibility_rules( 'hidden', 'HTML' ),
				'supports_tinymce'   => true,
				'supports_media'     => false,
				'allowed_html_tags'  => $this->get_allowed_html_tags(),
				'description'        => sprintf(
					// translators: %1$s: opening anchor tag, %2$s: closing anchor tag
					esc_html_x( 'Format your message with the rich text editor. Supported tags: bold, italic, underline, strikethrough, links, blockquotes, and code. %1$sView Telegram documentation%2$s', 'Telegram', 'uncanny-automator' ),
					'<a href="' . self::TELEGRAM_HTML_DOCS_URL . '" target="_blank" rel="noopener noreferrer">',
					' ' . self::EXTERNAL_LINK_ICON . '</a>'
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$chat_id        = $this->get_parsed_meta_value( $this->get_action_meta() );
		$message_format = $this->get_parsed_meta_value( 'MESSAGE_FORMAT', 'Markdown' );

		// Validate chat ID
		if ( empty( $chat_id ) ) {
			throw new Exception( esc_html_x( 'Chat ID is required', 'Telegram', 'uncanny-automator' ) );
		}

		// Get the appropriate text based on format
		$text = 'HTML' === $message_format
			? $this->get_parsed_meta_value( 'TEXT_HTML' )
			: $this->get_parsed_meta_value( 'TEXT' );

		// Validate message text
		if ( empty( $text ) ) {
			throw new Exception( esc_html_x( 'Message text is required', 'Telegram', 'uncanny-automator' ) );
		}

		// Escape HTML entities if using HTML format per Telegram API requirements
		if ( 'HTML' === $message_format ) {
			$text = $this->escape_html_for_telegram( $text );
		}

		// Send message with appropriate parse mode
		$this->api->send_message( $chat_id, $text, $message_format );

		return true;
	}

	/**
	 * Get allowed HTML tags for Telegram HTML mode
	 *
	 * Note: 'br' is included for TinyMCE editor support, but will be converted
	 * to newlines before sending to Telegram (since Telegram doesn't support <br>)
	 *
	 * @see https://core.telegram.org/bots/api#html-style
	 *
	 * @return array
	 */
	private function get_allowed_html_tags() {
		return array(
			'b',
			'strong',
			'i',
			'em',
			'u',
			'ins',
			's',
			'strike',
			'del',
			'a',
			'code',
			'pre',
			'blockquote',
			'br', // Required for TinyMCE editor (converted to \n before sending)
		);
	}

	/**
	 * Generate dynamic visibility rules based on MESSAGE_FORMAT field
	 *
	 * @param string $default_state Default visibility state ('show' or 'hidden')
	 * @param string $value The MESSAGE_FORMAT value to match ('Markdown' or 'HTML')
	 *
	 * @return array
	 */
	private function get_format_visibility_rules( $default_state, $value ) {
		return array(
			'default_state'    => $default_state,
			'visibility_rules' => array(
				array(
					'operator'             => 'AND',
					'rule_conditions'      => array(
						array(
							'option_code' => 'MESSAGE_FORMAT',
							'compare'     => '==',
							'value'       => $value,
						),
					),
					'resulting_visibility' => 'show',
				),
			),
		);
	}

	/**
	 * Escape HTML content for Telegram API
	 * Per API docs: must replace '&' with '&amp;', '<' with '&lt;', and '>' with '&gt;'
	 * But only outside of allowed HTML tags
	 *
	 * @param string $html
	 * @return string
	 */
	private function escape_html_for_telegram( $html ) {

		// Convert <br> tags to newlines (Telegram doesn't support <br> in HTML mode)
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );

		// Convert &nbsp; to regular spaces (Telegram doesn't handle HTML entities well)
		$html = str_replace( '&nbsp;', ' ', $html );

		// Remove TinyMCE-specific attributes (data-mce-*)
		$html = preg_replace( '/\s*data-mce-[a-z\-]+="[^"]*"/i', '', $html );

		// Get allowed tags from centralized method
		// Note: We also allow 'span' and 'tg-emoji' for Telegram-specific features
		$allowed_tags = array_merge(
			$this->get_allowed_html_tags(),
			array( 'span', 'tg-emoji' )
		);

		// Create pattern to match allowed tags
		$tag_pattern = implode( '|', array_map( 'preg_quote', $allowed_tags ) );

		// Split content into segments: tags and text.
		$segments = preg_split(
			'/(<\/?(?:' . $tag_pattern . ')(?:\s[^>]*)?>)/i',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		$result = '';

		foreach ( $segments as $segment ) {
			// Check if segment is a tag or escape entities in text content.
			$result .= preg_match( '/^<\/?(?:' . $tag_pattern . ')(?:\s[^>]*)?>/i', $segment )
				? $segment
				: str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $segment );
		}

		return $result;
	}
}
