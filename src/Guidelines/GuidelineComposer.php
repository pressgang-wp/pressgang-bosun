<?php

namespace PressGang\Bosun\Guidelines;

use PressGang\Bosun\Detect\ThemeInventory;

/**
 * Composes located guideline fragments into a single agent guidelines
 * document, headed by an inventory summary of what is actually installed —
 * package versions, source refs, and detected feature opt-ins — so agents
 * reason about the theme's reality rather than the ecosystem's newest ideas.
 */
class GuidelineComposer {

	/**
	 * Composes the guidelines document.
	 *
	 * @param ThemeInventory        $inventory
	 * @param array<string, string> $fragments Relative fragment id => absolute path.
	 * @param array<string, array>  $indexes   Package => API index info, from DocsIndexLocator.
	 *
	 * @return string
	 */
	public function compose( ThemeInventory $inventory, array $fragments, array $indexes = [] ): string {

		$sections = [ $this->inventory_summary( $inventory ) ];

		if ( $indexes ) {
			$sections[] = $this->docs_indexes( $indexes );
		}

		foreach ( $fragments as $id => $path ) {
			$content = trim( (string) file_get_contents( $path ) );

			if ( $content === '' ) {
				continue;
			}

			$sections[] = "<!-- bosun:fragment {$id} -->\n{$content}";
		}

		return implode( "\n\n---\n\n", $sections ) . "\n";
	}

	/**
	 * The inventory summary section.
	 *
	 * @param ThemeInventory $inventory
	 *
	 * @return string
	 */
	protected function inventory_summary( ThemeInventory $inventory ): string {

		$lines = [
			'# ' . basename( $inventory->theme_dir ) . ' — theme guidelines (composed by pressgang-bosun)',
			'',
			'Generated from this theme\'s installed packages and configuration.',
			'Regenerate with `wp bosun update` — do not edit this region by hand.',
			'',
			'## Installed',
			'',
		];

		foreach ( $inventory->packages as $package => $version ) {
			$ref     = $inventory->refs[ $package ] ?? '';
			$lines[] = rtrim( "- {$package} {$version}" . ( $ref ? " ({$ref})" : '' ) );
		}

		$lines[] = '';
		$lines[] = '## Feature opt-ins detected';
		$lines[] = '';
		$lines[] = $inventory->features
			? '- ' . implode( "\n- ", $inventory->features )
			: '- none (explicit template stubs)';

		return implode( "\n", $lines );
	}

	/**
	 * The API docs indexes section.
	 *
	 * Points agents at the machine-readable API indexes packages ship in
	 * vendor, rather than inlining them — the vendor file stays the single
	 * source of truth.
	 *
	 * @param array<string, array{path: string, index: array}> $indexes
	 *
	 * @return string
	 */
	protected function docs_indexes( array $indexes ): string {

		$lines = [
			'## API indexes',
			'',
			'These packages ship a machine-readable API index. Read the JSON',
			'before writing code against the package — it lists every public',
			'method with its signature and links to the relevant WordPress docs.',
			'',
		];

		foreach ( $indexes as $package => $info ) {
			$detail = array_filter( [
				isset( $info['index']['methods'] ) && is_array( $info['index']['methods'] )
					? count( $info['index']['methods'] ) . ' methods'
					: null,
				isset( $info['index']['entrypoint'] ) && is_string( $info['index']['entrypoint'] )
					? "entrypoint {$info['index']['entrypoint']}"
					: null,
			] );

			$lines[] = "- {$package}: `{$info['path']}`"
				. ( $detail ? ' (' . implode( ', ', $detail ) . ')' : '' );
		}

		return implode( "\n", $lines );
	}
}
