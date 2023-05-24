<?php
/**
 * Autoloader registration for the context of rest.
 *
 * @since 4.12
 */
spl_autoload_register(
	function( $class ) {
		$namespace_parts = explode( '\\', $class );
		if ( isset( $namespace_parts[0] ) && isset( $namespace_parts[1] )
			&& 'Uncanny_Automator' !== $namespace_parts[0] && 'Rest' !== $namespace_parts[1] ) {
			return;
		}
		// Resolve the filename.
		$file = strtr(
			strtolower( $class ),
			array(
				'uncanny_automator\\rest' => __DIR__,
				'\\'                      => DIRECTORY_SEPARATOR,
				'_'                       => '-',
			)
		);
		// Require the file.
		$is_rest_file = strpos( $file, str_replace( '\\', DIRECTORY_SEPARATOR, 'uncanny-automator\\src\\core\\services\\rest\\endpoint' ) );
		if ( false !== $is_rest_file && file_exists( $file . '.php' ) ) {
			require_once $file . '.php';
		}
	}
);
