<?php

namespace PressGang\Bosun\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PressGang\Bosun\Mcp\McpRegistrar;

class McpRegistrarTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/bosun-mcp-' . uniqid();
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		// Recursively clear the temp theme (may contain .cursor/).
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $node ) {
			$node->isDir() ? rmdir( $node->getPathname() ) : unlink( $node->getPathname() );
		}
		rmdir( $this->dir );
	}

	private function mcp_json(): array {
		return json_decode( (string) file_get_contents( "{$this->dir}/.mcp.json" ), true );
	}

	public function test_writes_the_pressgang_server_into_a_fresh_config(): void {
		$result = McpRegistrar::register( $this->dir, [ 'claude' ] );

		$this->assertSame( [ "{$this->dir}/.mcp.json" ], $result['written'] );

		$server = $this->mcp_json()['mcpServers']['pressgang'];
		$this->assertSame( 'wp', $server['command'] );
		$this->assertSame( [ 'capstan', 'mcp', 'serve' ], $server['args'] );
	}

	public function test_preserves_other_servers_and_top_level_keys(): void {
		file_put_contents( "{$this->dir}/.mcp.json", json_encode( [
			'someTopLevel' => true,
			'mcpServers'   => [ 'other' => [ 'command' => 'node', 'args' => [ 'x.js' ] ] ],
		] ) );

		McpRegistrar::register( $this->dir, [ 'claude' ] );
		$config = $this->mcp_json();

		$this->assertTrue( $config['someTopLevel'] );
		$this->assertSame( 'node', $config['mcpServers']['other']['command'] );
		$this->assertArrayHasKey( 'pressgang', $config['mcpServers'] );
	}

	public function test_is_idempotent(): void {
		McpRegistrar::register( $this->dir, [ 'claude' ] );
		$first = (string) file_get_contents( "{$this->dir}/.mcp.json" );

		McpRegistrar::register( $this->dir, [ 'claude' ] );
		$second = (string) file_get_contents( "{$this->dir}/.mcp.json" );

		$this->assertSame( $first, $second );
		$this->assertCount( 1, $this->mcp_json()['mcpServers'] );
	}

	public function test_leaves_malformed_config_untouched(): void {
		file_put_contents( "{$this->dir}/.mcp.json", '{ this is not json' );

		$result = McpRegistrar::register( $this->dir, [ 'claude' ] );

		$this->assertSame( [ "{$this->dir}/.mcp.json" ], $result['skipped'] );
		$this->assertSame( '{ this is not json', (string) file_get_contents( "{$this->dir}/.mcp.json" ) );
	}

	public function test_cursor_target_only_written_when_cursor_in_use(): void {
		// No .cursor/ dir yet — cursor target is skipped.
		McpRegistrar::register( $this->dir, [ 'cursor' ] );
		$this->assertFileDoesNotExist( "{$this->dir}/.cursor/mcp.json" );

		// Once the project uses Cursor, the config is created and kept in sync.
		mkdir( "{$this->dir}/.cursor" );
		McpRegistrar::register( $this->dir, [ 'cursor' ] );
		$this->assertFileExists( "{$this->dir}/.cursor/mcp.json" );
	}
}
