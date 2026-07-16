<?php

namespace PressGang\Bosun\Mcp;

/**
 * Registers the Capstan MCP server in each editor's MCP config.
 *
 * Bosun owns exactly one key — `mcpServers.pressgang` — and preserves every
 * other key byte-for-byte: the JSON analogue of the managed region
 * {@see \PressGang\Bosun\Agents\AgentTargets} keeps in the guideline files.
 * A malformed config is left untouched for a human, never clobbered.
 *
 * Capstan is a global WP-CLI package, not a theme composer dependency, so it
 * is detected by class presence rather than through the theme inventory. The
 * Claude Code target (`.mcp.json`) is always written; an editor-specific
 * target (`.cursor/mcp.json`) is written only when that editor is already in
 * use here — bosun never litters a repo with config for a tool it isn't using.
 */
final class McpRegistrar {

	/**
	 * The one server key bosun owns inside `mcpServers`.
	 */
	public const SERVER_KEY = 'pressgang';

	/**
	 * Agent key => MCP config target. `if_dir` (relative to the theme) gates
	 * a target on that editor already being in use.
	 *
	 * @var array<string, array{path: string, if_dir?: string}>
	 */
	public const TARGETS = [
		'claude' => [ 'path' => '.mcp.json' ],
		'cursor' => [ 'path' => '.cursor/mcp.json', 'if_dir' => '.cursor' ],
	];

	/**
	 * The server definition editors launch. No `--path`: WP-CLI auto-detects
	 * the WordPress root by walking up from the editor's workspace.
	 *
	 * @return array{command: string, args: array<int, string>, env: object}
	 */
	public static function server(): array {
		return [
			'command' => 'wp',
			'args'    => [ 'capstan', 'mcp', 'serve' ],
			'env'     => (object) [],
		];
	}

	/**
	 * Whether a Capstan able to serve MCP is loaded in this WP-CLI process.
	 */
	public static function capstan_available(): bool {
		return class_exists( \PressGang\Capstan\Commands\McpServeCommand::class );
	}

	/**
	 * Write the registration into each applicable target.
	 *
	 * @param string             $theme_dir Absolute theme path.
	 * @param array<int, string> $agents    Agent keys selected (defaults to all).
	 *
	 * @return array{written: array<int, string>, skipped: array<int, string>}
	 */
	public static function register( string $theme_dir, array $agents = [] ): array {

		$written = [];
		$skipped = [];
		$agents  = $agents ?: array_keys( self::TARGETS );

		foreach ( self::TARGETS as $agent => $target ) {
			if ( ! in_array( $agent, $agents, true ) ) {
				continue;
			}

			// Don't create config for an editor that isn't in use here.
			if ( isset( $target['if_dir'] ) && ! is_dir( "{$theme_dir}/{$target['if_dir']}" ) ) {
				continue;
			}

			$path    = "{$theme_dir}/{$target['path']}";
			$content = self::merged( $path );

			if ( $content === null ) {
				$skipped[] = $path;

				continue;
			}

			if ( ! is_dir( dirname( $path ) ) ) {
				mkdir( dirname( $path ), 0777, true );
			}

			if ( file_put_contents( $path, $content ) !== false ) {
				$written[] = $path;
			}
		}

		return [ 'written' => $written, 'skipped' => $skipped ];
	}

	/**
	 * The full JSON a config should hold after registration, or null when the
	 * existing file is malformed and must be left for a human.
	 *
	 * @param string $path Absolute config path.
	 *
	 * @return string|null
	 */
	protected static function merged( string $path ): ?string {

		$config = [ 'mcpServers' => [] ];

		if ( file_exists( $path ) ) {
			$decoded = json_decode( (string) file_get_contents( $path ), true );

			if ( ! is_array( $decoded ) ) {
				return null; // malformed — preserve, don't clobber
			}

			$config = $decoded;

			if ( ! isset( $config['mcpServers'] ) || ! is_array( $config['mcpServers'] ) ) {
				$config['mcpServers'] = [];
			}
		}

		$config['mcpServers'][ self::SERVER_KEY ] = self::server();

		return json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
	}
}
