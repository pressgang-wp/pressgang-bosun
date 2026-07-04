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
		return "# Theme guidelines (" . AgentTargets::MARKER . ")\n\nContent.\n";
	}

	private function claude(): string {
		return (string) file_get_contents( "{$this->dir}/CLAUDE.md" );
	}

	public function test_fresh_targets_are_written_as_a_region(): void {
		$result = AgentTargets::write( $this->dir, $this->document() );

		$this->assertCount( 2, $result['written'] );
		$this->assertSame( [], $result['skipped'] );
		$this->assertStringStartsWith( AgentTargets::REGION_START, $this->claude() );
		$this->assertStringEndsWith( AgentTargets::REGION_END . "\n", $this->claude() );
	}

	public function test_rerun_replaces_the_region_without_duplicating_it(): void {
		AgentTargets::write( $this->dir, $this->document() );
		AgentTargets::write( $this->dir, $this->document() . 'Updated.' );

		$this->assertSame( 1, substr_count( $this->claude(), AgentTargets::REGION_START ) );
		$this->assertStringContainsString( 'Updated.', $this->claude() );
	}

	public function test_hand_written_content_is_preserved_and_region_appended(): void {
		file_put_contents( "{$this->dir}/CLAUDE.md", "# My hand-written notes\n\nScope rules here.\n" );

		$result = AgentTargets::write( $this->dir, $this->document() );

		$this->assertContains( "{$this->dir}/CLAUDE.md", $result['written'] );
		$this->assertStringStartsWith( '# My hand-written notes', $this->claude() );
		$this->assertStringContainsString( 'Scope rules here.', $this->claude() );
		$this->assertStringContainsString( AgentTargets::REGION_START, $this->claude() );
	}

	public function test_rerun_on_an_appended_region_keeps_hand_written_content(): void {
		file_put_contents( "{$this->dir}/CLAUDE.md", "# Mine\n" );
		AgentTargets::write( $this->dir, $this->document() );
		AgentTargets::write( $this->dir, $this->document() . 'Updated.' );

		$this->assertStringStartsWith( '# Mine', $this->claude() );
		$this->assertSame( 1, substr_count( $this->claude(), AgentTargets::REGION_START ) );
		$this->assertStringContainsString( 'Updated.', $this->claude() );
	}

	public function test_content_after_the_region_is_preserved(): void {
		file_put_contents(
			"{$this->dir}/CLAUDE.md",
			"Above.\n\n" . AgentTargets::REGION_START . "\nOld.\n" . AgentTargets::REGION_END . "\n\nBelow.\n"
		);

		AgentTargets::write( $this->dir, $this->document() );

		$this->assertStringStartsWith( 'Above.', $this->claude() );
		$this->assertStringEndsWith( "Below.\n", $this->claude() );
		$this->assertStringNotContainsString( 'Old.', $this->claude() );
	}

	public function test_pre_region_bosun_output_is_migrated_to_a_region(): void {
		file_put_contents( "{$this->dir}/CLAUDE.md", $this->document() );

		AgentTargets::write( $this->dir, $this->document() );

		$this->assertStringStartsWith( AgentTargets::REGION_START, $this->claude() );
		$this->assertSame( 1, substr_count( $this->claude(), AgentTargets::MARKER ) );
	}

	public function test_unbalanced_markers_are_skipped_untouched(): void {
		$broken = "Notes.\n" . AgentTargets::REGION_START . "\nNo end marker.\n";
		file_put_contents( "{$this->dir}/CLAUDE.md", $broken );

		$result = AgentTargets::write( $this->dir, $this->document() );

		$this->assertContains( "{$this->dir}/CLAUDE.md", $result['skipped'] );
		$this->assertSame( $broken, $this->claude() );
	}

	public function test_force_rewrites_unbalanced_files_as_region_only(): void {
		file_put_contents( "{$this->dir}/CLAUDE.md", "Notes.\n" . AgentTargets::REGION_START . "\n" );

		$result = AgentTargets::write( $this->dir, $this->document(), [], true );

		$this->assertContains( "{$this->dir}/CLAUDE.md", $result['written'] );
		$this->assertStringStartsWith( AgentTargets::REGION_START, $this->claude() );
		$this->assertStringNotContainsString( 'Notes.', $this->claude() );
	}

	public function test_duplicated_markers_are_skipped_untouched(): void {
		$mangled = AgentTargets::REGION_START . "\nA\n" . AgentTargets::REGION_END . "\n"
			. AgentTargets::REGION_START . "\nB\n" . AgentTargets::REGION_END . "\n";
		file_put_contents( "{$this->dir}/CLAUDE.md", $mangled );

		$result = AgentTargets::write( $this->dir, $this->document() );

		$this->assertContains( "{$this->dir}/CLAUDE.md", $result['skipped'] );
		$this->assertSame( $mangled, $this->claude() );
	}

	public function test_literal_markers_inside_the_document_are_stripped(): void {
		AgentTargets::write( $this->dir, "Docs mention " . AgentTargets::REGION_END . " literally.\n" );

		$this->assertSame( 1, substr_count( $this->claude(), AgentTargets::REGION_END ) );
	}

	public function test_unknown_agent_keys_are_ignored(): void {
		$result = AgentTargets::write( $this->dir, $this->document(), [ 'copilot' ] );

		$this->assertSame( [], $result['written'] );
	}
}
