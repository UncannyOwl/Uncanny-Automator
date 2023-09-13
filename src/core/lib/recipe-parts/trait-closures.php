<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Closure
 * @since   3.0
 * @version 3.0
 * @author  Saad S.
 * @package Uncanny_Automator
 */


namespace Uncanny_Automator\Recipe;

/**
 * Trait Closure
 *
 * @package Uncanny_Automator\Recipe
 */
trait Closure {
	/**
	 * Closure Setup. This trait handles closure definitions.
	 */
	use Closure_Setup;


	/**
	 * @param $user_id
	 * @param $closure_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function redirect( $user_id, $closure_data, $recipe_id, $args ) {

		$redirect_url_raw = isset( $closure_data['meta'][ $this->get_closure_meta() ] ) ? $closure_data['meta'][ $this->get_closure_meta() ] : '';

		if ( empty( $redirect_url_raw ) ) {
			return;
		}

		$redirect_url = Automator()->parse->text( $redirect_url_raw, $recipe_id, $user_id, $args );

		// Log the field values.
		Automator()->db->closure->add_entry_meta(
			array(
				'user_id'                  => isset( $args['user_id'] ) ? $args['user_id'] : null,
				'automator_closure_id'     => isset( $closure_data['ID'] ) ? $closure_data['ID'] : null,
				'automator_closure_log_id' => isset( $args['closure_log_id'] ) ? $args['closure_log_id'] : null,
			),
			'field_values',
			wp_json_encode(
				array(
					'raw'    => $redirect_url_raw,
					'parsed' => $redirect_url,
				)
			)
		);

		//if ( Automator()->helpers->recipe->is_ajax() || automator_filter_has_var( 'gform_ajax', INPUT_POST ) ) {
		if ( Automator()->helpers->recipe->is_ajax() ) {
			update_option( 'UO_REDIRECTURL_' . $user_id, $redirect_url );

			return;
		}
		if ( false === apply_filters( 'automator_recipe_closure_admin_redirect', false, $user_id, $redirect_url ) && is_admin() ) {
			update_option( 'UO_REDIRECTURL_' . $user_id, $redirect_url );

			return;
		}
		?>
		<script>
			let t = setTimeout(function () {
					top.location.replace('<?php echo esc_url_raw( $redirect_url ); ?>')
				},
				1000
			);
		</script>
		<?php
		exit;
	}
}
