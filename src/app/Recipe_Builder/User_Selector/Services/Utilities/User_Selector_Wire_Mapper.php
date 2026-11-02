<?php
// phpcs:disable WordPress.WP.I18n
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\Utilities;

use Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities\Field_Value_Normalizer;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\User_Selector;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Value_Objects\User_Selector_Source;

/**
 * Translate the recipe-builder run-as wire blob (option_code FieldsOutput)
 * to/from the canonical User_Selector service data shape.
 *
 * Single owner of the option_code <-> domain <-> stored-key vocabulary for
 * run-as. Pure translation: never validates, never throws. The aggregate
 * owns validation; the store owns persistence.
 */
final class User_Selector_Wire_Mapper {

	/**
	 * user_data option_code suffix -> camelCase domain key.
	 *
	 * Wire codes are lane-prefixed (CREATE_USER_* | NEW_USER_*); the domain
	 * user_data array (consumed by User_Selector_User_Data) is camelCase.
	 *
	 * @var array
	 */
	private const USER_DATA_SUFFIX_MAP = array(
		'EMAIL'        => 'email',
		'USERNAME'     => 'username',
		'FIRST_NAME'   => 'firstName',
		'LAST_NAME'    => 'lastName',
		'DISPLAY_NAME' => 'displayName',
		'PASSWORD'     => 'password',
		'ROLE'         => 'role',
	);

	/**
	 * Wire FieldsOutput -> save_user_selector() $data shape.
	 *
	 * @param array $fields Option_code-keyed wire blob.
	 * @return array Canonical service data.
	 */
	public function to_service_data( array $fields ): array {
		$source = (string) $this->value( $fields, 'SOURCE' );
		$data   = array( 'source' => $source );

		$is_existing = User_Selector_Source::EXISTING_USER === User_Selector_Source::normalize( $source );

		// Fallback codes are lane-specific; the builder form keeps values of
		// hidden fields, so the blob can carry a stale entry from the
		// non-selected lane — read only the SOURCE lane's fallback.
		if ( $is_existing ) {
			$data['unique_field']       = (string) $this->value( $fields, 'UNIQUE_FIELD' );
			$data['unique_field_value'] = (string) $this->value( $fields, 'UNIQUE_FIELD_VALUE' );
			$data['fallback']           = (string) $this->value( $fields, 'EXISTING_USER_FALLBACK' );
			$data['user_data']          = $this->user_data( $fields, 'CREATE_USER_' );
		} else {
			$data['fallback']          = (string) $this->value( $fields, 'NEW_USER_FALLBACK' );
			$data['prioritized_field'] = (string) $this->value( $fields, 'PRIORITIZED_FIELD' );
			$data['user_data']         = $this->user_data( $fields, 'NEW_USER_' );
		}

		return $data;
	}

	/**
	 * Read a field entry's `value`, or null when absent.
	 *
	 * @param array  $fields Wire blob.
	 * @param string $code   Option code.
	 * @return mixed|null
	 */
	private function value( array $fields, string $code ) {
		$entry = $fields[ $code ] ?? null;
		if ( is_array( $entry ) && array_key_exists( 'value', $entry ) ) {
			return $entry['value'];
		}
		return null;
	}

	/**
	 * Whether the wire blob carries a given option code.
	 *
	 * @param array  $fields Wire blob.
	 * @param string $code   Option code.
	 * @return bool
	 */
	private function has( array $fields, string $code ): bool {
		return isset( $fields[ $code ] ) && is_array( $fields[ $code ] );
	}

	/**
	 * Build the camelCase user_data array from lane-prefixed wire fields.
	 *
	 * LOG_USER_IN is shared (not lane-prefixed) and coerced to a real bool —
	 * the wire sends the checkbox string 'true'/'false' and (bool) 'false' is
	 * true in PHP.
	 *
	 * @param array  $fields Wire blob.
	 * @param string $prefix Lane prefix (`CREATE_USER_` | `NEW_USER_`).
	 * @return array
	 */
	private function user_data( array $fields, string $prefix ): array {
		$user_data = array();
		foreach ( self::USER_DATA_SUFFIX_MAP as $suffix => $key ) {
			$code = $prefix . $suffix;
			if ( $this->has( $fields, $code ) ) {
				$user_data[ $key ] = (string) $this->value( $fields, $code );
			}
		}
		if ( $this->has( $fields, 'LOG_USER_IN' ) ) {
			$user_data['logUserIn'] = $this->to_bool( $this->value( $fields, 'LOG_USER_IN' ) );
		}
		return $user_data;
	}

	/**
	 * Coerce a checkbox wire value to bool.
	 *
	 * Delegates to {@see Field_Value_Normalizer::to_bool()}, which the Field
	 * README declares the single owner of checkbox coercion in both
	 * directions — a second local truth table here would drift from the FE's
	 * isCheckboxValueChecked semantics.
	 *
	 * @param mixed $value Raw wire value.
	 * @return bool
	 */
	private function to_bool( $value ): bool {
		return Field_Value_Normalizer::to_bool( $value );
	}

	/**
	 * Domain User_Selector -> self-describing wire FieldsOutput.
	 *
	 * Emits a full envelope per entry (type, value, readable, miscellaneous
	 * with show_label_in_sentence:false + label) so the FE needs nothing
	 * else. SOURCE is folded in as a radio entry; lane + fallback decide
	 * which entries are present.
	 *
	 * @param User_Selector $sel Loaded selector.
	 * @return array Option_code-keyed FieldsOutput.
	 */
	public function to_wire_fields( User_Selector $sel ): array {
		$source = $sel->get_source()->get_value();
		$fields = array(
			'SOURCE' => $this->entry(
				'radio',
				$source,
				'existing-user' === $source
					? esc_html_x( 'An existing user', 'Run-as', 'uncanny-automator' )
					: esc_html_x( 'A new user', 'Run-as', 'uncanny-automator' ),
				esc_html_x( 'Run actions as', 'Run-as', 'uncanny-automator' )
			),
		);

		if ( $sel->get_source()->is_existing_user() ) {
			$this->project_existing_user( $sel, $fields );
		} else {
			$this->project_new_user( $sel, $fields );
		}

		return $fields;
	}

