<?php

namespace PressGang\Bosun\Commands;

use PressGang\Bosun\Agents\AgentTargets;
use PressGang\Bosun\Detect\ThemeInventory;
use PressGang\Bosun\Guidelines\FragmentLocator;
use PressGang\Bosun\Guidelines\GuidelineComposer;

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

		if ( empty( $inventory->packages ) ) {
			\WP_CLI::error( "No pressgang packages found in {$theme_dir}/composer.lock — is this a PressGang child theme?" );
		}

		$locator   = new FragmentLocator( dirname( __DIR__, 2 ) . '/resources/guidelines' );
		$fragments = $locator->locate( $inventory );
		$document  = ( new GuidelineComposer() )->compose( $inventory, $fragments );

		$written = AgentTargets::write( $theme_dir, $document, $agents );

		\WP_CLI::log( sprintf( 'Composed %d fragments for %d packages.', count( $fragments ), count( $inventory->packages ) ) );

		foreach ( $written as $file ) {
			\WP_CLI::log( "  wrote {$file}" );
		}

		\WP_CLI::success( 'Guidelines composed. Re-run after composer updates with `wp bosun update`.' );
	}
}
