<?php

namespace PressGang\Bosun\Detect;

/**
 * Builds an inventory of a PressGang child theme: installed pressgang
 * packages (with versions and source refs from composer.lock), and feature
 * opt-ins detected from the theme's config files.
 *
 * Pure filesystem/JSON analysis — no WordPress required — so guidelines can
 * be composed from any checkout, and the class is unit-testable.
 */
class ThemeInventory {

	/**
	 * @param string                $theme_dir Absolute path to the child theme.
	 * @param array<string, string> $packages  Package name => version (pressgang-wp/* and notable others).
	 * @param array<string, string> $refs      Package name => short source reference.
	 * @param array<int, string>    $features  Detected feature opt-ins (e.g. 'template-routing').
	 */
	public function __construct(
		public readonly string $theme_dir,
		public readonly array $packages,
		public readonly array $refs,
		public readonly array $features,
	) {
	}

	/**
	 * Builds an inventory from a theme directory.
	 *
	 * @param string $theme_dir Absolute path to the child theme.
	 *
	 * @return self
	 */
	public static function from_theme( string $theme_dir ): self {

		$packages = [];
		$refs     = [];

		$lock_file = "{$theme_dir}/composer.lock";

		if ( is_readable( $lock_file ) ) {
			$lock = json_decode( (string) file_get_contents( $lock_file ), true ) ?: [];

			foreach ( array_merge( $lock['packages'] ?? [], $lock['packages-dev'] ?? [] ) as $package ) {
				if ( self::is_notable( $package['name'] ?? '' ) ) {
					$packages[ $package['name'] ] = $package['version'] ?? 'unknown';
					$refs[ $package['name'] ]     = substr( $package['source']['reference'] ?? '', 0, 7 );
				}
			}
		}

		return new self( $theme_dir, $packages, $refs, self::detect_features( $theme_dir ) );
	}

	/**
	 * Whether a package is worth reporting in agent guidelines.
	 *
	 * @param string $name Package name.
	 *
	 * @return bool
	 */
	protected static function is_notable( string $name ): bool {
		return str_starts_with( $name, 'pressgang-wp/' )
			|| in_array( $name, [ 'timber/timber', 'wpengine/advanced-custom-fields-pro' ], true );
	}

	/**
	 * Detects feature opt-ins from the theme's config files.
	 *
	 * @param string $theme_dir Absolute path to the child theme.
	 *
	 * @return array<int, string>
	 */
	protected static function detect_features( string $theme_dir ): array {

		$features = [];

		$providers = "{$theme_dir}/config/service-providers.php";

		if ( is_readable( $providers ) && str_contains( (string) file_get_contents( $providers ), 'TemplateRoutingServiceProvider' ) ) {
			$features[] = 'template-routing';
		}

		if ( is_readable( "{$theme_dir}/config/page-templates.php" ) ) {
			$features[] = 'page-templates';
		}

		if ( is_readable( "{$theme_dir}/config/routes.php" ) ) {
			$features[] = 'routes';
		}

		return $features;
	}

	/**
	 * Whether a package is installed.
	 *
	 * @param string $name Package name.
	 *
	 * @return bool
	 */
	public function has_package( string $name ): bool {
		return isset( $this->packages[ $name ] );
	}

	/**
	 * Whether a feature opt-in was detected.
	 *
	 * @param string $feature Feature key.
	 *
	 * @return bool
	 */
	public function has_feature( string $feature ): bool {
		return in_array( $feature, $this->features, true );
	}
}
