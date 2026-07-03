<?php

namespace PressGang\Bosun\Commands;

/**
 * Recomposes AI agent guidelines for the active PressGang child theme.
 *
 * Alias of `wp bosun install` — regeneration is idempotent. Add it to the
 * theme's composer post-update-cmd scripts to keep guidelines current:
 *
 *     "post-update-cmd": [ "wp bosun update" ]
 *
 * ## EXAMPLES
 *
 *     wp bosun update
 */
class UpdateCommand extends InstallCommand {
}
