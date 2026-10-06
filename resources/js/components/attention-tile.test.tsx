import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { AlertTriangle } from 'lucide-react';

import { AttentionTile } from './attention-tile';

describe('a tile that narrows the page', () => {
    it('is a button, so it does not masquerade as navigation', async () => {
        const user = userEvent.setup();
        const onClick = vi.fn();

        render(
            <AttentionTile
                label="Overdue"
                value={4}
                icon={AlertTriangle}
                detail="2 inspections · 2 certificates"
                action="Show in schedule"
                onClick={onClick}
            />,
        );

        expect(screen.queryByRole('link')).toBeNull();

        await user.click(screen.getByRole('button'));

        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('shows what activating it will do', () => {
        render(
            <AttentionTile
                label="Overdue"
                value={4}
                icon={AlertTriangle}
                action="Show in schedule"
                onClick={() => {}}
            />,
        );

        expect(screen.getByText('Show in schedule')).not.toBeNull();
    });

    it('announces its pressed state, so the scope it set is not a guess', () => {
        render(
            <AttentionTile
                label="Overdue"
                value={4}
                icon={AlertTriangle}
                action="Show in schedule"
                active
                onClick={() => {}}
            />,
        );

        expect(screen.getByRole('button').getAttribute('aria-pressed')).toBe(
            'true',
        );
    });

    it('says so in words too, because a ring alone is not an announcement', () => {
        render(
            <AttentionTile
                label="Overdue"
                value={4}
                icon={AlertTriangle}
                action="Show in schedule"
                active
                onClick={() => {}}
            />,
        );

        // The lit tile offers the way out rather than repeating itself.
        expect(screen.getByText('Showing')).not.toBeNull();
        expect(screen.getByText('Clear to show everything')).not.toBeNull();
    });

    it('deselects nothing when it is the wrong tile for the current scope', () => {
        render(
            <AttentionTile
                label="Overdue"
                value={4}
                icon={AlertTriangle}
                action="Show in schedule"
                onClick={() => {}}
            />,
        );

        expect(screen.getByRole('button').getAttribute('aria-pressed')).toBe(
            'false',
        );
    });
});

describe('a tile with no scope to set', () => {
    it('is not focusable for nothing', () => {
        render(
            <AttentionTile
                label="Total assets"
                value={42}
                icon={AlertTriangle}
            />,
        );

        const tile = screen.getByRole('button');

        expect((tile as HTMLButtonElement).disabled).toBe(true);
        expect(tile.getAttribute('aria-pressed')).toBeNull();
    });
});
