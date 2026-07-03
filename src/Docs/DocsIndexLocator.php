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
			$relative = "vendor/{$package}/docs/api-index.json";
			$file     = "{$inventory->theme_dir}/{$relative}";

			if ( ! is_file( $file ) || filesize( $file ) > static::MAX_BYTES ) {
				continue;
			}

			$index = json_decode( (string) file_get_contents( $file ), true );

			if ( ! is_array( $index ) ) {
				continue;
			}

			$indexes[ $package ] = [
				'path'  => $relative,
				'index' => $index,
			];
		}

		ksort( $indexes );

		return $indexes;
	}
}
