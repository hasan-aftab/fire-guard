#!/usr/bin/env node
// Measured-layout gate for the dashboard.
//
// This answers one question: is anything broken at any width, in either theme,
// in any state? It cannot answer whether the result looks good — that needs a
// human, and the screenshots exist for that. So the split is deliberate:
// `measure.js` fails the build on objective faults, and never claims a visual
// pass.
//
// Gates:
//   1. no horizontal overflow of the document
//   2. nothing sticking out past the viewport outside a scroll container
//   3. every interactive target at least 24x24 (WCAG 2.2 AA, 2.5.8)
//   4. no text clipped without an ellipsis (a lost label, not a chosen one)
//   5. every group heading at least 24px tall
//   6. state agrees with the URL: rows present when data exists, an empty
//      state when it does not
//   7. the certificates tile lists only certificates — the invariant the tiles
//      exist to make checkable
//
// Usage: node qa/measure.js [light|dark]

import { execFileSync } from 'node:child_process';

const BASE_URL = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8000';
const THEME = process.argv[2] ?? 'all';
const THEMES = THEME === 'all' ? ['light', 'dark'] : [THEME];
const MIN_TARGET = 24;

const manifest = (theme) =>
    JSON.parse(
        execFileSync(
            'python3',
            ['qa/lib/read-manifest.py', `qa/dashboard-${theme}.yml`, BASE_URL],
            {
                encoding: 'utf8',
            },
        ),
    );

const probe = (url, theme, width) =>
    JSON.parse(
        execFileSync(
            'shot-scraper',
            [
                'javascript',
                BASE_URL + url,
                '--auth',
                `qa/auth-${theme}.json`,
                '--width',
                String(width),
                '--height',
                '900',
                '-i',
                'qa/probe.js',
            ],
            { encoding: 'utf8', maxBuffer: 1024 * 1024 },
        ),
    );

const failures = [];
const notes = [];

const check = (where, gate, ok, detail) => {
    if (!ok) failures.push(`${where}: ${gate} — ${detail}`);
};

for (const theme of THEMES) {
    const plan = manifest(theme);

    for (const shot of plan.shots) {
        for (const width of plan.widths) {
            const where = `${shot.name}@${width}`;
            const m = probe(shot.url, theme, width);

            check(
                where,
                'no horizontal overflow',
                m.scrollWidth <= m.width + 1,
                `scrollWidth ${m.scrollWidth} > viewport ${m.width}`,
            );
            check(
                where,
                'nothing overflows the viewport',
                m.overflowing.length === 0,
                m.overflowing
                    .map((o) => `${o.label} to ${o.right}px`)
                    .join(', '),
            );
            check(
                where,
                'targets >= 24px',
                m.smallTargets.length === 0,
                m.smallTargets
                    .map((t) => `${t.label} ${t.w}x${t.h}`)
                    .join(', '),
            );
            check(
                where,
                'no text clipped silently',
                m.clippedText.length === 0,
                m.clippedText.map((c) => c.label).join(', '),
            );

            for (const group of m.groups) {
                check(
                    where,
                    'group heading >= 24px',
                    group.headingHeight >= MIN_TARGET,
                    `${group.label} heading is ${group.headingHeight}px`,
                );
            }

            if (shot.name.endsWith('nothing-due')) {
                // The empty message is the point of this state: no matches must
                // still say so, not leave a blank card.
                check(
                    where,
                    'no rows with no matches',
                    m.rowCount === 0,
                    `${m.rowCount} rows rendered`,
                );
                check(
                    where,
                    'empty message is shown',
                    !!m.emptyMessage,
                    'no [data-empty] element',
                );
                check(
                    where,
                    'tiles are still shown',
                    m.cards > 0,
                    `${m.cards} tiles`,
                );
            }

            if (shot.name.endsWith('overdue-only')) {
                check(
                    where,
                    'overdue-only date control is named',
                    m.dueDateLabel === 'Due date: Overdue',
                    m.dueDateLabel ?? 'missing due-date control',
                );
                check(
                    where,
                    'overdue-only has rows',
                    m.rowCount > 0,
                    'no rows rendered',
                );
            } else if (!shot.name.endsWith('nothing-due')) {
                check(
                    where,
                    'rows are present',
                    m.rowCount > 0,
                    'no rows rendered',
                );
            }

            if (shot.name.endsWith('custom-range')) {
                check(
                    where,
                    'custom date control is named',
                    m.dueDateLabel?.includes('6 Oct – 20 Oct') === true,
                    m.dueDateLabel ?? 'missing due-date control',
                );
            }

            if (shot.name.endsWith('certificates-tile')) {
                check(
                    where,
                    'certificates tile lists only certificates',
                    m.rowKinds.length === 1 && m.rowKinds[0] === 'certificate',
                    `kinds: ${JSON.stringify(m.rowKinds)}`,
                );
            }

            if (m.ellipsisedCount > 0) {
                notes.push(
                    `${where}: ${m.ellipsisedCount} intentionally truncated label(s)`,
                );
            }
        }
    }
}

const total =
    THEMES.length *
    manifest(THEMES[0]).shots.length *
    manifest(THEMES[0]).widths.length;
console.log(`measured ${total} dashboard renders (${THEMES.join(', ')})`);

if (notes.length) {
    console.log(`\n${notes.length} note(s):`);
    for (const note of notes) console.log(`  ${note}`);
}

if (failures.length) {
    console.error(`\nmeasured: FAIL — ${failures.length} problem(s)`);
    for (const failure of failures) console.error(`  ${failure}`);
    process.exit(1);
}

console.log('\nmeasured: pass · visual: pending review');
