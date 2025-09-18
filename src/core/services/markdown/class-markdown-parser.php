<?php

namespace Uncanny_Automator\Services\Markdown;

/**
 * Class Markdown_Parser
 *
 * A service for parsing markdown content into HTML.
 * Supports CommonMark syntax plus extended features like task lists, mentions, and enhanced formatting.
 *
 * @package Uncanny_Automator\Services\Markdown
 */
class Markdown_Parser {

	/**
	 * Convert markdown content to HTML with configurable options
	 *
	 * @param string $content The markdown content to parse
	 * @param array  $options Optional formatting options
	 *
	 * @return string The formatted HTML content
	 */
	public function parse( $content, $options = array() ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return '';
		}

		$options = $this->get_default_options( $options );

		/**
		 * Filter markdown parsing options.
		 *
		 * Allows modification of how markdown content is processed and converted to HTML.
		 *
		 * @param array  $options Formatting options array
		 * @param string $content The original markdown content
		 *
		 * @return array Modified formatting options
		 */
		$options = apply_filters( 'automator_markdown_parser_options', $options, $content );

		$formatted_content = $this->process_content( $content, $options );

		/**
		 * Filter the final markdown HTML output.
		 *
		 * Allows final modifications to the processed HTML content.
		 *
		 * @param string $formatted_content The processed HTML content
		 * @param string $content           The original markdown content
		 * @param array  $options           The formatting options used
		 *
		 * @return string Modified HTML content
		 */
		$formatted_content = apply_filters( 'automator_markdown_parser_html', $formatted_content, $content, $options );

