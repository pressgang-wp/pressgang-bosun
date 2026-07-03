<?php

namespace PressGang\Bosun\Guidelines;

use PressGang\Bosun\Detect\ThemeInventory;

/**
 * Locates guideline fragments for a theme, in three tiers:
 *
 * 1. Package-shipped: vendor/{package}/resources/boost/guidelines/**.md
 *    (the same third-party convention Laravel Boost defined, so package
 *    authors write fragments once for any ecosystem).
 * 2. Bosun built-ins: bosun's resources/guidelines/{package-slug}/**.md,
 *    used when the installed package predates shipped fragments. Feature-
 *    gated fragments (e.g. v2/template-routing.md) are included only when
 *    the inventory detected the opt-in.
 * 3. Theme-local: {theme}/.ai/guidelines/**.md — custom additions, and
 *    overrides when the relative path matches an earlier fragment.
 *
 * Feature-gated fragment names apply to tiers 1 and 2; theme-local
 * fragments are never gated — writing the file is the user's opt-in.
 *
 * Returns fragment paths keyed by their relative identity so later tiers
 * override earlier ones.
 */
class FragmentLocator {

	/**
	 * @param string $builtins_dir Bosun's own resources/guidelines directory.
	 */
	public function __construct( protected string $builtins_dir ) {
	}

	/**
	 * Locates the guideline fragments for a theme inventory.
	 *
	 * @param ThemeInventory $inventory
	 *
	 * @return array<string, string> Relative fragment id => absolute path.
	 */
	public function locate( ThemeInventory $inventory ): array {

		$fragments = [];

		// Tier 1: package-shipped fragments (feature-gated like built-ins).
		foreach ( array_keys( $inventory->packages ) as $package ) {
			$dir = "{$inventory->theme_dir}/vendor/{$package}/resources/boost/guidelines";

			foreach ( $this->markdown_in( $dir ) as $relative => $path ) {
				if ( $this->applies( $relative, $inventory ) ) {
					$fragments[ static::slug( $package ) . "/{$relative}" ] = $path;
				}
			}
		}

		// Tier 2: bosun built-ins for installed packages (shipped fragments win).
		foreach ( array_keys( $inventory->packages ) as $package ) {
			$slug = static::slug( $package );

			foreach ( $this->markdown_in( "{$this->builtins_dir}/{$slug}" ) as $relative => $path ) {
				$id = "{$slug}/{$relative}";

				if ( isset( $fragments[ $id ] ) || ! $this->applies( $relative, $inventory ) ) {
					continue;
				}

				$fragments[ $id ] = $path;
			}
		}

		// Tier 3: theme-local custom guidelines and overrides.
		foreach ( $this->markdown_in( "{$inventory->theme_dir}/.ai/guidelines" ) as $relative => $path ) {
			$fragments[ $relative ] = $path;
		}

		ksort( $fragments );

		return $fragments;
	}

	/**
	 * Whether a feature-gated built-in fragment applies to the inventory.
	 *
	 * Fragments named {feature}.md inside a version directory are included
	 * only when the matching feature was detected (e.g. v2/template-routing.md
	 * requires the 'template-routing' opt-in). Ungated fragments always apply.
	 *
	 * @param string         $relative  Fragment path relative to the package slug.
	 * @param ThemeInventory $inventory
	 *
	 * @return bool
	 */
	protected function applies( string $relative, ThemeInventory $inventory ): bool {

		$feature = basename( $relative, '.md' );

		if ( in_array( $feature, [ 'template-routing', 'page-templates', 'routes' ], true ) ) {
			return $inventory->has_feature( $feature );
		}

		return true;
	}

	/**
	 * Markdown files in a directory, keyed by path relative to it.
	 *
	 * @param string $dir
	 *
	 * @return array<string, string>
	 */
	protected function markdown_in( string $dir ): array {

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$files    = [];
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $iterator as $file ) {
			if ( $file->getExtension() === 'md' ) {
				$files[ substr( $file->getPathname(), strlen( $dir ) + 1 ) ] = $file->getPathname();
			}
		}

		ksort( $files );

		return $files;
	}

	/**
	 * A short slug for a package name (vendor prefix and pressgang- stripped).
	 *
	 * @param string $package Package name.
	 *
	 * @return string
	 */
	public static function slug( string $package ): string {
		$slug = str_contains( $package, '/' ) ? explode( '/', $package, 2 )[1] : $package;

		return str_replace( 'pressgang-', '', $slug );
	}
}
