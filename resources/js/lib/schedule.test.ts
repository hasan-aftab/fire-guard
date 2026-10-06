import { describe, expect, it } from 'vitest';

import {
    activeTile,
    DEFAULT_STATE,
    horizonLabel,
    isDefaultState,
    kindBreakdown,
    kindLabel,
    OVERDUE_MODES,
    SCHEDULE_TYPES,
    scheduleState,
    scopeSentence,
    sinceSentence,
    stateFromQuery,
    stateToQuery,
    tileState,
    typeTabs,
} from './schedule';
import type { ScheduleState } from './schedule';
import type { DashboardKpis, DashboardSchedule } from '@/types/fire-tracker';

const AS_OF = '2026-10-04';

function kpis(): DashboardKpis {
    return {
        overdue: { total: 5, byKind: { asset: 3, certificate: 2 } },
        dueSoon: { total: 2, horizonPreset: '14d', horizonDays: 15 },
        certificates: {
            expired: 1,
            expiringSoon: 2,
            total: 3,
            horizonPreset: '30d',
        },
        passRate: {
            percent: 67,
            band: 'warning',
            pass: 2,
            fail: 1,
            needsRepair: 0,
            total: 3,
        },
    };
}

function schedule(
    overrides: Partial<DashboardSchedule> = {},
): DashboardSchedule {
    return {
        from: AS_OF,
        to: '2026-10-18',
        horizonDays: 15,
        horizonOffset: 14,
        horizonPreset: '14d',
        type: 'all',
        overdueMode: 'include',
        day: null,
        windowHidden: false,
        groupBoundaryDays: 14,
        groups: [
            {
                id: 'overdue',
                label: 'Overdue',
                itemCount: 0,
                byKind: { asset: 0, certificate: 0 },
                firstDate: null,
                items: [],
            },
            {
                id: 'next',
                label: 'Next 14 days',
                itemCount: 0,
                byKind: { asset: 0, certificate: 0 },
                firstDate: null,
                items: [],
            },
            {
                id: 'later',
                label: 'Later',
                itemCount: 0,
                byKind: { asset: 0, certificate: 0 },
                firstDate: null,
                items: [],
            },
        ],
        counts: { all: 0, asset: 0, certificate: 0 },
        days: [],
        overdueSince: null,
        overdueByKind: { asset: 0, certificate: 0 },
        nextAfter: null,
        overdueCount: 0,
        windowItemCount: 0,
        totalCount: 0,
        page: 1,
        lastPage: 1,
        returnedCount: 0,
        truncated: false,
        maxItems: 60,
        maxHorizonDays: 90,
        horizonPresets: { '7d': 7, '14d': 14, '30d': 30 },
        defaultHorizonPreset: '14d',
        ...overrides,
    };
}

describe('reading state out of a URL', () => {
    it('defaults to the untouched page when nothing is asked for', () => {
        const state = stateFromQuery(new URLSearchParams());

        expect(state).toEqual(DEFAULT_STATE);
        expect(isDefaultState(state)).toBe(true);
    });

    it('reads every field the schedule supports', () => {
        expect(
            stateFromQuery(
                new URLSearchParams({
                    schedule_type: 'certificate',
                    schedule_horizon: '30d',
                    schedule_overdue: 'exclude',
                    schedule_day: '2026-10-07',
                }),
            ),
        ).toEqual({
            type: 'certificate',
            dateMode: null,
            horizon: '30d',
            from: null,
            to: null,
            overdue: 'exclude',
            day: '2026-10-07',
            page: 1,
        });
    });

    it('accepts a plain record, because the server echoes filters that way', () => {
        expect(stateFromQuery({ schedule_overdue: 'only' }).overdue).toBe(
            'only',
        );
    });

    it('ignores values it cannot act on rather than rendering nothing', () => {
        const state = stateFromQuery(
            new URLSearchParams({
                schedule_type: 'invoice',
                schedule_overdue: 'sideways',
                schedule_day: '07-10-2026',
            }),
        );

        expect(state).toEqual(DEFAULT_STATE);
    });

    it('drops a half-specified custom range so both ends always agree', () => {
        const onlyStart = stateFromQuery(
            new URLSearchParams({ schedule_from: '2026-10-01' }),
        );
        const onlyEnd = stateFromQuery(
            new URLSearchParams({ schedule_to: '2026-10-20' }),
        );
        const both = stateFromQuery(
            new URLSearchParams({
                schedule_from: '2026-10-01',
                schedule_to: '2026-10-20',
            }),
        );

        expect(onlyStart.from).toBeNull();
        expect(onlyEnd.to).toBeNull();
        expect(both).toMatchObject({ from: '2026-10-01', to: '2026-10-20' });
    });

    it('round-trips inclusive open-ended and bounded date filters', () => {
        for (const custom of [
            scheduleState({ dateMode: 'through', to: '2026-10-03' }),
            scheduleState({ dateMode: 'from', from: '2026-10-10' }),
            scheduleState({
                dateMode: 'between',
                from: '2026-10-03',
                to: '2026-10-10',
            }),
        ]) {
            expect(
                stateFromQuery(new URLSearchParams(stateToQuery(custom))),
            ).toEqual(custom);
        }
    });

    it('keeps the selected agenda page in a shareable URL', () => {
        const state = scheduleState({ page: 3, type: 'asset' });
        expect(stateToQuery(state)).toEqual({
            schedule_type: 'asset',
            schedule_page: '3',
        });
        expect(
            stateFromQuery(new URLSearchParams(stateToQuery(state))),
        ).toEqual(state);
    });
});

