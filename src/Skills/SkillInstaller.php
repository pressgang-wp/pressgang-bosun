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

		$tiers[] = "{$inventory->theme_dir}/.ai/skills";

		foreach ( $tiers as $tier ) {
			foreach ( $this->skills_in( $tier ) as $name => $dir ) {
				$skills[ $name ] = $dir;
			}
		}

		ksort( $skills );

		return $skills;
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

		$installed = [];

		foreach ( $skills as $name => $source ) {
			$target = "{$theme_dir}/.claude/skills/{$name}";

			$this->copy_dir( $source, $target );

			$installed[] = $name;
		}

		return $installed;
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
