<?php

namespace PressGang\Bosun\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PressGang\Bosun\Detect\ThemeInventory;

class ThemeInventoryTest extends TestCase {

	private function fixture_theme(): string {
		return __DIR__ . '/../fixtures/theme';
	}

	public function test_reads_notable_packages_and_refs_from_lock(): void {
		$inventory = ThemeInventory::from_theme( $this->fixture_theme() );

		$this->assertSame( 'dev-master', $inventory->packages['pressgang-wp/pressgang'] );
		$this->assertSame( 'd221456', $inventory->refs['pressgang-wp/pressgang'] );
		$this->assertSame( '2.5.1', $inventory->packages['timber/timber'] );
		$this->assertArrayNotHasKey( 'twig/twig', $inventory->packages );
	}

	public function test_detects_feature_opt_ins_from_config(): void {
		$inventory = ThemeInventory::from_theme( $this->fixture_theme() );

		$this->assertTrue( $inventory->has_feature( 'template-routing' ) );
		$this->assertTrue( $inventory->has_feature( 'page-templates' ) );
		$this->assertFalse( $inventory->has_feature( 'routes' ) );
	}

	public function test_commented_out_provider_is_not_detected(): void {
		$inventory = ThemeInventory::from_theme( __DIR__ . '/../fixtures/commented' );

		$this->assertFalse( $inventory->has_feature( 'template-routing' ) );
	}

	public function test_missing_lock_yields_empty_inventory(): void {
		$inventory = ThemeInventory::from_theme( __DIR__ . '/../fixtures/empty' );

		$this->assertSame( [], $inventory->packages );
		$this->assertSame( [], $inventory->features );
	}
}
