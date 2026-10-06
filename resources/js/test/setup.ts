import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

/**
 * jsdom implements neither `matchMedia` nor the observer APIs the mobile
 * sidebar reaches for. Tests only need them to exist, so they stay inert.
 */ Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});

global.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

/**
 * jsdom has no layout, so `scrollIntoView` is absent. The day strip calls it to
 * keep the selected cell in view when the window is wider than the viewport,
 * which is a real browser concern with nothing for jsdom to assert about.
 */
if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = () => {};
}

afterEach(() => {
    cleanup();
});
