<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Boost Agents
    |--------------------------------------------------------------------------
    |
    | Redirects the agent guidelines that `php artisan boost:update` generates into
    | `.boost-guidelines.md`, a gitignored file, so that this project's hand-written
    | AGENTS.md stays free of machine-generated content.
    |
    | Boost defaults `claude_code` and `codex` to AGENTS.md rather than CLAUDE.md,
    | so a partial override silently re-appends the block to this project notes file.
    | `codex` is listed even though `boost.json` does not enable it yet, so the
    | override is already correct if Boost is later reconfigured to include it.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => '.boost-guidelines.md',
        ],
        'codex' => [
            'guidelines_path' => '.boost-guidelines.md',
        ],
        'opencode' => [
            'guidelines_path' => '.boost-guidelines.md',
        ],
    ],
];
