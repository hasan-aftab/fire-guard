import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Vitest runs with the Vite config's directory as the working directory, so
// app.css is addressed from the project root rather than from import.meta.url
// (which is not a file: URL under the jsdom environment).
const APP_CSS = resolve(process.cwd(), 'resources/css/app.css');

type Rgb = readonly [number, number, number];

export type Oklch = readonly [lightness: number, chroma: number, hue: number];

const clamp = (value: number): number => Math.min(1, Math.max(0, value));

const toSrgb = (linear: number): number =>
    linear <= 0.0031308 ? 12.92 * linear : 1.055 * linear ** (1 / 2.4) - 0.055;

const toLinear = (channel: number): number =>
    channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;

/** Converts an OKLCH colour to gamma-encoded sRGB, matching CSS Color 4. */
export const oklchToSrgb = ([lightness, chroma, hue]: Oklch): Rgb => {
    const radians = (hue * Math.PI) / 180;
    const a = chroma * Math.cos(radians);
    const b = chroma * Math.sin(radians);

    const l = (lightness + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (lightness - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (lightness - 0.0894841775 * a - 1.291485548 * b) ** 3;

    return [
        clamp(toSrgb(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s)),
        clamp(toSrgb(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s)),
        clamp(toSrgb(-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s)),
    ];
};

/** WCAG 2.1 relative luminance of a gamma-encoded sRGB colour. */
export const relativeLuminance = ([r, g, b]: Rgb): number =>
    0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);

/** WCAG 2.1 contrast ratio between two gamma-encoded sRGB colours. */
export const contrastRatio = (a: Rgb, b: Rgb): number => {
    const [first, second] = [relativeLuminance(a), relativeLuminance(b)].sort(
        (high, low) => low - high,
    );

    return (first + 0.05) / (second + 0.05);
};

/** Composites `source` over `backdrop` at `alpha`, as browsers do in sRGB. */
export const composite = (source: Rgb, backdrop: Rgb, alpha: number): Rgb =>
    source.map(
        (channel, index) => channel * alpha + backdrop[index] * (1 - alpha),
    ) as unknown as Rgb;

/** A `x / y` tint over a surface, e.g. `bg-success/10`. */
export const tintOver = (
    color: Oklch,
    backdrop: Oklch,
    alpha: number,
): number =>
    contrastRatio(
        oklchToSrgb(color),
        composite(oklchToSrgb(color), oklchToSrgb(backdrop), alpha),
    );

/** Text or icon colour sitting directly on a surface. */
export const textOn = (color: Oklch, surface: Oklch): number =>
    contrastRatio(oklchToSrgb(color), oklchToSrgb(surface));

const OKLCH_TOKEN =
    /^--([a-z0-9-]+):\s*oklch\(\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\)\s*;$/;

const readTokensFrom = (
    css: string,
    blockPattern: RegExp,
): Map<string, Oklch> => {
    const block = blockPattern.exec(css);

    if (!block) {
        throw new Error(`No block matching ${blockPattern} found in app.css`);
    }

    const tokens = new Map<string, Oklch>();

    for (const line of block[1].split('\n')) {
        const match = OKLCH_TOKEN.exec(line.trim());

        if (match) {
            // `@theme inline` declares literals under Tailwind's `--color-*`
            // namespace while `:root` / `.dark` use the raw names, so the
            // namespace is stripped to give one consistent lookup key.
            tokens.set(match[1].replace(/^color-/, ''), [
                Number(match[2]),
                Number(match[3]),
                Number(match[4]),
            ]);
        }
    }

    return tokens;
};

/**
 * Reads the OKLCH custom properties that apply to a theme in app.css.
 *
 * Tokens are declared either once in `@theme inline` (theme-independent, e.g.
 * `--brand`) or inside `:root` / `.dark` (theme-specific, e.g. `--card`).
 * The stylesheet is the single source of truth for theme tokens, so the
 * contrast assertions follow it rather than restating its values.
 */
export const readThemeTokens = (
    theme: 'light' | 'dark',
): Map<string, Oklch> => {
    const css = readFileSync(APP_CSS, 'utf8');
    const base = readTokensFrom(css, /@theme inline \{([^}]*)\}/);
    const overrides = readTokensFrom(
        css,
        theme === 'dark'
            ? /(?:^|\n)\.dark \{([^}]*)\}/
            : /(?:^|\n):root \{([^}]*)\}/,
    );

    return new Map([...base, ...overrides]);
};
