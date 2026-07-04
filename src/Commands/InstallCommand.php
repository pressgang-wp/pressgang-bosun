<?php

namespace PressGang\Bosun\Commands;

use PressGang\Bosun\Agents\AgentTargets;
use PressGang\Bosun\Detect\ThemeInventory;
use PressGang\Bosun\Docs\DocsIndexLocator;
use PressGang\Bosun\Guidelines\FragmentLocator;
use PressGang\Bosun\Guidelines\GuidelineComposer;
use PressGang\Bosun\Skills\SkillInstaller;

/**
 * Composes AI agent guidelines for the active PressGang child theme.
 *
 * ## OPTIONS
 *
 * [--agents=<agents>]
 * : Comma-separated agent targets to write (claude, agents). Default: all.
 *
 * [--theme=<path>]
 * : Theme directory to compose for. Default: the active child theme.
 *
 * [--force]
 * : Rewrite files whose bosun region markers are unbalanced (replaces the
 * whole file with the managed region).
 *
 * [--skip-skills]
 * : Compose guidelines only; do not install agent skills.
 *
 * ## EXAMPLES
 *
 *     wp bosun install
 *     wp bosun install --agents=claude
 */
class InstallCommand {

	/**
	 * @param array<int, string>    $args
	 * @param array<string, string> $assoc_args
	 *
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {

		$theme_dir = $assoc_args['theme'] ?? \get_stylesheet_directory();
		$agents    = isset( $assoc_args['agents'] )
			? array_map( 'trim', explode( ',', $assoc_args['agents'] ) )
			: [];

		$inventory = ThemeInventory::from_theme( $theme_dir );

		// A v1 theme legitimately has no composer packages — bosun still has
		// work to do there (the migration skill).
		if ( empty( $inventory->packages ) && ! $inventory->has_feature( 'legacy-v1' ) ) {
			\WP_CLI::error( "No pressgang packages found in {$theme_dir}/composer.lock — is this a PressGang child theme?" );
		}

		$locator   = new FragmentLocator( dirname( __DIR__, 2 ) . '/resources/guidelines' );
		$fragments = $locator->locate( $inventory );
		$indexes   = ( new DocsIndexLocator() )->locate( $inventory );
		$document  = ( new GuidelineComposer() )->compose( $inventory, $fragments, $indexes );

		$result = AgentTargets::write( $theme_dir, $document, $agents, isset( $assoc_args['force'] ) );

		\WP_CLI::log( sprintf( 'Composed %d fragments for %d packages.', count( $fragments ), count( $inventory->packages ) ) );

		foreach ( $result['written'] as $file ) {
			\WP_CLI::log( "  wrote {$file}" );
		}

		foreach ( $result['skipped'] as $file ) {
			\WP_CLI::warning( "skipped {$file} — unbalanced bosun region markers; fix them or pass --force to rewrite the file" );
		}

		if ( ! isset( $assoc_args['skip-skills'] ) ) {
			$installer = new SkillInstaller( dirname( __DIR__, 2 ) . '/resources/skills' );
			$installed = $installer->install( $theme_dir, $installer->locate( $inventory ) );

			if ( $installed ) {
				\WP_CLI::log( 'Installed skills: ' . implode( ', ', $installed ) );
			}
		}

		\WP_CLI::success( 'Guidelines composed. Re-run after composer updates with `wp bosun update`.' );
	}
}
