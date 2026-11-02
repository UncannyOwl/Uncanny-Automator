<?php

namespace Uncanny_Automator\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrate_Nested_Tokens.
 *
 * @package Uncanny_Automator
 */
class Migrate_Nested_Tokens extends Tokens_Migration {

	/**
	 * The legacy spellings that mean something only inside a token.
	 *
	 * @var string[]
	 */
	const TOKEN_ONLY_STRINGS = array( '««', '»»', '¦' );

	/**
	 * The delimiters map_tokens() splits a text on.
	 *
	 * An opener counts only when a token name follows it (letters, digits and underscores) and then a
	 * separator (`:`, `;`, `¦`) or a closer: `{{POSTMETA:`, `««USERMETA¦`, `{{recipe_run}}`. A literal `««`
	 * or `{{` in a recipe's text is not an opener.
	 *
	 * @var string
	 */
	const TOKEN_DELIMITERS = '/(\{\{(?=[A-Za-z0-9_]+(?::|;|¦|\}\}|»»))|««(?=[A-Za-z0-9_]+(?::|;|¦|\}\}|»»))|\}\}|»»)/';

	/**
	 * What a token never holds: a double quote, a tag boundary or a line break. A span between an opener
	 * and a closer that crosses one is the recipe's text (two JSON fields, two tags, two lines), not a token.
	 *
	 * @var string
	 */
	const NOT_IN_A_TOKEN = '/["<>\r\n]/';

	/**
	 * What a POSTMETA or USERMETA token never holds: a tag opening or a line break. A meta key may hold a
	 * double quote or `>`, so a meta token allows those two, but a span that crosses a `<` or a line break
	 * is still the recipe's text (two tags, two lines), not a token.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const NOT_IN_A_META_TOKEN = '/[<\r\n]/';

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct( $name ) {
		parent::__construct( $name );
		add_filter( 'automator_recipe_part_meta_value', array( $this, 'replace_strings_in_imports' ), 10, 4 );
	}

	/**
	 * strings_to_replace
	 *
	 * @return array
	 */
	public function strings_to_replace() {

		return array(
			'««'                   => '{{',
			'»»'                   => '}}',
			'¦'                    => ':',
			'{{CALCULATION'        => '{{UT:ADVANCED:CALCULATION',
			'{{POSTMETA'           => '{{UT:ADVANCED:POSTMETA',
			'{{USERMETA'           => '{{UT:ADVANCED:USERMETA',
			'{{recipe_run}}'       => '{{UT:ADVANCED:RECIPE_RUN}}',
			'{{recipe_total_run}}' => '{{UT:ADVANCED:RECIPE_RUN_TOTAL}}',
		);
	}

