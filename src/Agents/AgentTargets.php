<?php

namespace PressGang\Bosun\Agents;

/**
 * The agent files bosun writes the composed guidelines to.
 *
 * Bosun owns a marked region inside each target, never the whole file:
 * content between the region markers is replaced on every run, and anything
 * outside them is preserved byte-for-byte. A hand-written file gains the
 * region appended at the end; a file bosun created is just the region. The
 * only file bosun refuses to touch is one with unbalanced markers — that
 * needs a human (or --force, which rewrites the file as region-only).
 *
 * Kept as a simple map for now; if per-agent formats diverge, promote
 * entries to target classes.
 */
class AgentTargets {

	/**
	 * Managed-region delimiters. Everything between them belongs to bosun.
	 */
	public const REGION_START = '<!-- bosun:start -->';
	public const REGION_END   = '<!-- bosun:end -->';

	/**
	 * The marker present in the composed document's header. Also identifies
	 * pre-region bosun output (which owned the whole file) for migration.
	 */
	public const MARKER = 'composed by pressgang-bosun';

	/**
	 * Agent key => guidelines filename relative to the theme root.
	 *
	 * @var array<string, string>
	 */
	public const TARGETS = [
		'claude' => 'CLAUDE.md',
		'agents' => 'AGENTS.md',
	];

	/**
	 * Writes the composed document into each selected agent target's
	 * managed region.
	 *
	 * @param string             $theme_dir Absolute path to the child theme.
	 * @param string             $document  Composed guidelines document.
	 * @param array<int, string> $agents    Agent keys to write (defaults to all).
	 * @param bool               $force     Rewrite files with unbalanced markers as region-only.
	 *
	 * @return array{written: array<int, string>, skipped: array<int, string>}
	 */
	public static function write( string $theme_dir, string $document, array $agents = [], bool $force = false ): array {

		$written = [];
		$skipped = [];
		$agents  = $agents ?: array_keys( self::TARGETS );

		// A literal marker inside the document (e.g. a fragment documenting
		// bosun itself) would corrupt the next run's region replace.
		$document = str_replace( [ self::REGION_START, self::REGION_END ], '', $document );
		$region   = self::REGION_START . "\n" . rtrim( $document ) . "\n" . self::REGION_END;

		foreach ( $agents as $agent ) {
			if ( ! isset( self::TARGETS[ $agent ] ) ) {
				continue;
			}

			$path    = "{$theme_dir}/" . self::TARGETS[ $agent ];
			$content = self::merged( $path, $region, $force );

			if ( $content === null ) {
				$skipped[] = $path;

				continue;
			}

			if ( file_put_contents( $path, $content ) !== false ) {
				$written[] = $path;
			}
		}

		return [ 'written' => $written, 'skipped' => $skipped ];
	}

	/**
	 * The full file content a target should hold after this run, or null
	 * when the file must be left alone (unbalanced markers, no --force).
	 *
	 * @param string $path   Absolute target path.
	 * @param string $region The new managed region (markers included).
	 * @param bool   $force  Rewrite unbalanced files as region-only.
	 *
	 * @return string|null
	 */
	protected static function merged( string $path, string $region, bool $force ): ?string {

		if ( ! file_exists( $path ) ) {
			return $region . "\n";
		}

		$existing = (string) file_get_contents( $path );
		$start    = strpos( $existing, self::REGION_START );
		$end      = strpos( $existing, self::REGION_END );

		// Duplicated markers mean the file was hand-mangled; guessing which
		// pair is the region risks eating hand-written content.
		if ( substr_count( $existing, self::REGION_START ) > 1 || substr_count( $existing, self::REGION_END ) > 1 ) {
			return $force ? $region . "\n" : null;
		}

		// Replace the existing region, preserving everything around it.
		if ( $start !== false && $end !== false && $end > $start ) {
			return substr( $existing, 0, $start )
				. $region
				. substr( $existing, $end + strlen( self::REGION_END ) );
		}

		// Unbalanced markers: a human (or --force) has to resolve it.
		if ( $start !== false || $end !== false ) {
			return $force ? $region . "\n" : null;
		}

		// Pre-region bosun output owned the whole file — migrate it.
		if ( str_contains( $existing, self::MARKER ) ) {
			return $region . "\n";
		}

		// Hand-written file: append the region, preserving every byte above.
		return rtrim( $existing ) . "\n\n" . $region . "\n";
	}
}
