<?php

namespace PressGang\Bosun\Skills;

use PressGang\Bosun\Detect\ThemeInventory;

/**
 * Locates and installs agent skills for a theme.
 *
 * Skills are directories containing a SKILL.md (Agent Skills format) plus
 * optional supporting files. Like guidelines, they are gathered in three
 * tiers — package-shipped (`vendor/{package}/resources/boost/skills/{name}`),
 * bosun built-ins (`resources/skills/{name}`), theme-local
 * (`.ai/skills/{name}`) — with later tiers overriding earlier ones by skill
 * name.
 *
 * Skills install to `.claude/skills/{name}` in the theme. Installed skills
 * are authoritative-from-source on every run: customise by overriding in
 * `.ai/skills/{name}`, not by editing the installed copy.
 */
class SkillInstaller {

	/**
	 * @param string $builtins_dir Bosun's own resources/skills directory.
	 */
	public function __construct( protected string $builtins_dir ) {
	}

	/**
	 * Locates the skills applicable to a theme inventory.
	 *
	 * Built-in and package-shipped skills may declare a required feature in
	 * their SKILL.md frontmatter (`requires-feature: legacy-v1`) and are
	 * skipped when the theme lacks it. Theme-local skills are never gated.
	 *
	 * @param ThemeInventory $inventory
	 *
	 * @return array<string, string> Skill name => source directory.
	 */
	public function locate( ThemeInventory $inventory ): array {

		$skills = [];

		$tiers = [ $this->builtins_dir ];

		foreach ( array_keys( $inventory->packages ) as $package ) {
			$tiers[] = "{$inventory->theme_dir}/vendor/{$package}/resources/boost/skills";
		}

		foreach ( $tiers as $tier ) {
			foreach ( $this->skills_in( $tier ) as $name => $dir ) {
				if ( $this->applies( $dir, $inventory ) ) {
					$skills[ $name ] = $dir;
				}
			}
		}

		foreach ( $this->skills_in( "{$inventory->theme_dir}/.ai/skills" ) as $name => $dir ) {
			$skills[ $name ] = $dir;
		}

		ksort( $skills );

		return $skills;
	}

	/**
	 * Whether a skill's frontmatter feature requirement is satisfied.
	 *
	 * @param string         $dir       Skill directory.
	 * @param ThemeInventory $inventory
	 *
	 * @return bool
	 */
	protected function applies( string $dir, ThemeInventory $inventory ): bool {

		$frontmatter = $this->frontmatter( "{$dir}/SKILL.md" );

		if ( ! preg_match( '/^requires-feature:\s*(\S+)\s*$/m', $frontmatter, $match ) ) {
			return true;
		}

		return $inventory->has_feature( $match[1] );
	}

	/**
	 * A SKILL.md file's YAML frontmatter block, or an empty string.
	 *
	 * @param string $file Absolute path to a SKILL.md.
	 *
	 * @return string
	 */
	protected function frontmatter( string $file ): string {

		$content = (string) file_get_contents( $file );

		if ( preg_match( '/^---\r?\n(.*?)\r?\n---/s', $content, $match ) ) {
			return $match[1];
		}

		return '';
	}

	/**
	 * Installs skills into the theme's `.claude/skills` directory.
	 *
	 * @param string                $theme_dir Absolute path to the child theme.
	 * @param array<string, string> $skills    Skill name => source directory.
	 *
	 * @return array<int, string> Installed skill names.
	 */
	public function install( string $theme_dir, array $skills ): array {

		if ( ! is_dir( "{$theme_dir}/.claude/skills" ) ) {
			mkdir( "{$theme_dir}/.claude/skills", 0755, true );
		}

		$this->prune( $theme_dir, array_keys( $skills ) );

		$installed = [];

		foreach ( $skills as $name => $source ) {
			$target = "{$theme_dir}/.claude/skills/{$name}";

			// Replace rather than overlay, so files removed from the source
			// don't linger in the installed copy.
			if ( is_dir( $target ) ) {
				$this->remove_dir( $target );
			}

			$this->copy_dir( $source, $target );

			$installed[] = $name;
		}

		file_put_contents(
			"{$theme_dir}/.claude/skills/.bosun-skills.json",
			json_encode( $installed, JSON_PRETTY_PRINT ) . "\n"
		);

		return $installed;
	}

	/**
	 * Removes previously bosun-managed skills that no longer apply (e.g. a
	 * migration skill after the theme has been migrated). Only skills listed
	 * in the bosun manifest are ever removed — hand-installed skills are
	 * never touched.
	 *
	 * @param string             $theme_dir Absolute path to the child theme.
	 * @param array<int, string> $current   Skill names about to be installed.
	 *
	 * @return void
	 */
	protected function prune( string $theme_dir, array $current ): void {

		$manifest = "{$theme_dir}/.claude/skills/.bosun-skills.json";

		if ( ! is_readable( $manifest ) ) {
			return;
		}

		$managed = json_decode( (string) file_get_contents( $manifest ), true );

		if ( ! is_array( $managed ) ) {
			return;
		}

		foreach ( array_diff( $managed, $current ) as $stale ) {
			$dir = "{$theme_dir}/.claude/skills/" . basename( (string) $stale );

			if ( is_dir( $dir ) ) {
				$this->remove_dir( $dir );
			}
		}
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir
	 *
	 * @return void
	 */
	protected function remove_dir( string $dir ): void {

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $dir );
	}

	/**
	 * Skill directories (containing a SKILL.md) within a tier directory.
	 *
	 * @param string $dir
	 *
	 * @return array<string, string> Skill name => directory.
	 */
	protected function skills_in( string $dir ): array {

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$skills = [];

		foreach ( new \FilesystemIterator( $dir, \FilesystemIterator::SKIP_DOTS ) as $entry ) {
			if ( $entry->isDir() && is_file( $entry->getPathname() . '/SKILL.md' ) ) {
				$skills[ $entry->getFilename() ] = $entry->getPathname();
			}
		}

		return $skills;
	}

	/**
	 * Recursively copies a directory.
	 *
	 * @param string $source
	 * @param string $target
	 *
	 * @return void
	 */
	protected function copy_dir( string $source, string $target ): void {

		if ( ! is_dir( $target ) ) {
			mkdir( $target, 0755, true );
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$destination = $target . '/' . $iterator->getSubPathname();

			if ( $item->isDir() ) {
				is_dir( $destination ) || mkdir( $destination, 0755, true );
			} else {
				copy( $item->getPathname(), $destination );
			}
		}
	}
}
