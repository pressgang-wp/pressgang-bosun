<?php

namespace PressGang\Bosun\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PressGang\Bosun\Detect\ThemeInventory;
use PressGang\Bosun\Guidelines\FragmentLocator;
use PressGang\Bosun\Guidelines\GuidelineComposer;

class GuidelineComposerTest extends TestCase {

	public function test_composes_inventory_summary_and_fragments(): void {
		$inventory = ThemeInventory::from_theme( __DIR__ . '/../fixtures/theme' );
		$locator   = new FragmentLocator( dirname( __DIR__, 2 ) . '/resources/guidelines' );

		$document = ( new GuidelineComposer() )->compose( $inventory, $locator->locate( $inventory ) );

		$this->assertStringContainsString( 'pressgang-wp/pressgang dev-master (d221456)', $document );
		$this->assertStringContainsString( '- template-routing', $document );
		$this->assertStringContainsString( '<!-- bosun:fragment quartermaster/core.md -->', $document );
		$this->assertStringContainsString( '## Shipped quartermaster fragment', $document );
		$this->assertStringContainsString( '## Local override of pressgang core', $document );
		$this->assertStringContainsString( 'Shipped routing guidance', $document );
	}

	public function test_empty_fragments_are_dropped(): void {
		$inventory = new ThemeInventory( '/tmp/x', [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [] );
		$empty     = tempnam( sys_get_temp_dir(), 'bosun' );
		file_put_contents( $empty, "  \n\n " );

		$document = ( new GuidelineComposer() )->compose( $inventory, [ 'x/empty.md' => $empty ] );
		unlink( $empty );

		$this->assertStringNotContainsString( 'bosun:fragment', $document );
	}

	public function test_no_features_reads_as_stub_built(): void {
		$inventory = new ThemeInventory( '/tmp/x', [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [] );

		$document = ( new GuidelineComposer() )->compose( $inventory, [] );

		$this->assertStringContainsString( 'none (explicit template stubs)', $document );
	}
}
