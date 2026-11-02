<?php
/**
 * Recognise meta keys named like credentials.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities;

/**
 * Only the key's own name is judged. Stored values are shown as the Custom
 * Fields panel shows them; guessing at secrets inside values hid ordinary text
 * such as "reset your password" and still missed real secrets. Matching errs
 * towards withholding: a key named secretary_note is treated as a credential.
 */
final class Post_Meta_Credentials {

	/**
	 * Credential words anywhere in a key, joined or separated. A separator of up
	 * to two characters may split the "key" pairs, as in api_key or signing-key.
	 */
	private const NAME = '/pass(?:word|wd|phrase)|secret|token|credential|bearer|authorization|(?:api|private|access|auth|license|signing|encryption|consumer|hmac|ssh|webhook)[^a-z0-9]{0,2}key/i';

	/** Short words that name a credential only as a whole word of a key, as in smtp_pwd or basic_auth. */
	private const WORDS = array( 'pass', 'pwd', 'salt', 'auth' );

	/** Whether a key names a credential. */
	public static function is_credential_key( string $key ): bool {
		if ( 1 === preg_match( self::NAME, $key ) ) {
			return true;
		}
		$spaced = (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1 $2', $key );
		$words  = preg_split( '/[^a-z0-9]+/', strtolower( $spaced ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( is_array( $words ) ? $words : array() as $word ) {
			if ( in_array( $word, self::WORDS, true ) || in_array( rtrim( $word, 's' ), self::WORDS, true ) ) {
				return true;
			}
		}
		return false;
	}
}
