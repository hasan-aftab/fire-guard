import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ScheduleRow } from './schedule-row';
import type { ScheduleItem } from '@/types/fire-tracker';

const AS_OF = '2026-10-04';

function item(overrides: Partial<ScheduleItem> = {}): ScheduleItem {
    return {
        key: 'inspection:1',
        entityId: 1,
        kind: 'asset',
        typeName: 'Fire Extinguisher Annual Service',
        reference: 'FE-0042',
        siteName: 'Harbourside Plaza',
        locationDesc: null,
        clientName: 'Harbourside Group',
        dueDate: '2026-10-09',
        daysUntil: 5,
        status: 'ok',
        isOverdue: false,
        href: '/inspections?search=FE-0042',
        ...overrides,
    };
}

describe('a schedule row', () => {
    it('is the link, so the whole row is the target', () => {
        render(
            <ul>
                <ScheduleRow item={item()} asOfDate={AS_OF} />
            </ul>,
        );

        const link = screen.getByRole('link');

        expect(link.getAttribute('href')).toBe('/inspections?search=FE-0042');
        expect(link.textContent).toContain('Fire Extinguisher Annual Service');
    });

    it('leads with what is late, not with the calendar date', () => {
        render(
            <ul>
                <ScheduleRow item={item()} asOfDate={AS_OF} />
            </ul>,
        );

        expect(screen.getByText('Due in 5 days')).not.toBeNull();
    });

    it('names the site and the client it belongs to', () => {
        render(
            <ul>
                <ScheduleRow item={item()} asOfDate={AS_OF} />
            </ul>,
        );

        expect(
            screen.getByText('Harbourside Plaza · Harbourside Group'),
        ).not.toBeNull();
    });

    it('keeps the equipment reference and location available in the mobile subtitle', () => {
        render(
            <ul>
                <ScheduleRow
                    item={item({ locationDesc: 'Level 2 lobby' })}
                    asOfDate={AS_OF}
                />
            </ul>,
        );

        expect(
            screen.getByText('FE-0042 · Level 2 lobby · Harbourside Plaza'),
        ).not.toBeNull();
    });

    it('drops the year and weekday on a date that is only days away', () => {
        render(
            <ul>
                <ScheduleRow item={item()} asOfDate={AS_OF} />
            </ul>,
        );

        const row = screen.getByRole('link');

        expect(row.textContent).toContain('9 Oct');
        expect(row.textContent).not.toContain('2026');
        expect(row.textContent).not.toContain('Fri');
    });

    it('keeps the year and weekday on an overdue date, which may be old', () => {
        render(
            <ul>
                <ScheduleRow
                    item={item({
                        dueDate: '2025-08-20',
                        isOverdue: true,
                        status: 'overdue',
                    })}
                    asOfDate={AS_OF}
                />
            </ul>,
        );

        const row = screen.getByRole('link');

        // Two rows reading "20 Aug" could be different years; here it is certain.
        expect(row.textContent).toContain('20 Aug 2025');
        expect(screen.getByText(/days overdue/)).not.toBeNull();
    });

    it('carries the server-measured status, not one it worked out itself', () => {
        render(
            <ul>
                <ScheduleRow
                    item={item({ status: 'due_soon', dueDate: '2026-10-06' })}
                    asOfDate={AS_OF}
                />
            </ul>,
        );

        const row = screen.getByRole('link');

        expect(
            row.querySelector('svg')?.classList.contains('text-warning'),
        ).toBe(true);
    });
});
