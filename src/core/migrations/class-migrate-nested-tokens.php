<?php

namespace Uncanny_Automator\Migrations;

/**
 * Migrate_Nested_Tokens.
 *
 * @package Uncanny_Automator
 */
class Migrate_Nested_Tokens extends Tokens_Migration {

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
	 * @param  string $initial_value
	 * @return string
	 */
	public function replace_strings( $initial_value ) {

		$updated_value = $this->replace_postmeta_inner_tokens( $initial_value );

		$updated_value = $this->recursive_strtr( $updated_value, $this->strings_to_replace() );

		return $updated_value;
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
	 * Replaces [something;something;something] with
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
