<?php

namespace Uncanny_Automator\Integrations\Telegram;

/**
 * Renders a Telegram message body (plain text + entity metadata) as HTML.
 *
 * Telegram's Bot API delivers incoming messages as a plain `text` string plus a
 * separate `entities` array of MessageEntity objects (offset, length, type, optional
 * url/user/language). This class walks those entities and emits a compact HTML
 * representation suitable for any Automator action field that accepts HTML.
 *
 * Newlines in the source text are emitted as `<br>` with the literal `\n` dropped,
 * so the result renders consistently in HTML contexts without doubling up under
 * downstream renderers that also treat `\n` as a line break.
 *
 * Limitations:
 * - Entity types not in the map degrade to plain text (e.g. `cashtag`, `bot_command`,
 *   `custom_emoji`). They contribute their text content, just without wrapping tags.
 * - True overlapping entities (rare per Telegram's own docs) are emitted in source
 *   order and may yield non-strictly-nested HTML. Browsers handle this; strict
 *   parsers may not.
 * - Entity offsets in the Bot API are measured in UTF-16 code units. A surrogate
 *   pair (e.g. most emoji) counts as 2 units, not 1 PHP character. This class
 *   pre-builds a UTF-16-to-PHP-character offset map so entity boundaries land
 *   on the correct character regardless of emoji/other supplementary-plane chars.
 * - Downstream sanitizers vary in which tags and attributes they preserve; the
 *   text content survives in every case but specific formatting (e.g. `<u>`,
 *   custom `class` on `<span>`, the `tg:` URL scheme) may be stripped.
 *
 * @see https://core.telegram.org/bots/api#messageentity
 * @see https://core.telegram.org/bots/api#html-style
 */
class Telegram_Entity_Renderer {

	/**
	 * Simple type-to-tag map for entities whose entire output is a wrapping tag pair.
	 *
	 * @var array<string,string>
	 */
	private const TAG_MAP = array(
		'bold'                  => 'b',
		'italic'                => 'em',
		'underline'             => 'u',
		'strikethrough'         => 'del',
		'code'                  => 'code',
		'pre'                   => 'pre',
		'blockquote'            => 'blockquote',
		'expandable_blockquote' => 'blockquote',
	);

	/**
	 * Render the text + entity stream as HTML.
	 *
	 * @param string $text     The plain UTF-8 message text from the webhook payload.
	 * @param array  $entities The raw `entities` array from the webhook payload.
	 *
	 * @return string HTML-escaped, entity-wrapped text. Returns esc_html'd plain text
	 *                if entities is empty or text is empty.
	 */
	public static function render( $text, $entities = array() ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return '';
		}

		if ( empty( $entities ) || ! is_array( $entities ) ) {
			return self::newlines_to_br( esc_html( $text ) );
		}

		$offset_map = self::build_offset_map( $text );

		// Collect events. Each entity contributes one open + one close event.
		$events = array();
		foreach ( $entities as $i => $entity ) {
			if ( ! is_array( $entity ) || ! isset( $entity['offset'], $entity['length'], $entity['type'] ) ) {
				continue;
			}

			$u16_start = (int) $entity['offset'];
			$u16_end   = $u16_start + (int) $entity['length'];

			if ( ! isset( $offset_map[ $u16_start ] ) || ! isset( $offset_map[ $u16_end ] ) ) {
				continue;
			}

			$php_start = $offset_map[ $u16_start ];
			$php_end   = $offset_map[ $u16_end ];

			$slice = mb_substr( $text, $php_start, $php_end - $php_start, 'UTF-8' );
			$tags  = self::tags_for_entity( $entity, $slice );
			if ( null === $tags ) {
				continue;
			}

			$events[] = array(
				'pos'   => $php_start,
				'kind'  => 'open',
				'tag'   => $tags['open'],
				'end'   => $php_end,
				'order' => $i,
			);
			$events[] = array(
				'pos'   => $php_end,
				'kind'  => 'close',
				'tag'   => $tags['close'],
				'end'   => $php_end,
				'order' => $i,
			);
		}

		// Stable sort: by pos ascending, then close-before-open at the same pos,
		// then outer-wraps-inner for opens at the same start (longer span first).
		usort(
			$events,
			static function ( $a, $b ) {
				if ( $a['pos'] !== $b['pos'] ) {
					return $a['pos'] - $b['pos'];
				}
				if ( $a['kind'] !== $b['kind'] ) {
					return 'close' === $a['kind'] ? -1 : 1;
				}
				if ( 'open' === $a['kind'] && $a['end'] !== $b['end'] ) {
					return $b['end'] - $a['end'];
				}
				return $a['order'] - $b['order'];
			}
		);