	/**
	 * replace_strings
	 *
	 * The legacy delimiters (««, »»), the separator (¦) and the nested [[ ]] are token syntax: they
	 * are rewritten inside a token only. Outside one they are the text of a recipe (a JSON array of
	 * arrays, a regular expression class, "Read more »»") and stay as they are. The brace-anchored
	 * spellings ({{POSTMETA, {{recipe_run}}) name a token wherever they sit.
	 *
	 * @param  string $initial_value
	 * @return string
	 */
	public function replace_strings( $initial_value ) {
		$text    = (string) $initial_value;
		$decoded = json_decode( $text, true );
		if ( JSON_ERROR_NONE === json_last_error() && ( is_array( $decoded ) || is_string( $decoded ) ) ) {
			// JSON quotes bound values, not token parameters: scan each decoded string separately.
			$rewritten = preg_replace_callback(
				'/"(?:[^"\\\\]|\\\\.)*"/s',
				function ( $match ) {
					$value   = json_decode( $match[0], true );
					$changed = $this->replace_tokens_in_text( $value );
					return $changed === $value ? $match[0] : wp_json_encode( $changed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				},
				$text
			);
			return is_string( $rewritten ) ? $rewritten : $text;
		}
		return $this->replace_tokens_in_text( $text );
	}

	/**
	 * Rewrite tokens inside one text value, without crossing JSON string boundaries.
	 *
	 * @since 8.0.0
	 *
	 * @param string $initial_value The decoded text.
	 * @return string
	 */
	private function replace_tokens_in_text( $initial_value ) {

		$token_only = array_flip( self::TOKEN_ONLY_STRINGS );

		$updated_value = $this->map_tokens(
			(string) $initial_value,
			function ( $token ) use ( $token_only ) {
				return strtr( $this->replace_postmeta_inner_tokens( $token ), array_intersect_key( $this->strings_to_replace(), $token_only ) );
			}
		);

		return $this->recursive_strtr( $updated_value, array_diff_key( $this->strings_to_replace(), $token_only ) );
	}

	/**
	 * Run a callback on every token of a text, each token being a balanced {{ }} or «« »» span (the
	 * two delimiters may be mixed: {{CALCULATION:««USERMETA¦points»» + 1}}), and leave the rest alone.
	 *
	 * A span is a token only when it opens on a token name (TOKEN_DELIMITERS) and holds no double quote,
	 * tag boundary or line break (NOT_IN_A_TOKEN). A POSTMETA or USERMETA span may hold a double quote or
	 * `>`, which a meta key allows, but still no `<` or line break (NOT_IN_A_META_TOKEN). An opener that
	 * starts no token is text, and what follows it is scanned again on its own, so a literal «« in one
	 * field never swallows the text up to a »» in another, and the tokens after a stray opener still
	 * convert. A closer with no opener is text.
	 *
	 * @param string   $text   The text.
	 * @param callable $change Receives a whole token, returns what it becomes.
	 *
	 * @return string
	 */
	private function map_tokens( $text, callable $change ) {

		$parts = preg_split( self::TOKEN_DELIMITERS, $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( false === $parts ) {
			return $text;
		}

		$output = '';
		$count  = count( $parts );

		for ( $start = 0; $start < $count; ++$start ) {

			if ( ! $this->opens_token( $parts[ $start ] ) ) {
				$output .= $parts[ $start ];
				continue;
			}

			$end = $this->closing_part( $parts, $start );

			if ( null !== $end ) {
				$token = implode( '', array_slice( $parts, $start, $end - $start + 1 ) );

				// A post or user meta key may hold a double quote or `>`, so a meta token allows those
				// two; it still never crosses a tag opening or a line break. JSON boundaries were
				// already isolated in replace_strings().
				$is_meta   = 1 === preg_match( '/^(?:\{\{|««)(?:POSTMETA(?::|¦)(?:[0-9]+|\[\[[^\]]+\]\])(?::|¦)|USERMETA(?::|¦))/', $token );
				$not_token = $is_meta ? self::NOT_IN_A_META_TOKEN : self::NOT_IN_A_TOKEN;
				if ( 1 !== preg_match( $not_token, $token ) ) {
					$output .= $change( $token );
					$start   = $end;
					continue;
				}
			}

			$output .= $parts[ $start ];
		}

		return $output;
	}

	/**
	 * Whether a part of the split text is a token opener.
	 *
	 * @param string $part The part.
	 *
	 * @return bool
	 */
	private function opens_token( $part ) {
		return '{{' === $part || '««' === $part;
	}

	/**
	 * The index of the part that closes the opener at an index, counting nested openers.
	 *
	 * @param string[] $parts The split text.
	 * @param int      $start The opener's index.
	 *
	 * @return int|null Null when the opener never closes.
	 */
	private function closing_part( array $parts, $start ) {

		$depth = 0;
		$count = count( $parts );

		for ( $index = $start; $index < $count; ++$index ) {

			if ( $this->opens_token( $parts[ $index ] ) ) {
				++$depth;
			} elseif ( '}}' === $parts[ $index ] || '»»' === $parts[ $index ] ) {
				--$depth;
			}

			if ( 0 === $depth ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * recursiveStrtr
	 *
	 * This function is used to replace strings in a string recursively.
	 *
	 * @param  string $str
	 * @param  array $replacements
	 * @return string
	 */
	public function recursive_strtr( $str, $replacements ) {
		do {
			$before = $str;
			$str    = strtr( $str, $replacements );
		} while ( $str !== $before );

		return $str;
	}

	/**
	 * replace_square_tokens
	 *
	 * Replaces [[something;something;something]] with {{something:something:something}}. replace_strings() passes it one token at a time.
	 *
	 * @param  string $input
	 * @return string
	 */
	public function replace_postmeta_inner_tokens( $input ) {

		$pattern = '/\[\[(.*?)\]\]/';

		// Use preg_replace_callback to handle dynamic replacements
		$output = preg_replace_callback(
			$pattern,
			function ( $matches ) {
				// Explode the matched string by semicolons and rejoin with colons
				$inner = str_replace( ';', ':', $matches[1] );
				return '{{' . $inner . '}}';
			},
			$input
		);

		return $output;
	}
}

new Migrate_Nested_Tokens( '6.5_nested_tokens' );
