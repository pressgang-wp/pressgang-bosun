<?php

namespace PressGang\Bosun\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PressGang\Bosun\Agents\AgentTargets;

class AgentTargetsTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/bosun-test-' . uniqid();
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		array_map( 'unlink', glob( "{$this->dir}/*" ) ?: [] );
		rmdir( $this->dir );
	}

	private function document(): string {
		return "# Theme Guidelines (" . AgentTargets::MARKER . ")\n\nContent.\n";
	}

	public function test_writes_fresh_targets(): void {
		$result = AgentTargets::write( $this->dir, $this->document() );

		$this->assertCount( 2, $result['written'] );
		$this->assertSame( [], $result['skipped'] );
		$this->assertFileExists( "{$this->dir}/CLAUDE.md" );
		$this->assertFileExists( "{$this->dir}/AGENTS.md" );
	}

	public function test_overwrites_its_own_previous_output(): void {
		AgentTargets::write( $this->dir, $this->document() );

		$result = AgentTargets::write( $this->dir, $this->document() . 'Updated.' );

		$this->assertCount( 2, $result['written'] );
		$this->assertStringContainsString( 'Updated.', file_get_contents( "{$this->dir}/CLAUDE.md" ) );
	}

	public function test_never_clobbers_a_hand_written_file(): void {
		file_put_contents( "{$this->dir}/CLAUDE.md", "# My hand-written notes\n" );

		$result = AgentTargets::write( $this->dir, $this->document() );

		$this->assertSame( [ "{$this->dir}/CLAUDE.md" ], $result['skipped'] );
		$this->assertSame( [ "{$this->dir}/AGENTS.md" ], $result['written'] );
		$this->assertStringContainsString( 'hand-written', file_get_contents( "{$this->dir}/CLAUDE.md" ) );
	}

	public function test_force_overwrites_foreign_files(): void {
		file_put_contents( "{$this->dir}/CLAUDE.md", "# My hand-written notes\n" );

		$result = AgentTargets::write( $this->dir, $this->document(), [], true );

		$this->assertContains( "{$this->dir}/CLAUDE.md", $result['written'] );
		$this->assertStringContainsString( AgentTargets::MARKER, file_get_contents( "{$this->dir}/CLAUDE.md" ) );
	}

	public function test_unknown_agent_keys_are_ignored(): void {
		$result = AgentTargets::write( $this->dir, $this->document(), [ 'copilot' ] );

		$this->assertSame( [], $result['written'] );
	}
}
