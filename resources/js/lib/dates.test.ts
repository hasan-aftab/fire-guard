import { describe, expect, it } from 'vitest';

import {
    addDays,
    dayHeading,
    diffDays,
    formatLongDate,
    mediumDate,
    rangeLabel,
    relativeDue,
    shortDate,
} from './dates';

// Every assertion below is written against the one locale the product ships
// with. They are therefore also the regression test for that decision: if a
// caller could reintroduce a per-machine locale, these literals would stop
// matching on any machine not set to en-GB.

describe('calendar arithmetic', () => {
    it('differences two date-only values in exact whole days', () => {
        expect(diffDays('2026-10-04', '2026-10-17')).toBe(13);
        expect(diffDays('2026-10-17', '2026-10-04')).toBe(-13);
        expect(diffDays('2026-10-04', '2026-10-04')).toBe(0);
    });

    it('spans month and year boundaries exactly', () => {
        expect(diffDays('2026-09-30', '2026-10-01')).toBe(1);
        expect(diffDays('2025-12-31', '2026-01-01')).toBe(1);
        expect(diffDays('2024-02-28', '2024-03-01')).toBe(2);
    });

    it('adds days across a month boundary without slipping', () => {
        expect(addDays('2026-10-31', 1)).toBe('2026-11-01');
        expect(addDays('2026-01-01', -1)).toBe('2025-12-31');
    });
});

describe('formatting', () => {
    it('renders a date-only value without shifting its calendar day', () => {
        expect(shortDate('2026-10-04')).toBe('4 Oct');
        expect(formatLongDate('2026-10-04')).toBe('Sun, 4 Oct 2026');
        expect(mediumDate('2026-10-04')).toBe('Sun 4 Oct');
    });

    it('renders month and year boundaries without shifting them', () => {
        expect(shortDate('2026-01-01')).toBe('1 Jan');
        expect(formatLongDate('2026-01-01')).toBe('Thu, 1 Jan 2026');
        expect(formatLongDate('2026-12-31')).toBe('Thu, 31 Dec 2026');
    });

    it('never emits a raw ISO string or a lowercased heading', () => {
        const rendered = [
            shortDate('2026-10-04'),
            formatLongDate('2026-10-04'),
            mediumDate('2026-10-04'),
            dayHeading('2026-10-04', '2026-10-04'),
        ].join(' ');

        expect(rendered).not.toMatch(/\d{4}-\d{2}-\d{2}/);
        expect(rendered).not.toMatch(/\b[a-z]{3},/);
    });

    it('states a range with the year once when both ends share it', () => {
        expect(rangeLabel('2026-10-04', '2026-10-17')).toBe(
            '4 Oct – 17 Oct 2026',
        );
    });

    it('states the year on both ends when the range crosses one', () => {
        expect(rangeLabel('2025-12-30', '2026-01-04')).toBe(
            'Tue, 30 Dec 2025 – Sun, 4 Jan 2026',
        );
    });
});

describe('due language', () => {
    it('describes due dates relative to the as-of date', () => {
        expect(relativeDue('2026-10-04', '2026-10-04')).toBe('Due today');
        expect(relativeDue('2026-10-05', '2026-10-04')).toBe('Due tomorrow');
        expect(relativeDue('2026-10-17', '2026-10-04')).toBe('Due in 13 days');
        expect(relativeDue('2026-10-03', '2026-10-04')).toBe('1 day overdue');
        expect(relativeDue('2026-08-20', '2026-10-04')).toBe('45 days overdue');
    });

    it('names today and tomorrow but states every other date outright', () => {
        expect(dayHeading('2026-10-04', '2026-10-04')).toBe('Today');
        expect(dayHeading('2026-10-05', '2026-10-04')).toBe('Tomorrow');
        expect(dayHeading('2026-10-06', '2026-10-04')).toBe('Tue 6 Oct');
    });
});
