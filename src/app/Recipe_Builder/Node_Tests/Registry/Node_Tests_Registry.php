<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Registry;

use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Provider;

/**
 * Runtime registry of node-test providers (Block_Type_Registry pattern).
 *
 * Populated at boot: Free providers from Node_Tests_Bootstrap, Pro
 * providers from Pro's bootstrap. Catalog `has_tests` and the node-tests
 * REST dispatch both answer from this one lookup — no feed / complete.json
 * involvement anywhere.
 */
final class Node_Tests_Registry {

	/**
	 * Providers keyed by "kind|integration_code|code" — either an
	 * instantiated Node_Test_Provider or a not-yet-instantiated class-string.
	 *
	 * @var array<string, Node_Test_Provider|class-string<Node_Test_Provider>>
	 */
	private static array $providers = array();

	/**
	 * Register one provider under its declared identity.
	 *
	 * Registering the same identity twice overwrites (last wins) — Pro
	 * deliberately replaces a Free provider this way if it ever needs to.
	 *
	 * @param Node_Test_Provider $provider The provider.
	 * @return void
	 */
	public static function register( Node_Test_Provider $provider ): void {
		self::$providers[ self::key( $provider->kind(), $provider->integration_code(), $provider->code() ) ] = $provider;
	}

	/**
	 * Register a provider class-string under its declared identity, without
	 * instantiating it — the class autoloads and constructs lazily, on the
	 * first {@see self::provider_for()} lookup.
	 *
	 * The identity args must match, byte-exact, what the provider's own
	 * kind()/integration_code()/code() return once instantiated — a
	 * mismatch fails silently, the same as any wrong code (see
	 * Node_Test_Provider's docblock). They are required here, rather than
	 * derived by instantiating the class, so registration stays
	 * autoload-cold: boot never constructs a provider it doesn't need yet.
	 *
	 * @param string $provider_class   Node_Test_Provider implementation class-string.
	 * @param string $kind             item | block | block_modifier.
	 * @param string $integration_code Integration code ('' for blocks).
	 * @param string $code             Item/modifier code or block_type.
	 * @return void
	 */
	public static function register_class( string $provider_class, string $kind, string $integration_code, string $code ): void {
		self::$providers[ self::key( $kind, $integration_code, $code ) ] = $provider_class;
	}

	/**
	 * The provider for an identity, or null.
	 *
	 * A class-string entry is instantiated on first lookup and the
	 * instance replaces the class-string in the map (memoized) so later
	 * lookups for the same identity return the same instance.
	 *
	 * @param string $kind             item | block | block_modifier.
	 * @param string $integration_code Integration code ('' for blocks).
	 * @param string $code             Item/modifier code or block_type.
	 * @return Node_Test_Provider|null
	 */
	public static function provider_for( string $kind, string $integration_code, string $code ): ?Node_Test_Provider {
		$provider_key = self::key( $kind, $integration_code, $code );
		$entry        = self::$providers[ $provider_key ] ?? null;

		if ( null === $entry ) {
			return null;
		}

		if ( is_string( $entry ) ) {
			$entry                            = new $entry();
			self::$providers[ $provider_key ] = $entry;
		}

		return $entry;
	}

	/**
	 * Whether a provider is registered for the identity — the catalog's
	 * has_tests. Never instantiates a class-string entry; existence is a
	 * key lookup only.
	 *
	 * @param string $kind             item | block | block_modifier.
	 * @param string $integration_code Integration code ('' for blocks).
	 * @param string $code             Item/modifier code or block_type.
	 * @return bool
	 */
	public static function has_tests( string $kind, string $integration_code, string $code ): bool {
		return array_key_exists( self::key( $kind, $integration_code, $code ), self::$providers );
	}

	/**
	 * Drop every registration (test isolation).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$providers = array();
	}

	/**
	 * The composite lookup key.
	 *
	 * @param string $kind             Node kind.
	 * @param string $integration_code Integration code.
	 * @param string $code             Entity code.
	 * @return string
	 */
	private static function key( string $kind, string $integration_code, string $code ): string {
		return $kind . '|' . $integration_code . '|' . $code;
	}
}
