<?php

namespace PressGang\Bosun\Docs;

use PressGang\Bosun\Detect\ThemeInventory;

/**
 * Locates machine-readable API indexes shipped by installed packages
 * (`vendor/{package}/docs/api-index.json`).
 *
 * An index describes a package's public API — method signatures, the query
 * args they set, links to the relevant WordPress docs — in a form agents can
 * read on demand. Bosun doesn't copy the JSON anywhere: the vendor file is
 * the single source of truth, and the composed guidelines point agents at
 * it. Missing, oversized, or malformed indexes are silently skipped — an
 * index is an enhancement, never a requirement.
 */
class DocsIndexLocator {

	/**
	 * Indexes larger than this are skipped rather than surfaced to agents.
	 */
	public const MAX_BYTES = 262144;

	/**
	 * Locates valid API indexes for a theme inventory.
	 *
	 * @param ThemeInventory $inventory
	 *
	 * @return array<string, array{path: string, index: array}> Package name =>
	 *         theme-relative path and decoded index.
	 */
	public function locate( ThemeInventory $inventory ): array {

		$indexes = [];

		foreach ( array_keys( $inventory->packages ) as $package ) {
			$file = $inventory->package_dir( $package ) . '/docs/api-index.json';

			if ( ! is_file( $file ) || filesize( $file ) > static::MAX_BYTES ) {
				continue;
			}

			$index = json_decode( (string) file_get_contents( $file ), true );

			if ( ! is_array( $index ) ) {
				continue;
			}

			$indexes[ $package ] = [
				'path'  => static::relative_path( $inventory->theme_dir, $file ),
				'index' => $index,
			];
		}

		ksort( $indexes );

		return $indexes;
	}

	/**
	 * A file's path relative to a base directory, using ../ when the file
	 * lives outside it (e.g. the parent theme installed beside the child).
	 * Keeps composed guidelines machine-portable — never absolute.
	 *
	 * @param string $base Absolute base directory.
	 * @param string $file Absolute file path.
	 *
	 * @return string
	 */
	protected static function relative_path( string $base, string $file ): string {

		// Realpath both sides so symlinked segments (e.g. macOS /var →
		// /private/var) can't defeat the common-prefix match.
		$base = explode( '/', trim( (string) realpath( $base ), '/' ) );
		$file = explode( '/', trim( (string) ( realpath( $file ) ?: $file ), '/' ) );

		while ( $base && $file && $base[0] === $file[0] ) {
			array_shift( $base );
			array_shift( $file );
		}

		return str_repeat( '../', count( $base ) ) . implode( '/', $file );
	}
}