describe('writing state back to a URL', () => {
    it('leaves the query empty at the default, so reset is a real reset', () => {
        expect(stateToQuery(DEFAULT_STATE)).toEqual({});
    });

    it('emits only what differs from the default', () => {
        expect(stateToQuery(scheduleState({ overdue: 'exclude' }))).toEqual({
            schedule_overdue: 'exclude',
        });
    });

    it('round-trips every state it can produce', () => {
        const states: ScheduleState[] = [
            DEFAULT_STATE,
            scheduleState({ type: 'certificate', horizon: '30d' }),
            scheduleState({ overdue: 'only' }),
            scheduleState({ overdue: 'exclude', horizon: '7d' }),
            scheduleState({ day: '2026-10-07' }),
            scheduleState({ from: '2026-10-01', to: '2026-10-20' }),
            scheduleState({
                type: 'asset',
                horizon: '7d',
                overdue: 'include',
                day: '2026-10-05',
                from: null,
                to: null,
            }),
        ];

        for (const state of states) {
            expect(
                stateFromQuery(new URLSearchParams(stateToQuery(state))),
            ).toEqual(state);
        }
    });
});

describe('tile scopes', () => {
    it('counts the overdue tile over the backlog alone', () => {
        expect(tileState('overdue', kpis())).toEqual(
            scheduleState({ overdue: 'only' }),
        );
    });

    it('counts the due-in-fourteen-days tile over the window without the backlog', () => {
        expect(tileState('dueSoon', kpis())).toEqual(
            scheduleState({ horizon: '14d', overdue: 'exclude' }),
        );
    });

    it('counts the certificates tile over certificates and expired included', () => {
        expect(tileState('certificates', kpis())).toEqual(
            scheduleState({ type: 'certificate', horizon: '30d' }),
        );
    });

    it('takes its horizons from the KPI payload, not from a table here', () => {
        const shifted = kpis();
        shifted.dueSoon.horizonPreset = '14d';
        shifted.certificates.horizonPreset = '60d';

        expect(tileState('dueSoon', shifted).horizon).toBe('14d');
        expect(tileState('certificates', shifted).horizon).toBe('60d');
    });

    it('identifies the tile a scope belongs to', () => {
        expect(activeTile(tileState('overdue', kpis()), kpis())).toBe(
            'overdue',
        );
        expect(activeTile(tileState('dueSoon', kpis()), kpis())).toBe(
            'dueSoon',
        );
        expect(activeTile(tileState('certificates', kpis()), kpis())).toBe(
            'certificates',
        );
    });

    it('reports no active tile for the default page or a free view', () => {
        expect(activeTile(DEFAULT_STATE, kpis())).toBeNull();
        expect(
            activeTile(scheduleState({ horizon: '14d' }), kpis()),
        ).toBeNull();
    });

    it('reports no active tile once a single day is picked', () => {
        // A day is a narrower question than any tile asked, so leaving a tile lit
        // would claim the tile's number still describes what is on screen.
        const narrowed = { ...tileState('dueSoon', kpis()), day: '2026-10-07' };

        expect(activeTile(narrowed, kpis())).toBeNull();
    });

    it('drops a picked day when the reader chooses a tile instead', () => {
        const onADay = scheduleState({ day: '2026-10-07' });

        expect(tileState('overdue', kpis()).day).toBeNull();
        expect(
            stateToQuery({ ...onADay, ...tileState('overdue', kpis()) }),
        ).toEqual({ schedule_overdue: 'only' });
    });
});

