<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Closure
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
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

		$redirect_url = isset( $closure_data['meta'][ $this->get_closure_meta() ] ) ? $closure_data['meta'][ $this->get_closure_meta() ] : '';
		if ( empty( $redirect_url ) ) {
			return;
		}
		$redirect_url = Automator()->parse->text( $redirect_url, $recipe_id, $user_id, $args );
		//if ( Automator()->helpers->recipe->is_ajax() || automator_filter_has_var( 'gform_ajax', INPUT_POST ) ) {
		if ( Automator()->helpers->recipe->is_ajax() ) {
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
