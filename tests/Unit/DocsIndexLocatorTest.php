<?php

namespace PressGang\Bosun\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PressGang\Bosun\Detect\ThemeInventory;
use PressGang\Bosun\Docs\DocsIndexLocator;

class DocsIndexLocatorTest extends TestCase {

	private string $theme;

	protected function setUp(): void {
		$this->theme = sys_get_temp_dir() . '/bosun-docs-test-' . uniqid();
		mkdir( $this->theme, 0755, true );
	}

	protected function tearDown(): void {
		exec( 'rm -rf ' . escapeshellarg( $this->theme ) );
	}

	private function inventory( array $packages ): ThemeInventory {
		return new ThemeInventory( $this->theme, $packages, [], [] );
	}

	private function ship_index( string $package, string $json ): void {
		mkdir( "{$this->theme}/vendor/{$package}/docs", 0755, true );
		file_put_contents( "{$this->theme}/vendor/{$package}/docs/api-index.json", $json );
	}

	public function test_locates_valid_index_with_relative_path(): void {
		$this->ship_index( 'pressgang-wp/quartermaster', json_encode( [
			'entrypoint' => 'PressGang\\Quartermaster\\Quartermaster',
			'methods'    => [ [ 'name' => 'posts' ] ],
		] ) );

		$indexes = ( new DocsIndexLocator() )->locate(
			$this->inventory( [ 'pressgang-wp/quartermaster' => 'dev-main' ] )
		);

		$this->assertSame(
			'vendor/pressgang-wp/quartermaster/docs/api-index.json',
			$indexes['pressgang-wp/quartermaster']['path']
		);
		$this->assertSame( 'posts', $indexes['pressgang-wp/quartermaster']['index']['methods'][0]['name'] );
	}

	public function test_packages_without_an_index_are_skipped(): void {
		$indexes = ( new DocsIndexLocator() )->locate(
			$this->inventory( [ 'pressgang-wp/pressgang' => 'dev-master' ] )
		);

		$this->assertSame( [], $indexes );
	}

	public function test_invalid_json_is_skipped(): void {
		$this->ship_index( 'pressgang-wp/quartermaster', '{not json' );

		$indexes = ( new DocsIndexLocator() )->locate(
			$this->inventory( [ 'pressgang-wp/quartermaster' => 'dev-main' ] )
		);

		$this->assertSame( [], $indexes );
	}

	public function test_scalar_json_is_skipped(): void {
		$this->ship_index( 'pressgang-wp/quartermaster', '"just a string"' );

		$indexes = ( new DocsIndexLocator() )->locate(
			$this->inventory( [ 'pressgang-wp/quartermaster' => 'dev-main' ] )
		);

		$this->assertSame( [], $indexes );
	}

	public function test_oversized_index_is_skipped(): void {
		$this->ship_index(
			'pressgang-wp/quartermaster',
			json_encode( [ 'blob' => str_repeat( 'x', DocsIndexLocator::MAX_BYTES ) ] )
		);

		$indexes = ( new DocsIndexLocator() )->locate(
			$this->inventory( [ 'pressgang-wp/quartermaster' => 'dev-main' ] )
		);

		$this->assertSame( [], $indexes );
	}
}
