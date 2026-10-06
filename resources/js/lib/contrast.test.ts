import { describe, expect, it } from 'vitest';

import { readThemeTokens, textOn, tintOver } from './contrast';

const light = readThemeTokens('light');
const dark = readThemeTokens('dark');

const token = (
    tokens: Map<string, readonly [number, number, number]>,
    name: string,
) => {
    const value = tokens.get(name);

    if (!value) {
        throw new Error(`Missing --${name} in app.css`);
    }

    return value;
};

const TEXT = 4.5;
const UI = 3;

// Every pair below is a real class combination used in the dashboard or the
// shared status components. Thresholds are WCAG 2.1 AA: 4.5:1 for text, 3:1
// for non-text UI boundaries.
const TEXT_PAIRS = [
    {
        name: 'muted foreground on card',
        ratio: (t: typeof light) =>
            textOn(token(t, 'muted-foreground'), token(t, 'card')),
    },
    {
        name: 'warning text on compliance card',
        ratio: (t: typeof light) =>
            textOn(token(t, 'warning'), token(t, 'card')),
    },
    {
        name: 'overdue text on schedule card',
        ratio: (t: typeof light) =>
            textOn(token(t, 'destructive'), token(t, 'card')),
    },
    {
        name: 'success on success/10 over card',
        ratio: (t: typeof light) =>
            tintOver(token(t, 'success'), token(t, 'card'), 0.1),
    },
    {
        name: 'warning on warning/10 over card',
        ratio: (t: typeof light) =>
            tintOver(token(t, 'warning'), token(t, 'card'), 0.1),
    },
    {
        name: 'destructive on destructive/10 over card',
        ratio: (t: typeof light) =>
            tintOver(token(t, 'destructive'), token(t, 'card'), 0.1),
    },
    {
        name: 'destructive foreground on destructive badge',
        ratio: (t: typeof light) =>
            textOn(token(t, 'destructive-foreground'), token(t, 'destructive')),
    },
] as const;

// The focus indicator is the one UI boundary that must clear 3:1 against every
// surface it can land on, in both themes. shadcn pairs its decorative
// `focus-visible:ring-ring/50` halo with a solid `focus-visible:border-ring`,
// so the solid token is what these assertions pin.
const UI_PAIRS = ['card', 'background', 'muted', 'accent', 'popover'] as const;

describe('theme token contrast', () => {
    describe.each([
        ['light', light],
        ['dark', dark],
    ] as const)('%s theme', (_theme, tokens) => {
        it.each(TEXT_PAIRS)('$name meets AA text contrast', ({ ratio }) => {
            expect(ratio(tokens)).toBeGreaterThanOrEqual(TEXT);
        });

        it.each(UI_PAIRS)('focus ring meets UI contrast on %s', (surface) => {
            expect(
                textOn(token(tokens, 'ring'), token(tokens, surface)),
            ).toBeGreaterThanOrEqual(UI);
        });
    });

    it('uses the same ring lightness in both themes', () => {
        // One token value for both themes means a single assertion above covers
        // every surface; a divergent pair would silently reintroduce a theme
        // that fails 3:1.
        expect(token(dark, 'ring')[0]).toBe(token(light, 'ring')[0]);
    });

    it('keeps the sidebar ring in step with the ring', () => {
        expect(token(light, 'sidebar-ring')[0]).toBe(token(light, 'ring')[0]);
        expect(token(dark, 'sidebar-ring')[0]).toBe(token(dark, 'ring')[0]);
    });

    it('leaves the brand fill/icon token untouched in both themes', () => {
        // `--brand` is deliberately left alone: it is used for fills and icons
        // (sidebar rail, logo, reports), not for text, and darkening it would
        // restyle the whole product.
        expect(token(dark, 'brand')[0]).toBe(token(light, 'brand')[0]);
    });

    it('flips the destructive badge foreground between themes', () => {
        // Light mode paints white on red; dark mode paints near-black on the
        // lighter dark-mode red. Both clear AA, which is why the badge uses the
        // semantic token instead of a hard-coded colour.
        expect(token(light, 'destructive-foreground')[0]).toBeGreaterThan(0.9);
        expect(token(dark, 'destructive-foreground')[0]).toBeLessThan(0.2);
    });
});
