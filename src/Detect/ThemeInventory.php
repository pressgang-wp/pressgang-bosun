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
	 * @param string                $theme_dir    Absolute path to the child theme.
	 * @param array<string, string> $packages     Package name => version (pressgang-wp/* and notable others).
	 * @param array<string, string> $refs         Package name => short source reference.
	 * @param array<int, string>    $features     Detected feature opt-ins (e.g. 'template-routing').
	 * @param array<string, string> $package_dirs Package name => resolved install directory, where
	 *                                            it differs from the vendor/{name} default.
	 */
	public function __construct(
		public readonly string $theme_dir,
		public readonly array $packages,
		public readonly array $refs,
		public readonly array $features,
		public readonly array $package_dirs = [],
	) {
	}

	/**
	 * A package's install directory.
	 *
	 * Composer's installer-paths can place packages outside vendor — the
	 * pressgang parent theme (type wordpress-theme) installs to
	 * wp-content/themes/pressgang. Paths resolved from
	 * vendor/composer/installed.json take precedence; anything else falls
	 * back to the vendor/{name} default.
	 *
	 * @param string $name Package name.
	 *
	 * @return string
	 */
	public function package_dir( string $name ): string {
		return $this->package_dirs[ $name ] ?? "{$this->theme_dir}/vendor/{$name}";
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

		return new self(
			$theme_dir,
			$packages,
			$refs,
			self::detect_features( $theme_dir ),
			self::installed_package_dirs( $theme_dir, array_keys( $packages ) )
		);
	}

	/**
	 * Resolves notable packages' install directories from Composer's
	 * vendor/composer/installed.json, which records the true install-path
	 * even when installer-paths place a package outside vendor.
	 *
	 * @param string             $theme_dir Absolute path to the child theme.
	 * @param array<int, string> $notable   Package names to resolve.
	 *
	 * @return array<string, string> Package name => resolved directory.
	 */
	protected static function installed_package_dirs( string $theme_dir, array $notable ): array {

		$file = "{$theme_dir}/vendor/composer/installed.json";

		if ( ! is_readable( $file ) ) {
			return [];
		}

		$installed = json_decode( (string) file_get_contents( $file ), true );
		$dirs      = [];

		foreach ( $installed['packages'] ?? [] as $package ) {
			$name = $package['name'] ?? '';

			if ( ! in_array( $name, $notable, true ) || empty( $package['install-path'] ) ) {
				continue;
			}

			$resolved = realpath( "{$theme_dir}/vendor/composer/{$package['install-path']}" );

			if ( $resolved !== false ) {
				$dirs[ $name ] = $resolved;
			}
		}

		return $dirs;
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

		if ( is_readable( $providers ) && str_contains( self::executable_code( $providers ), 'TemplateRoutingServiceProvider' ) ) {
			$features[] = 'template-routing';
		}

		if ( is_readable( "{$theme_dir}/config/page-templates.php" ) ) {
			$features[] = 'page-templates';
		}

		if ( is_readable( "{$theme_dir}/config/routes.php" ) ) {
			$features[] = 'routes';
		}

		// A PressGang v1 boot file marks a theme awaiting migration.
		if ( is_readable( "{$theme_dir}/core/settings.php" ) ) {
			$features[] = 'legacy-v1';
		}

		return $features;
	}

	/**
	 * A file's PHP source with comments stripped, so commented-out lines
	 * never trigger feature detection. Lexes only — never executes.
	 *
	 * @param string $file Absolute file path.
	 *
	 * @return string
	 */
	protected static function executable_code( string $file ): string {

		$code = '';

		foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}

			$code .= is_array( $token ) ? $token[1] : $token;
		}

		return $code;
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
