import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { StatCard } from './stat-card';

describe('a card that links', () => {
    it('makes the whole card the link, not just the number', () => {
        render(
            <StatCard
                label="Total assets"
                value={42}
                description="Fire safety equipment"
                href="/assets"
            />,
        );

        const link = screen.getByRole('link');

        expect(link.getAttribute('href')).toBe('/assets');
        expect(link.textContent).toContain('Total assets');
        expect(link.textContent).toContain('42');
    });
});

describe('a plain card', () => {
    it('is neither a link nor a button, so it is not focusable for nothing', () => {
        render(<StatCard label="Total assets" value={42} />);

        expect(screen.queryByRole('link')).toBeNull();
        expect(screen.queryByRole('button')).toBeNull();
    });
});