	/**
	 * Project existing-user lane entries.
	 *
	 * @param User_Selector $sel    Selector.
	 * @param array         $fields Fields accumulator (by reference).
	 * @return void
	 */
	private function project_existing_user( User_Selector $sel, array &$fields ): void {
		$uf                           = $sel->get_unique_field() ? (string) $sel->get_unique_field()->get_value() : '';
		$fields['UNIQUE_FIELD']       = $this->entry( 'select', $uf, ucfirst( $uf ), esc_html_x( 'Match by', 'Run-as', 'uncanny-automator' ) );
		$fields['UNIQUE_FIELD_VALUE'] = $this->entry( 'text', (string) $sel->get_unique_field_value(), '', esc_html_x( 'Value', 'Run-as', 'uncanny-automator' ) );

		$fallback = $sel->get_fallback() ? (string) $sel->get_fallback()->get_value() : '';
		// _x (not esc_html_x): labels ship over JSON and the FE renders them as
		// text — entity-encoding the apostrophe would display literally.
		// phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext
		$fields['EXISTING_USER_FALLBACK'] = $this->entry( 'radio', $fallback, $this->fallback_readable( $fallback ), _x( "If the user doesn't exist", 'Run-as', 'uncanny-automator' ) );

		if ( $sel->get_fallback() && $sel->get_fallback()->creates_new_user() && $sel->get_user_data() ) {
			$this->project_user_data( $sel->get_user_data(), 'CREATE_USER_', $fields );
		}
	}

	/**
	 * Project new-user lane entries.
	 *
	 * @param User_Selector $sel    Selector.
	 * @param array         $fields Fields accumulator (by reference).
	 * @return void
	 */
	private function project_new_user( User_Selector $sel, array &$fields ): void {
		if ( $sel->get_user_data() ) {
			$this->project_user_data( $sel->get_user_data(), 'NEW_USER_', $fields );
		}

		$fallback = $sel->get_fallback() ? (string) $sel->get_fallback()->get_value() : '';
		// phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- JSON wire label, see EXISTING_USER_FALLBACK note.
		$fields['NEW_USER_FALLBACK'] = $this->entry( 'radio', $fallback, $this->fallback_readable( $fallback ), _x( 'If the user already exists', 'Run-as', 'uncanny-automator' ) );

		if ( $sel->get_fallback() && $sel->get_fallback()->selects_existing_user() && $sel->get_prioritized_field() ) {
			$pf                          = (string) $sel->get_prioritized_field()->get_value();
			$fields['PRIORITIZED_FIELD'] = $this->entry( 'select', $pf, ucfirst( $pf ), esc_html_x( 'Match the existing user by', 'Run-as', 'uncanny-automator' ) );
		}
	}

	/**
	 * Project the user_data VO into lane-prefixed text entries + LOG_USER_IN.
	 *
	 * @param \Uncanny_Automator\App\Recipe_Builder\User_Selector\Value_Objects\User_Selector_User_Data $ud     User data.
	 * @param string                                                                                     $prefix Lane prefix.
	 * @param array                                                                                      $fields Accumulator (by reference).
	 * @return void
	 */
	private function project_user_data( $ud, string $prefix, array &$fields ): void {
		$values = array(
			'EMAIL'        => $ud->get_email(),
			'USERNAME'     => $ud->get_username(),
			'FIRST_NAME'   => $ud->get_first_name(),
			'LAST_NAME'    => $ud->get_last_name(),
			'DISPLAY_NAME' => $ud->get_display_name(),
			'PASSWORD'     => $ud->get_password(),
			'ROLE'         => $ud->get_role(),
		);
		foreach ( $values as $suffix => $value ) {
			$type                        = 'EMAIL' === $suffix ? 'email' : 'text';
			$fields[ $prefix . $suffix ] = $this->entry( $type, (string) $value, '', '' );
		}
		$fields['LOG_USER_IN'] = array(
			'type'          => 'checkbox',
			'value'         => $ud->should_log_user_in() ? 'true' : 'false',
			'readable'      => '',
			'miscellaneous' => array(
				'show_label_in_sentence' => false,
				'label'                  => esc_html_x( 'Log the new user in', 'Run-as', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Build one self-describing FieldsOutput entry.
	 *
	 * @param string $type     Input type.
	 * @param string $value    Raw value.
	 * @param string $readable Human-readable selection (radio/select).
	 * @param string $label    Field label.
	 * @return array
	 */
	private function entry( string $type, string $value, string $readable, string $label ): array {
		return array(
			'type'          => $type,
			'value'         => $value,
			'readable'      => $readable,
			'miscellaneous' => array(
				'show_label_in_sentence' => false,
				'label'                  => $label,
			),
		);
	}

	/**
	 * Readable text for a fallback value.
	 *
	 * @param string $fallback Fallback code.
	 * @return string
	 */
	private function fallback_readable( string $fallback ): string {
		$map = array(
			'do-nothing'           => esc_html_x( 'Skip the action', 'Run-as', 'uncanny-automator' ),
			'create-new-user'      => esc_html_x( 'Create a new user', 'Run-as', 'uncanny-automator' ),
			'select-existing-user' => esc_html_x( 'Use the existing user', 'Run-as', 'uncanny-automator' ),
		);
		return $map[ $fallback ] ?? '';
	}
}
