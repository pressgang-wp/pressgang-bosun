<?php

namespace PressGang\Bosun\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PressGang\Bosun\Detect\ThemeInventory;
use PressGang\Bosun\Skills\SkillInstaller;

class SkillInstallerTest extends TestCase {

	private string $theme;

	protected function setUp(): void {
		$this->theme = sys_get_temp_dir() . '/bosun-skill-test-' . uniqid();
		mkdir( "{$this->theme}/.ai/skills/local-skill", 0755, true );
		file_put_contents( "{$this->theme}/.ai/skills/local-skill/SKILL.md", "---\nname: local-skill\n---\nLocal.\n" );
	}

	protected function tearDown(): void {
		exec( 'rm -rf ' . escapeshellarg( $this->theme ) );
	}

	private function installer(): SkillInstaller {
		return new SkillInstaller( dirname( __DIR__, 2 ) . '/resources/skills' );
	}

	private function inventory(): ThemeInventory {
		return new ThemeInventory( $this->theme, [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [] );
	}

	public function test_locates_builtin_and_theme_local_skills(): void {
		$skills = $this->installer()->locate( $this->inventory() );

		$this->assertArrayHasKey( 'pressgang-theme-build', $skills );
		$this->assertArrayHasKey( 'local-skill', $skills );
	}

	public function test_theme_local_skill_overrides_builtin_of_same_name(): void {
		mkdir( "{$this->theme}/.ai/skills/pressgang-theme-build", 0755, true );
		file_put_contents( "{$this->theme}/.ai/skills/pressgang-theme-build/SKILL.md", "Custom.\n" );

		$skills = $this->installer()->locate( $this->inventory() );

		$this->assertStringContainsString( '.ai/skills/pressgang-theme-build', $skills['pressgang-theme-build'] );
	}

	public function test_installs_skills_to_claude_skills_directory(): void {
		$installer = $this->installer();

		$installed = $installer->install( $this->theme, $installer->locate( $this->inventory() ) );

		$this->assertContains( 'pressgang-theme-build', $installed );
		$this->assertFileExists( "{$this->theme}/.claude/skills/pressgang-theme-build/SKILL.md" );
		$this->assertFileExists( "{$this->theme}/.claude/skills/local-skill/SKILL.md" );
	}

	public function test_feature_gated_skill_excluded_without_opt_in(): void {
		$skills = $this->installer()->locate( $this->inventory() );

		$this->assertArrayNotHasKey( 'pressgang-v1-migration', $skills );
	}

	public function test_feature_gated_skill_included_with_opt_in(): void {
		$legacy = new ThemeInventory( $this->theme, [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [ 'legacy-v1' ] );

		$skills = $this->installer()->locate( $legacy );

		$this->assertArrayHasKey( 'pressgang-v1-migration', $skills );
	}

	public function test_prunes_stale_managed_skills_but_never_hand_installed_ones(): void {
		$installer = $this->installer();

		// First run on a legacy theme installs the migration skill.
		$legacy = new ThemeInventory( $this->theme, [ 'pressgang-wp/pressgang' => 'dev-master' ], [], [ 'legacy-v1' ] );
		$installer->install( $this->theme, $installer->locate( $legacy ) );
		$this->assertFileExists( "{$this->theme}/.claude/skills/pressgang-v1-migration/SKILL.md" );

		// A hand-installed skill bosun knows nothing about.
		mkdir( "{$this->theme}/.claude/skills/hand-made", 0755, true );
		file_put_contents( "{$this->theme}/.claude/skills/hand-made/SKILL.md", 'mine' );

		// Second run after migration: legacy feature gone.
		$installer->install( $this->theme, $installer->locate( $this->inventory() ) );

		$this->assertFileDoesNotExist( "{$this->theme}/.claude/skills/pressgang-v1-migration/SKILL.md" );
		$this->assertFileExists( "{$this->theme}/.claude/skills/hand-made/SKILL.md" );
	}

	public function test_frontmatter_gate_survives_crlf_line_endings(): void {
		mkdir( "{$this->theme}/vendor/pressgang-wp/pressgang/resources/boost/skills/crlf-gated", 0755, true );
		file_put_contents(
			"{$this->theme}/vendor/pressgang-wp/pressgang/resources/boost/skills/crlf-gated/SKILL.md",
			"---\r\nname: crlf-gated\r\nrequires-feature: legacy-v1\r\n---\r\nGated.\r\n"
		);

		$this->assertArrayNotHasKey( 'crlf-gated', $this->installer()->locate( $this->inventory() ) );
	}

	public function test_reinstall_replaces_rather_than_overlays(): void {
		$installer = $this->installer();

		$installer->install( $this->theme, $installer->locate( $this->inventory() ) );
		file_put_contents( "{$this->theme}/.claude/skills/local-skill/removed-from-source.md", 'stale' );

		$installer->install( $this->theme, $installer->locate( $this->inventory() ) );

		$this->assertFileDoesNotExist( "{$this->theme}/.claude/skills/local-skill/removed-from-source.md" );
	}

	public function test_directories_without_skill_md_are_ignored(): void {
		mkdir( "{$this->theme}/.ai/skills/not-a-skill", 0755, true );
		file_put_contents( "{$this->theme}/.ai/skills/not-a-skill/notes.txt", 'x' );

		$this->assertArrayNotHasKey( 'not-a-skill', $this->installer()->locate( $this->inventory() ) );
	}
}
