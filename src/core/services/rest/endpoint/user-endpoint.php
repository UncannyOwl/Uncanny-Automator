<?php
namespace Uncanny_Automator\Rest\Endpoint;

use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

class User_Endpoint {

	/**
	 * Find user by ID.
	 *
	 * @template Adapter_Of_Array_Access_Port of \WP_REST_Request
	 * @param Adapter_Of_Array_Access_Port $request The instance of request object from WordPress.
	 *
	 * @return WP_REST_Response;
	 */
	public function find_by_id( WP_REST_Request $request ) {

		$id = absint( $request->get_param( 'id' ) );

		try {

			$user = get_user_by( 'ID', $id );

			$this->validate_user( $user );

			if ( $user instanceof WP_User ) {

				$user = array(
					'display_name' => $user->data->display_name,
					'email'        => $user->data->user_email,
					'avatar'       => get_avatar_url( $user->data->ID ),
					'edit_url'     => get_edit_user_link( $user->data->ID ),
				);

				return $this->respond_with_data( true, $user, 200 );

			}

			throw new Exception( 'Invalid user object', 422 );

		} catch ( Exception $e ) {

			$error = array(
				'error_code'    => $e->getCode(),
				'error_message' => $e->getMessage(),
			);

			return $this->respond_with_data( false, $error, 404 );

		}

	}

	/**
	 * @param bool $is_success
	 * @param (int|mixed|string)[] $data
	 * @param int $status_code
	 *
	 * @return WP_REST_Response
	 */
	protected function respond_with_data( $is_success, $data, $status_code ) {

		return new WP_REST_Response(
			array(
				'success' => $is_success,
				'data'    => $data,
			),
			$status_code
		);

	}

	/**
	 * Validates the $user object.
	 *
	 * @param WP_User|false $user The wp user object.
	 *
	 * @throws Exception User not found when the user does not exists.
	 *
	 * @return bool True, if there are no Exceptions.
	 */
	protected function validate_user( $user ) {

		if ( empty( $user ) ) {
			throw new Exception( 'User not found', 404 );
		}

		return true;

	}

}
