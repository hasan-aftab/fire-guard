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
    | Both agents need redirecting: Boost defaults `claude_code` to AGENTS.md rather
    | than CLAUDE.md, and `boost.json` enables both agents.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => '.boost-guidelines.md',
        ],
        'opencode' => [
            'guidelines_path' => '.boost-guidelines.md',
        ],
    ],
];
