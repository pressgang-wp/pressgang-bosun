<?php

namespace PressGang\Bosun\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PressGang\Bosun\Detect\ThemeInventory;
use PressGang\Bosun\Guidelines\FragmentLocator;

class FragmentLocatorTest extends TestCase {

	private function locate(): array {
		$inventory = ThemeInventory::from_theme( __DIR__ . '/../fixtures/theme' );
		$locator   = new FragmentLocator( dirname( __DIR__, 2 ) . '/resources/guidelines' );

		return $locator->locate( $inventory );
	}

	public function test_package_shipped_fragments_win_over_builtins(): void {
		$fragments = $this->locate();

		$this->assertStringContainsString(
			'fixtures/theme/vendor/pressgang-wp/quartermaster',
			$fragments['quartermaster/core.md']
		);
	}

	public function test_builtins_apply_for_packages_without_shipped_fragments(): void {
		$fragments = $this->locate();

		// pressgang ships no fragments in the fixture; the feature-gated
		// routing built-in applies because the opt-in was detected.
		$this->assertStringContainsString(
			'resources/guidelines/pressgang/template-routing.md',
			$fragments['pressgang/template-routing.md']
		);
	}

	public function test_theme_local_guidelines_override_by_matching_path(): void {
		$fragments = $this->locate();

		$this->assertStringContainsString( '.ai/guidelines/pressgang/core.md', $fragments['pressgang/core.md'] );
		$this->assertStringContainsString( '.ai/guidelines/house-rules.md', $fragments['house-rules.md'] );
	}

	public function test_feature_gated_fragments_excluded_without_opt_in(): void {
		$inventory = new ThemeInventory( __DIR__ . '/../fixtures/theme', [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [] );
		$locator   = new FragmentLocator( dirname( __DIR__, 2 ) . '/resources/guidelines' );

		$this->assertArrayNotHasKey( 'pressgang/template-routing.md', $locator->locate( $inventory ) );
	}

	public function test_slug_strips_vendor_and_pressgang_prefix(): void {
		$this->assertSame( 'pressgang', FragmentLocator::slug( 'pressgang-wp/pressgang' ) );
		$this->assertSame( 'snippets', FragmentLocator::slug( 'pressgang-wp/pressgang-snippets' ) );
		$this->assertSame( 'quartermaster', FragmentLocator::slug( 'pressgang-wp/quartermaster' ) );
	}
}
