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

	public function test_directories_without_skill_md_are_ignored(): void {
		mkdir( "{$this->theme}/.ai/skills/not-a-skill", 0755, true );
		file_put_contents( "{$this->theme}/.ai/skills/not-a-skill/notes.txt", 'x' );

		$this->assertArrayNotHasKey( 'not-a-skill', $this->installer()->locate( $this->inventory() ) );
	}
}
