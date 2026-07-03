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

	public function test_shipped_fragment_wins_over_builtin_for_same_id(): void {
		$fragments = $this->locate();

		// The fixture vendor ships pressgang/template-routing.md, so it
		// overrides bosun's built-in for the same fragment id.
		$this->assertStringContainsString(
			'vendor/pressgang-wp/pressgang/resources/bosun/guidelines/template-routing.md',
			$fragments['pressgang/template-routing.md']
		);
	}

	public function test_builtins_apply_for_packages_without_shipped_fragments(): void {
		// quartermaster ships only core.md; snippets ships nothing — bosun's
		// snippets built-in applies.
		$inventory = ThemeInventory::from_theme( __DIR__ . '/../fixtures/theme' );
		$inventory = new ThemeInventory(
			$inventory->theme_dir,
			$inventory->packages + [ 'pressgang-wp/pressgang-snippets' => 'dev-main' ],
			$inventory->refs,
			$inventory->features
		);

		$fragments = ( new FragmentLocator( dirname( __DIR__, 2 ) . '/resources/guidelines' ) )->locate( $inventory );

		$this->assertStringContainsString( 'resources/guidelines/snippets/core.md', $fragments['snippets/core.md'] );
	}

	public function test_theme_local_guidelines_override_by_matching_path(): void {
		$fragments = $this->locate();

		$this->assertStringContainsString( '.ai/guidelines/pressgang/core.md', $fragments['pressgang/core.md'] );
		$this->assertStringContainsString( '.ai/guidelines/house-rules.md', $fragments['house-rules.md'] );
	}

	public function test_shipped_fragments_are_feature_gated_too(): void {
		// The fixture vendor ships pressgang/template-routing.md; without the
		// opt-in it must not appear (and with it, it overrides the built-in).
		$gated = new ThemeInventory( __DIR__ . '/../fixtures/theme', [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [] );
		$locator = new FragmentLocator( dirname( __DIR__, 2 ) . '/resources/guidelines' );

		$this->assertArrayNotHasKey( 'pressgang/template-routing.md', $locator->locate( $gated ) );

		$opted = new ThemeInventory( __DIR__ . '/../fixtures/theme', [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [ 'template-routing' ] );
		$fragments = $locator->locate( $opted );

		$this->assertStringContainsString( 'vendor/pressgang-wp/pressgang', $fragments['pressgang/template-routing.md'] );
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