		$out    = '';
		$cursor = 0;
		$length = mb_strlen( $text, 'UTF-8' );

		foreach ( $events as $event ) {
			if ( $event['pos'] > $cursor ) {
				$out   .= esc_html( mb_substr( $text, $cursor, $event['pos'] - $cursor, 'UTF-8' ) );
				$cursor = $event['pos'];
			}
			$out .= $event['tag'];
		}

		if ( $cursor < $length ) {
			$out .= esc_html( mb_substr( $text, $cursor, $length - $cursor, 'UTF-8' ) );
		}

		return self::newlines_to_br( $out );
	}

	/**
	 * Replace every line break in the rendered output with a `<br>` tag.
	 *
	 * Unlike `nl2br`, this strips the literal `\n` rather than leaving it in
	 * place after the `<br>`. Downstream renderers that themselves convert `\n`
	 * to a line break would otherwise emit two `<br>` per source newline.
	 *
	 * @param string $html Rendered HTML (already escape-safe).
	 *
	 * @return string
	 */
	private static function newlines_to_br( $html ) {
		return str_replace( array( "\r\n", "\n", "\r" ), '<br>', $html );
	}

	/**
	 * Build a map from UTF-16 code-unit offset to PHP UTF-8 character offset.
	 *
	 * Telegram's MessageEntity offsets are UTF-16-based. PHP string functions
	 * are byte- or UTF-8-character-based. A non-BMP character (emoji etc.)
	 * is 2 code units in UTF-16 but 1 character in PHP — without this map,
	 * every entity that appears after such a character would land on the
	 * wrong PHP offset.
	 *
	 * @param string $text The UTF-8 text.
	 *
	 * @return array<int,int> Map of UTF-16 offset => PHP char offset, including
	 *                        a sentinel entry for the end-of-string position.
	 */
	private static function build_offset_map( $text ) {
		$map = array();
		$u16 = 0;
		$php = 0;

		$chars = mb_str_split( $text, 1, 'UTF-8' );
		foreach ( $chars as $char ) {
			$map[ $u16 ] = $php;
			$code        = mb_ord( $char, 'UTF-8' );
			$u16        += ( false !== $code && $code >= 0x10000 ) ? 2 : 1;
			++$php;
		}

		$map[ $u16 ] = $php;

		return $map;
	}

	/**
	 * Resolve an entity to its opening + closing HTML tags.
	 *
	 * @param array  $entity The entity descriptor from the webhook.
	 * @param string $slice  The text covered by the entity (used for auto-linkers
	 *                       like `url`, `email`, `phone_number`, `mention`).
	 *
	 * @return array{open:string,close:string}|null Pair of tags, or null to skip.
	 */
	private static function tags_for_entity( $entity, $slice ) {
		$type = $entity['type'];

		if ( isset( self::TAG_MAP[ $type ] ) ) {
			$tag = self::TAG_MAP[ $type ];
			return array(
				'open'  => '<' . $tag . '>',
				'close' => '</' . $tag . '>',
			);
		}

		switch ( $type ) {
			case 'url':
				return array(
					'open'  => '<a href="' . esc_url( $slice ) . '">',
					'close' => '</a>',
				);

			case 'text_link':
				if ( empty( $entity['url'] ) ) {
					return null;
				}
				return array(
					'open'  => '<a href="' . esc_url( $entity['url'] ) . '">',
					'close' => '</a>',
				);

			case 'email':
				return array(
					'open'  => '<a href="' . esc_url( 'mailto:' . $slice ) . '">',
					'close' => '</a>',
				);

			case 'phone_number':
				return array(
					'open'  => '<a href="' . esc_url( 'tel:' . $slice ) . '">',
					'close' => '</a>',
				);

			case 'mention':
				$username = ltrim( $slice, '@' );
				return array(
					'open'  => '<a href="' . esc_url( 'https://t.me/' . $username ) . '">',
					'close' => '</a>',
				);

			case 'text_mention':
				if ( empty( $entity['user']['id'] ) ) {
					return null;
				}
				return array(
					'open'  => '<a href="tg://user?id=' . (int) $entity['user']['id'] . '">',
					'close' => '</a>',
				);

			case 'spoiler':
				// Per Telegram's HTML mode docs, `<span class="tg-spoiler">` is equivalent
				// to `<tg-spoiler>`. Using the span form is more portable across downstream
				// sanitizers that don't recognise the custom element.
				return array(
					'open'  => '<span class="tg-spoiler">',
					'close' => '</span>',
				);
		}

		return null;
	}
}