		return $formatted_content;
	}

	/**
	 * Get default parsing options
	 *
	 * @param array $options User-provided options
	 *
	 * @return array Complete options array
	 */
	private function get_default_options( $options = array() ) {
		$defaults = array(
			// Content processing
			'trim_content'          => true,   // Remove leading/trailing whitespace
			'convert_markdown'      => true,   // Convert markdown syntax to HTML
			'preserve_newlines'     => true,   // Convert \n to <br> tags
			'normalize_breaks'      => true,   // Clean up excessive line breaks

			// Block elements
			'convert_headers'       => true,   // Convert # headers to <h1-h6>
			'convert_blockquotes'   => true,   // Convert > blockquotes
			'convert_lists'         => true,   // Convert numbered/bullet lists
			'convert_task_lists'    => true,   // Convert - [ ] task lists
			'convert_code_blocks'   => true,   // Convert ``` code blocks

			// Inline elements
			'convert_emphasis'      => true,   // Convert **bold** and *italic*
			'convert_strikethrough' => true, // Convert ~~strikethrough~~
			'convert_code'          => true,   // Convert `inline code`
			'convert_links'         => true,   // Convert [text](url) links
			'convert_images'        => true,   // Convert ![alt](src) images
			'convert_mentions'      => true,   // Convert @username mentions
		);

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Process content through all enabled transformations
	 *
	 * @param string $content The raw content
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_content( $content, $options ) {
		$formatted_content = $content;

		if ( $options['trim_content'] ) {
			$formatted_content = trim( $formatted_content );
		}

		if ( $options['convert_markdown'] ) {
			$formatted_content = $this->convert_markdown_to_html( $formatted_content, $options );
		}

		if ( $options['normalize_breaks'] ) {
			$formatted_content = $this->normalize_line_breaks( $formatted_content );
		}

		if ( $options['preserve_newlines'] && ! $options['convert_markdown'] ) {
			$formatted_content = nl2br( $formatted_content, false );
		}

		return $formatted_content;
	}

	/**
	 * Normalize excessive line breaks
	 *
	 * @param string $content Content with potential excessive breaks
	 *
	 * @return string Normalized content
	 */
	private function normalize_line_breaks( $content ) {
		// Replace multiple consecutive <br> tags with double <br>
		return preg_replace( '/(<br\s*\/?>){3,}/', '<br><br>', $content );
	}

	/**
	 * Convert markdown to HTML using enabled options
	 *
	 * @param string $text The text with markdown syntax
	 * @param array  $options Conversion options
	 *
	 * @return string The text with HTML conversion
	 */
	private function convert_markdown_to_html( $text, $options = array() ) {
		$html = $text;

		// Process in order of precedence to avoid conflicts
		$html = $this->process_code_blocks( $html, $options );
		$html = $this->process_headers( $html, $options );
		$html = $this->process_blockquotes( $html, $options );
		$html = $this->process_emphasis( $html, $options );
		$html = $this->process_strikethrough( $html, $options );
		$html = $this->process_links( $html, $options );
		$html = $this->process_images( $html, $options );
		$html = $this->process_mentions( $html, $options );
		$html = $this->process_inline_code( $html, $options );
		$html = $this->process_lists( $html, $options );
		$html = $this->apply_line_breaks( $html, $options );

		return $html;
	}

	/**
	 * Process code blocks
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_code_blocks( $html, $options ) {
		if ( ! $options['convert_code_blocks'] ) {
			return $html;
		}

		// Multi-line code blocks with language specification ```lang\ncode\n```
		$html = preg_replace_callback(
			'/```(\w+)?\n(.*?)\n```/s',
			function ( $matches ) {
				$language = ! empty( $matches[1] ) ? ' class="language-' . esc_attr( $matches[1] ) . '"' : '';
				$code     = htmlspecialchars( $matches[2] );
				return '<pre><code' . $language . '>' . $code . '</code></pre>';
			},
			$html
		);

		// Simple multi-line code blocks ```\ncode\n```
		$html = preg_replace_callback(
			'/```\n(.*?)\n```/s',
			function ( $matches ) {
				$code = htmlspecialchars( $matches[1] );
				return '<pre><code>' . $code . '</code></pre>';
			},
			$html
		);

		return $html;
	}

	/**
	 * Process inline code
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_inline_code( $html, $options ) {
		if ( ! $options['convert_code'] ) {
			return $html;
		}

		// Inline code (but not inside code blocks)
		return preg_replace( '/`([^`\n]+)`/', '<code>$1</code>', $html );
	}

	/**
	 * Process headers
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_headers( $html, $options ) {
		if ( ! $options['convert_headers'] ) {
			return $html;
		}

		$html = preg_replace( '/^# (.*$)/m', '<h1>$1</h1>', $html );
		$html = preg_replace( '/^## (.*$)/m', '<h2>$1</h2>', $html );
		$html = preg_replace( '/^### (.*$)/m', '<h3>$1</h3>', $html );
		$html = preg_replace( '/^#### (.*$)/m', '<h4>$1</h4>', $html );
		$html = preg_replace( '/^##### (.*$)/m', '<h5>$1</h5>', $html );
		$html = preg_replace( '/^###### (.*$)/m', '<h6>$1</h6>', $html );

		return $html;
	}

	/**
	 * Process blockquotes
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_blockquotes( $html, $options ) {
		if ( ! $options['convert_blockquotes'] ) {
			return $html;
		}

		return preg_replace( '/^> (.*$)/m', '<blockquote>$1</blockquote>', $html );
	}

	/**
	 * Process emphasis (bold/italic)
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_emphasis( $html, $options ) {
		if ( ! $options['convert_emphasis'] ) {
			return $html;
		}

		// Process in order: triple, double, single to avoid conflicts
		$html = preg_replace( '/\*\*\*([^*\n]+)\*\*\*/', '<strong><em>$1</em></strong>', $html ); // Bold + italic with *
		$html = preg_replace( '/___([^_\n]+)___/', '<strong><em>$1</em></strong>', $html );       // Bold + italic with _
		$html = preg_replace( '/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $html );             // Bold with *
		$html = preg_replace( '/__([^_\n]+)__/', '<strong>$1</strong>', $html );                 // Bold with _
		$html = preg_replace( '/\*([^*\n]+)\*/', '<em>$1</em>', $html );                         // Italic with *
		$html = preg_replace( '/_([^_\n]+)_/', '<em>$1</em>', $html );                           // Italic with _

		return $html;
	}

	/**
	 * Process strikethrough
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_strikethrough( $html, $options ) {
		if ( ! $options['convert_strikethrough'] ) {
			return $html;
		}

		return preg_replace( '/~~([^~\n]+)~~/', '<del>$1</del>', $html );
	}

	/**
	 * Process links
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_links( $html, $options ) {
		if ( ! $options['convert_links'] ) {
			return $html;
		}

		return preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );
	}

	/**
	 * Process images
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_images( $html, $options ) {
		if ( ! $options['convert_images'] ) {
			return $html;
		}

		return preg_replace( '/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" />', $html );
	}

	/**
	 * Process mentions
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_mentions( $html, $options ) {
		if ( ! $options['convert_mentions'] ) {
			return $html;
		}

		return preg_replace( '/@([a-zA-Z0-9_-]+)/', '<span class="mention">@$1</span>', $html );
	}

	/**
	 * Process lists
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function process_lists( $html, $options ) {
		if ( $options['convert_lists'] || $options['convert_task_lists'] ) {
			return $this->convert_lists_to_html( $html, $options );
		}

		return $html;
	}

	/**
	 * Apply intelligent line breaks
	 *
	 * @param string $html Content to process
	 * @param array  $options Processing options
	 *
	 * @return string Processed content
	 */
	private function apply_line_breaks( $html, $options ) {
		if ( ! $options['preserve_newlines'] ) {
			return $html;
		}

		return $this->process_line_breaks( $html );
	}

	/**
	 * Convert markdown lists to HTML lists
	 *
	 * @param string $text The text containing markdown lists
	 * @param array  $options Processing options
	 *
	 * @return string The text with HTML lists
	 */
	private function convert_lists_to_html( $text, $options ) {
		$lines      = explode( "\n", $text );
		$result     = array();
		$list_state = $this->get_initial_list_state();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( $this->is_task_list_item( $trimmed ) && $options['convert_task_lists'] ) {
				$result = $this->process_task_list_item( $trimmed, $result, $list_state );
			} elseif ( $this->is_ordered_list_item( $trimmed ) && $options['convert_lists'] ) {
				$result = $this->process_ordered_list_item( $trimmed, $result, $list_state );
			} elseif ( $this->is_unordered_list_item( $trimmed ) && $options['convert_lists'] ) {
				$result = $this->process_unordered_list_item( $trimmed, $result, $list_state );
			} else {
				$result   = $this->close_all_lists( $result, $list_state );
				$result[] = $line;
			}
		}

		// Close any remaining open lists
		$result = $this->close_all_lists( $result, $list_state );

		return implode( "\n", $result );
	}

	/**
	 * Get initial list state
	 *
	 * @return array List state array
	 */
	private function get_initial_list_state() {
		return array(
			'ordered'   => false,
			'unordered' => false,
			'task'      => false,
		);
	}

	/**
	 * Check if line is a task list item
	 *
	 * @param string $line Trimmed line content
	 *
	 * @return bool True if task list item
	 */
	private function is_task_list_item( $line ) {
		return preg_match( '/^-\s+\[([ x])\]\s+(.+)/', $line );
	}

	/**
	 * Check if line is an ordered list item
	 *
	 * @param string $line Trimmed line content
	 *
	 * @return bool True if ordered list item
	 */
	private function is_ordered_list_item( $line ) {
		return preg_match( '/^\d+\.\s+(.+)/', $line );
	}

	/**
	 * Check if line is an unordered list item
	 *
	 * @param string $line Trimmed line content
	 *
	 * @return bool True if unordered list item
	 */
	private function is_unordered_list_item( $line ) {
		return preg_match( '/^[-*+]\s+(.+)/', $line );
	}

	/**
	 * Process task list item
	 *
	 * @param string $line Trimmed line content
	 * @param array  $result Current result array
	 * @param array  $list_state Current list state
	 *
	 * @return array Updated result array
	 */
	private function process_task_list_item( $line, $result, &$list_state ) {
		if ( ! $list_state['task'] ) {
			$result             = $this->close_other_lists( $result, $list_state, 'task' );
			$result[]           = '<ul class="task-list">';
			$list_state['task'] = true;
		}

		if ( preg_match( '/^-\s+\[([ x])\]\s+(.+)/', $line, $matches ) ) {
			$checked  = ( 'x' === $matches[1] ) ? ' checked="checked"' : '';
			$checkbox = '<input type="checkbox" disabled' . $checked . '> ';
			$result[] = '<li class="task-list-item">' . $checkbox . $matches[2] . '</li>';
		}

		return $result;
	}

	/**
	 * Process ordered list item
	 *
	 * @param string $line Trimmed line content
	 * @param array  $result Current result array
	 * @param array  $list_state Current list state
	 *
	 * @return array Updated result array
	 */
	private function process_ordered_list_item( $line, $result, &$list_state ) {
		if ( ! $list_state['ordered'] ) {
			$result                = $this->close_other_lists( $result, $list_state, 'ordered' );
			$result[]              = '<ol>';
			$list_state['ordered'] = true;
		}

		if ( preg_match( '/^\d+\.\s+(.+)/', $line, $matches ) ) {
			$result[] = '<li>' . $matches[1] . '</li>';
		}

		return $result;
	}

	/**
	 * Process unordered list item
	 *
	 * @param string $line Trimmed line content
	 * @param array  $result Current result array
	 * @param array  $list_state Current list state
	 *
	 * @return array Updated result array
	 */
	private function process_unordered_list_item( $line, $result, &$list_state ) {
		if ( ! $list_state['unordered'] ) {
			$result                  = $this->close_other_lists( $result, $list_state, 'unordered' );
			$result[]                = '<ul>';
			$list_state['unordered'] = true;
		}

		if ( preg_match( '/^[-*+]\s+(.+)/', $line, $matches ) ) {
			$result[] = '<li>' . $matches[1] . '</li>';
		}

		return $result;
	}

	/**
	 * Close other list types when starting a new one
	 *
	 * @param array  $result Current result array
	 * @param array  $list_state Current list state
	 * @param string $keep_open List type to keep open
	 *
	 * @return array Updated result array
	 */
	private function close_other_lists( $result, &$list_state, $keep_open ) {
		if ( 'ordered' !== $keep_open && $list_state['ordered'] ) {
			$result[]              = '</ol>';
			$list_state['ordered'] = false;
		}
		if ( 'unordered' !== $keep_open && $list_state['unordered'] ) {
			$result[]                = '</ul>';
			$list_state['unordered'] = false;
		}
		if ( 'task' !== $keep_open && $list_state['task'] ) {
			$result[]           = '</ul>';
			$list_state['task'] = false;
		}

		return $result;
	}

	/**
	 * Close all open lists
	 *
	 * @param array $result Current result array
	 * @param array $list_state Current list state
	 *
	 * @return array Updated result array
	 */
	private function close_all_lists( $result, &$list_state ) {
		if ( $list_state['ordered'] ) {
			$result[]              = '</ol>';
			$list_state['ordered'] = false;
		}
		if ( $list_state['unordered'] ) {
			$result[]                = '</ul>';
			$list_state['unordered'] = false;
		}
		if ( $list_state['task'] ) {
			$result[]           = '</ul>';
			$list_state['task'] = false;
		}

		return $result;
	}

	/**
	 * Process line breaks to avoid breaks inside HTML elements
	 *
	 * @param string $html The HTML content
	 *
	 * @return string The HTML with processed line breaks
	 */
	private function process_line_breaks( $html ) {
		$lines  = explode( "\n", $html );
		$result = array();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			// Skip empty lines (will create natural spacing)
			if ( empty( $trimmed ) ) {
				$result[] = '';
				continue;
			}

			// Don't add <br> to lines that are already HTML block elements
			if ( $this->is_html_block_element( $trimmed ) ) {
				$result[] = $line;
			} else {
				// Add <br> to regular text lines
				$result[] = $line . '<br>';
			}
		}

		// Join back together and clean up
		$html = implode( "\n", $result );
		$html = $this->clean_up_line_breaks( $html );

		return $html;
	}

	/**
	 * Check if line contains HTML block elements
	 *
	 * @param string $line Trimmed line content
	 *
	 * @return bool True if line contains block elements
	 */
	private function is_html_block_element( $line ) {
		return preg_match( '/^<(h[1-6]|p|div|ul|ol|li|blockquote|pre|code)/', $line ) ||
			preg_match( '/<\/(h[1-6]|p|div|ul|ol|li|blockquote|pre|code)>$/', $line );
	}

	/**
	 * Clean up excessive line breaks
	 *
	 * @param string $html HTML content
	 *
	 * @return string Cleaned HTML
	 */
	private function clean_up_line_breaks( $html ) {
		// Clean up multiple consecutive <br> tags and normalize spacing
		$html = preg_replace( '/(<br>\s*){3,}/', '<br><br>', $html );

		// Remove <br> tags that are immediately before block elements
		$html = preg_replace( '/<br>\s*\n\s*<(h[1-6]|p|div|ul|ol|blockquote|pre)/', "\n<$1", $html );

		// Remove <br> tags that are immediately after block elements
		$html = preg_replace( '/<\/(h[1-6]|p|div|ul|ol|blockquote|pre)>\s*<br>/', "</$1>", $html );

		return $html;
	}
}
