<?php
namespace Uncanny_Automator\Integrations\Thrive_Architect;

/**
 * Class Thrive_Architect_Helpers
 *
 * @package Uncanny_Automator\Integrations\Thrive_Architect
 */
class Thrive_Architect_Helpers {

	/**
	 * Handle the given value as string for safe token rendering.
	 *
	 * @param string $token_value
	 *
	 * @return string
	 */
	public static function handle_as_token( $token_value = '' ) {

		if ( is_scalar( $token_value ) ) {
			return (string) $token_value;
		}

		if ( is_iterable( $token_value ) ) {
			return implode( ', ', (array) $token_value );
		}

		return '';

	}

	/**
	 * Determines whether the dependecies are ready or not.
	 *
	 * @return bool
	 */
	public static function is_dependencies_ready() {
		return class_exists( '\TCB\inc\helpers\FormSettings' )
			&& method_exists( '\TCB\inc\helpers\FormSettings', 'get_one' );
	}

	/**
	 * Extracts form properties. This method is use so we can extract the post id and form identifier.
	 * And later on use it in the token so we dont have to iterate the results again.
	 *
	 * @param mixed $form_id
	 * @return array
	 */
	public static function extract_form_properties( $form_id ) {

		$parts = (array) explode( '|', $form_id );

		return array(
			'form_post_id'    => isset( $parts[0] ) ? $parts[0] : '',
			'form_identifier' => isset( $parts[1] ) ? $parts[1] : '',
		);

	}

	/**
	 * Retrieve all forms from Thrive Architect.
	 *
	 * This code was extracted from \TCB\Integrations\Automator::get_options_callback
	 *
	 * @see thrive-visual-editor/inc/automator/fields/class-form-identifier-data-field.php
	 *
	 * @return array
	 */
	public function get_forms() {

		if ( ! class_exists( '\TCB\inc\helpers\FormSettings' ) ) {
			return array();
		}

		$form_query = new \WP_Query(
			array(
				'post_type'      => \TCB\inc\helpers\FormSettings::POST_TYPE,
				'fields'         => 'id=>parent',
				'posts_per_page' => '-1',
				'post_status'    => 'draft',
			)
		);

		$options = array(
			array(
				'text'  => _x( 'Any form', 'Thrive Architect', 'uncanny-automator' ),
				'value' => -1,
			),
		);

		foreach ( $form_query->posts as $form_post ) {

			$form_settings = \TCB\inc\helpers\FormSettings::get_one( $form_post->ID );

			if ( empty( $form_settings ) ) {
				return array();
			}

			$post = get_post( $form_post->post_parent );

			if ( ! empty( $post ) && $post->post_status !== 'trash' ) {

				$saved_identifier = $form_settings->form_identifier;

				if ( empty( $saved_identifier ) && ! empty( $form_post->post_parent ) ) {

					$form_identifier           = ( empty( $post->post_name ) ? '' : $post->post_name ) . '-form-' . substr( uniqid( '', true ), - 6, 6 );
					$config                    = $form_settings->get_config( false );
					$config['form_identifier'] = $form_identifier;
					$post_title                = 'Form settings' . ( $form_post->post_parent ? ' for content ' . $form_post->post_parent : '' );
					$form_settings->set_config( $config )
							->save( $post_title, array( 'post_parent' => $form_post->post_parent ) );
				}

				$form_id = $form_settings->form_identifier;

				$options[] = array(
					'text'  => $form_id,
					'value' => $form_post->ID . '|' . $form_id,
				);
			}
		}

		return $options;

	}

}