describe('naming what is on screen', () => {
    it('names the window and its record count', () => {
        const panel = schedule({ totalCount: 4, windowItemCount: 4 });

        expect(scopeSentence(panel, DEFAULT_STATE)).toBe(
            '4 Oct – 18 Oct 2026 · 4 records',
        );
    });

    it('uses the singular for exactly one record', () => {
        expect(scopeSentence(schedule({ totalCount: 1 }), DEFAULT_STATE)).toBe(
            '4 Oct – 18 Oct 2026 · 1 record',
        );
    });

    it('admits the overdue rows the window label leaves out', () => {
        const panel = schedule({ totalCount: 6, overdueCount: 2 });

        expect(scopeSentence(panel, DEFAULT_STATE)).toBe(
            '4 Oct – 18 Oct 2026 · 6 records · incl. 2 overdue',
        );
    });

    it('says nothing about the backlog once it has been excluded', () => {
        const panel = schedule({ totalCount: 4, overdueCount: 2 });
        const state = scheduleState({ overdue: 'exclude' });

        expect(scopeSentence(panel, state)).not.toContain('overdue');
    });

    it('states the overdue scope instead of a window that is not on screen', () => {
        const panel = schedule({
            totalCount: 5,
            overdueCount: 5,
            windowHidden: true,
        });

        expect(scopeSentence(panel, scheduleState({ overdue: 'only' }))).toBe(
            'Overdue only · 5',
        );
    });

    it('admits what falls past the window rather than implying there is none', () => {
        const panel = schedule({
            totalCount: 5,
            overdueCount: 5,
            nextAfter: { date: '2026-10-25', daysUntil: 21 },
        });

        expect(
            scopeSentence(panel, scheduleState({ overdue: 'only' })),
        ).toContain('next inspections due in 21 days');
    });

    it('leads with the picked day so the list is named, not just counted', () => {
        const panel = schedule({ totalCount: 2 });
        const state = scheduleState({ day: '2026-10-07' });

        expect(scopeSentence(panel, state)).toBe('Wed 7 Oct · 2 records');
    });

    it('labels the kind tabs from the counts the server measured', () => {
        const panel = schedule({
            counts: { all: 7, asset: 4, certificate: 3 },
        });

        expect(typeTabs(panel)).toEqual([
            { id: 'all', label: 'All', count: 7 },
            { id: 'asset', label: 'Inspection', count: 4 },
            { id: 'certificate', label: 'Certificate', count: 3 },
        ]);
    });

    it('names a preset, and does not invent one the server did not send', () => {
        expect(horizonLabel('7d')).toBe('7 days');
        expect(horizonLabel('99y')).toBe('99y');
    });
});

describe('counts stated in words', () => {
    it('breaks a mixed count down by kind', () => {
        expect(kindBreakdown({ asset: 3, certificate: 2 })).toBe(
            '3 inspections · 2 certificates',
        );
    });

    it('uses the singular for one', () => {
        expect(kindBreakdown({ asset: 1, certificate: 0 })).toBe(
            '1 inspection',
        );
    });

    it('says so plainly when there is nothing to count', () => {
        expect(kindBreakdown({ asset: 0, certificate: 0 })).toBe('No overdue');
    });

    it('states the oldest overdue date only when there is one', () => {
        expect(sinceSentence('2026-08-20')).toBe('since Thu, 20 Aug 2026');
        expect(sinceSentence(null)).toBe('');
    });

    it('names both kinds the way the page refers to them', () => {
        expect(kindLabel('asset')).toBe('Inspection');
        expect(kindLabel('asset', true)).toBe('Inspections');
        expect(kindLabel('certificate', true)).toBe('Certificates');
    });
});

describe('the values the URL may take', () => {
    it('lists exactly what the request validates', () => {
        expect(SCHEDULE_TYPES).toEqual(['all', 'asset', 'certificate']);
        expect(OVERDUE_MODES).toEqual(['include', 'only', 'exclude']);
    });
});
