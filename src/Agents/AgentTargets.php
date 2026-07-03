<?php

namespace PressGang\Bosun\Agents;

/**
 * The agent files bosun writes the composed guidelines to.
 *
 * Kept as a simple map for now; if per-agent formats diverge (editor rules
 * files, frontmatter), promote entries to target classes.
 */
class AgentTargets {

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
	 * Writes the composed document to each selected agent target.
	 *
	 * @param string             $theme_dir Absolute path to the child theme.
	 * @param string             $document  Composed guidelines document.
	 * @param array<int, string> $agents    Agent keys to write (defaults to all).
	 *
	 * @return array<int, string> The files written (absolute paths).
	 */
	public static function write( string $theme_dir, string $document, array $agents = [] ): array {

		$written = [];
		$agents  = $agents ?: array_keys( self::TARGETS );

		foreach ( $agents as $agent ) {
			if ( ! isset( self::TARGETS[ $agent ] ) ) {
				continue;
			}

			$path = "{$theme_dir}/" . self::TARGETS[ $agent ];

			file_put_contents( $path, $document );

			$written[] = $path;
		}

		return $written;
	}
}
