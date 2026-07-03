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
	 *
	 * @return string
	 */
	public function compose( ThemeInventory $inventory, array $fragments ): string {

		$sections = [ $this->inventory_summary( $inventory ) ];

		foreach ( $fragments as $id => $path ) {
			$sections[] = "<!-- bosun:fragment {$id} -->\n" . trim( (string) file_get_contents( $path ) );
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
			'# Theme Guidelines (composed by pressgang-bosun)',
			'',
			'Generated from this theme\'s installed packages and configuration.',
			'Regenerate with `wp bosun update` — do not edit by hand.',
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
}
