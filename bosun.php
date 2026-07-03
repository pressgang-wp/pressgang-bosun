<?php

/**
 * WP-CLI entry point. Registers the bosun commands when running under WP-CLI.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::add_command( 'bosun install', \PressGang\Bosun\Commands\InstallCommand::class );
WP_CLI::add_command( 'bosun update', \PressGang\Bosun\Commands\UpdateCommand::class );
