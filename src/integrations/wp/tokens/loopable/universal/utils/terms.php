<?php
namespace Uncanny_Automator\Integrations\WP\Tokens\Loopable\Universal\Utils;

/**
 * Class Taxonomy_Fetcher.
 *
 * A class to fetch any taxonomy.
 */
class Taxonomy_Fetcher {

	/**
	 * Fetch terms as an array of associative arrays with 'term_id' and 'term_name'.
	 *
	 * @param string $taxonomy The taxonomy to fetch ('post_tag' or 'category').
	 *
	 * @return array|bool Array of terms with 'term_id' and 'term_name', or false on failure.
	 */
	public static function get_terms_list( $taxonomy ) {

		// Define arguments to fetch all terms, including empty ones.
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false, // Include empty terms.
		);

		// Get all terms using get_terms function with specified arguments.
		$terms = get_terms( $args );

		// Check if there are terms and if no error occurred.
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return false; // Return false if no terms are found or there is an error.
		}

		// Initialize the result array.
		$result = array();

		// Loop through each term and build the associative array.
		foreach ( $terms as $term ) {
			$result[] = array(
				'term_id'   => $term->term_id, // Add term ID to the result array.
				'term_name' => $term->name,  // Add term name to the result array.
			);
		}

		// Return the final result array.
		return $result;
	}
}
