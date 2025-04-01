<?php
namespace Uncanny_Automator\Services;

/**
 * Class Html_Partial_Renderer
 *
 * Handles rendering of HTML partial files.
 *
 * @package YourNamespace
 */
class Html_Partial_Renderer {

	/**
	 * @var string
	 */
	protected $html_partial_root_path = '';

	/**
	 * @param string $path
	 *
	 * @return void
	 */
	public function set_html_partial_root_path( $path ) {

		$this->html_partial_root_path = trailingslashit( $path );
	}

	/**
	 * @param mixed $file_name
	 * @param array $vars
	 * @return string|false
	 * @throws RuntimeException
	 */
	public function render_html_partial( $file_name, $vars = array() ) {

		if ( empty( $this->html_partial_root_path ) ) {
			throw new \RuntimeException( 'HTML partial root path is not set.' );
		}

		$file_path = $this->html_partial_root_path . $file_name;

		if ( ! file_exists( $file_path ) ) {
			throw new \RuntimeException( 'Partial file not found: ' . esc_html( $file_name ) );
		}

		include $file_path;
	}
}
