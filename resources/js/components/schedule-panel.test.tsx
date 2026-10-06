import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { SchedulePanel } from './schedule-panel';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { ScheduleState } from '@/lib/schedule';
import { stateToQuery } from '@/lib/schedule';
import type {
    DashboardFilters,
    DashboardKpis,
    DashboardSchedule,
    ScheduleGroup,
    ScheduleItem,
} from '@/types/fire-tracker';

const AS_OF = '2026-10-04';

function item(
    key: string,
    typeName: string,
    dueDate: string,
    kind: ScheduleItem['kind'] = 'asset',
): ScheduleItem {
    return {
        key,
        kind,
        entityId: 1,
        dueDate,
        isOverdue: dueDate < AS_OF,
        daysUntil: dueDate < AS_OF ? -20 : 2,
        status: dueDate < AS_OF ? 'overdue' : 'due_soon',
        typeName,
        siteName: 'Harbourside Warehouse',
        clientName: 'Harbourside',
        reference: null,
        locationDesc: null,
        href: '/assets',
    };
}

function group(
    id: ScheduleGroup['id'],
    label: string,
    items: ScheduleItem[],
): ScheduleGroup {
    return {
        id,
        label,
        itemCount: items.length,
        byKind: {
            asset: items.filter((entry) => entry.kind === 'asset').length,
            certificate: items.filter((entry) => entry.kind === 'certificate')
                .length,
        },
        firstDate: items.at(0)?.dueDate ?? null,
        items,
    };
}

const OVERDUE_ITEMS = [
    item('asset:1', 'Extinguisher', '2026-08-01'),
    item('certificate:1', 'Annual Certificate', '2026-08-15', 'certificate'),
    item('asset:2', 'Hose Reel', '2026-09-28'),
];

const NEXT_ITEMS = [
    item('asset:3', 'Smoke Detector', AS_OF),
    item('asset:4', 'Fire Blanket', '2026-10-05'),
    item(
        'certificate:2',
        'Fire Safety Certificate',
        '2026-10-05',
        'certificate',
    ),
];

const LATER_ITEMS = [item('asset:5', 'Sprinkler Head', '2026-10-14')];

/**
 * Builds a payload, then re-derives the scope totals from whatever groups it
 * ended up with.
 *
 * The counts have to agree with the rows: the agenda reads `overdueCount` and
 * `windowItemCount` to decide whether it can offer to reveal a hidden scope, so a
 * fixture that let them drift would test a payload the server never sends.
 */
function schedule(
    overrides: Partial<DashboardSchedule> = {},
): DashboardSchedule {
    const base: DashboardSchedule = {
        from: AS_OF,
        // The server default `14d` preset: an inclusive offset of 14, so 15 cells.
        to: '2026-10-18',
        horizonDays: 15,
        horizonOffset: 14,
        horizonPreset: '14d',
        type: 'all',
        overdueMode: 'include',
        day: null,
        windowHidden: false,
        groupBoundaryDays: 7,
        groups: [
            group('overdue', 'Overdue', OVERDUE_ITEMS),
            group('next', 'Next 7 days', NEXT_ITEMS),
            group('later', 'Later', LATER_ITEMS),
        ],
        counts: { all: 7, asset: 5, certificate: 2 },
        days: [
            { date: AS_OF, isToday: true, itemCount: 1 },
            { date: '2026-10-05', isToday: false, itemCount: 2 },
            ...Array.from({ length: 13 }, (_, index) => ({
                date: `2026-10-${String(index + 6).padStart(2, '0')}`,
                isToday: false,
                itemCount: index === 8 ? 1 : 0,
            })),
        ],
        overdueSince: '2026-08-01',
        overdueByKind: { asset: 2, certificate: 1 },
        nextAfter: { date: '2026-10-25', daysUntil: 21 },
        overdueCount: 3,
        windowItemCount: 4,
        totalCount: 7,
        returnedCount: 7,
        truncated: false,
        maxItems: 60,
        maxHorizonDays: 90,
        horizonPresets: { '7d': 7, '14d': 14, '30d': 30 },
        defaultHorizonPreset: '14d',
    };

    const groups = overrides.groups ?? base.groups;
    const shown = groups.flatMap((entry) => entry.items);

    return {
        ...base,
        ...overrides,
        groups,
        overdueCount:
            overrides.overdueCount ??
            groups.find((entry) => entry.id === 'overdue')?.itemCount ??
            0,
        windowItemCount:
            overrides.windowItemCount ??
            groups
                .filter((entry) => entry.id !== 'overdue')
                .reduce((sum, entry) => sum + entry.itemCount, 0),
        totalCount: overrides.totalCount ?? shown.length,
    };
}

const KPIS: DashboardKpis = {
    overdue: { total: 3, byKind: { asset: 2, certificate: 1 } },
    dueSoon: { total: 3, horizonPreset: '7d', horizonDays: 8 },
    certificates: {
        expired: 2,
        expiringSoon: 1,
        total: 3,
        horizonPreset: '30d',
    },
    passRate: {
        percent: 67,
        band: 'warning',
        pass: 4,
        fail: 1,
        needsRepair: 1,
        total: 6,
    },
};

function renderPanel({
    scheduleOverrides = {},
    filters = {},
    onChange = vi.fn(),
    loading = false,
}: {
    scheduleOverrides?: Partial<DashboardSchedule>;
    filters?: DashboardFilters;
    onChange?: (next: ScheduleState) => void;
    loading?: boolean;
} = {}) {
    // The app shell supplies this in `app.tsx`.
    render(
        <TooltipProvider delayDuration={0}>
            <SchedulePanel
                schedule={schedule(scheduleOverrides)}
                kpis={KPIS}
                filters={filters}
                loading={loading}
                onChange={onChange}
            />
        </TooltipProvider>,
    );

    return { onChange };
}

describe('the scope it says it is showing', () => {
    it('states the window and the count, admitting the overdue rows too', () => {
        renderPanel();

        expect(
            screen.getByText(
                '4 Oct – 18 Oct 2026 · 7 records · incl. 3 overdue',
            ),
        ).toBeTruthy();
    });

    it('names the overdue scope rather than a window that is not on screen', () => {
        renderPanel({
            filters: { schedule_overdue: 'only' },
            scheduleOverrides: { windowHidden: true, overdueMode: 'only' },
        });

        expect(screen.getByText(/^Overdue only · 7/)).toBeTruthy();
    });

    it('says nothing about the backlog once it has been excluded', () => {
        renderPanel({ filters: { schedule_overdue: 'exclude' } });

        expect(
            screen.getByText('4 Oct – 18 Oct 2026 · 7 records'),
        ).toBeTruthy();
        expect(screen.queryByText(/incl\. \d+ overdue/)).toBeNull();
    });
});

describe('the three sections', () => {
    it('names each group once, rather than repeating a heading per late date', () => {
        renderPanel();

        expect(screen.getByRole('heading', { name: /^Overdue/ })).toBeTruthy();
        expect(
            screen.getByRole('heading', { name: /^Next 7 days/ }),
        ).toBeTruthy();
        expect(screen.getByRole('heading', { name: /^Later/ })).toBeTruthy();

        // Three late records, one heading.
        expect(screen.getAllByRole('heading')).toHaveLength(3);
    });

    it('breaks the overdue count down and says how old it is', () => {
        renderPanel();

        const heading = screen.getByRole('heading', { name: /^Overdue/ });

        expect(heading.textContent).toContain('3 records');
        expect(heading.textContent).toContain('2 inspections · 1 certificate');
        expect(heading.textContent).toContain('since Sat, 1 Aug 2026');
    });

    it('drops an empty section instead of rendering a hollow heading', () => {
        renderPanel({
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 7 days', NEXT_ITEMS),
                    group('later', 'Later', []),
                ],
            },
        });

        expect(screen.queryByRole('heading', { name: /^Overdue/ })).toBeNull();
        expect(screen.queryByRole('heading', { name: /^Later/ })).toBeNull();
    });

    it('folds a long group and can unfold it again', async () => {
        const many = Array.from({ length: 12 }, (_, index) =>
            item(`asset:${index}`, `Extinguisher ${index}`, '2026-09-01'),
        );
        renderPanel({
            scheduleOverrides: {
                groups: [group('overdue', 'Overdue', many)],
            },
        });

        const toggle = screen.getByRole('button', {
            name: 'Show 4 more in this group',
        });

        expect(toggle.getAttribute('aria-expanded')).toBe('false');

        await userEvent.click(toggle);

        expect(
            screen
                .getByRole('button', { name: 'Show fewer' })
                .getAttribute('aria-expanded'),
        ).toBe('true');
        // Scoped to the group's own list: the day strip contributes 15 more.
        const lists = screen.getAllByRole('list');
        expect(
            lists.filter(
                (list) => within(list).queryAllByRole('listitem').length === 12,
            ),
        ).toHaveLength(1);
    });
});

describe('empty states', () => {
    it('celebrates an empty backlog rather than showing a bare list', () => {
        renderPanel({
            filters: { schedule_overdue: 'only' },
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 7 days', []),
                    group('later', 'Later', []),
                ],
                totalCount: 0,
            },
        });

        expect(screen.getByText('Nothing overdue')).toBeTruthy();
    });

    it('offers a way out when the chosen range is simply empty', () => {
        renderPanel({
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 7 days', []),
                    group('later', 'Later', []),
                ],
                totalCount: 0,
            },
        });

        expect(screen.getByText('Nothing due in this range')).toBeTruthy();
    });

    it('offers the scope the overdue mode hid', () => {
        renderPanel({ filters: { schedule_overdue: 'exclude' } });

        expect(
            screen.getByRole('button', { name: 'Show the overdue backlog' }),
        ).toBeTruthy();
    });

    it('offers the window overdue-only mode hid', () => {
        renderPanel({
            filters: { schedule_overdue: 'only' },
            scheduleOverrides: { windowHidden: true },
        });

        expect(
            screen.getByRole('button', {
                name: 'Show everything due in this range',
            }),
        ).toBeTruthy();
    });

    it('widens only the overdue mode when the window is offered back', async () => {
        const { onChange } = renderPanel({
            filters: {
                schedule_overdue: 'only',
                schedule_type: 'certificate',
                schedule_horizon: '30d',
            },
            scheduleOverrides: { windowHidden: true },
        });

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Show everything due in this range',
            }),
        );

        // A reset here would silently discard the reader's range and type, which
        // is not what "show everything due in this range" asks for.
        expect(onChange).toHaveBeenCalledWith({
            type: 'certificate',
            horizon: '30d',
            from: null,
            to: null,
            overdue: 'include',
            day: null,
        });
    });

    it('drops the selected day when the backlog is offered, since no day cell is late', async () => {
        const { onChange } = renderPanel({
            filters: {
                schedule_overdue: 'exclude',
                schedule_day: '2026-10-05',
            },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Show the overdue backlog' }),
        );

        expect(onChange).toHaveBeenCalledWith({
            type: 'all',
            horizon: null,
            from: null,
            to: null,
            overdue: 'only',
            day: null,
        });
    });

    it('does not offer a hidden scope when there is nothing hidden', () => {
        renderPanel({
            filters: { schedule_overdue: 'exclude' },
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 7 days', NEXT_ITEMS),
                    group('later', 'Later', []),
                ],
            },
        });

        expect(
            screen.queryByRole('button', { name: 'Show the overdue backlog' }),
        ).toBeNull();
    });
});

describe('the row cap', () => {
    it('says how much is hidden and why', () => {
        renderPanel({
            scheduleOverrides: {
                truncated: true,
                returnedCount: 60,
                totalCount: 91,
            },
        });

        expect(screen.getByRole('status').textContent).toContain(
            'Showing 60 of 91 records. The view is capped at 60',
        );
    });
});

describe('controls write the query string', () => {
    it('names each range by its own size', () => {
        renderPanel();

        const range = screen.getByRole('group', { name: 'Range' });

        expect(
            within(range).getByRole('button', { name: '7 days' }),
        ).toBeTruthy();
        expect(
            within(range).getByRole('button', { name: '14 days' }),
        ).toBeTruthy();
        expect(
            within(range).getByRole('button', { name: '30 days' }),
        ).toBeTruthy();
    });

    it('marks the server default range as active without it being in the URL', () => {
        renderPanel();

        expect(
            within(screen.getByRole('group', { name: 'Range' }))
                .getByRole('button', { name: '14 days' })
                .getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('writes the whole state, not a patch, when a range is chosen', async () => {
        const { onChange } = renderPanel({
            filters: { schedule_overdue: 'exclude' },
        });

        await userEvent.click(
            within(screen.getByRole('group', { name: 'Range' })).getByRole(
                'button',
                { name: '30 days' },
            ),
        );

        expect(onChange).toHaveBeenCalledWith({
            type: 'all',
            horizon: '30d',
            from: null,
            to: null,
            overdue: 'exclude',
            day: null,
        });
    });

    it('labels the overdue control by what it does', () => {
        renderPanel();

        const group = screen.getByRole('group', { name: 'Overdue records' });

        expect(
            within(group).getByRole('button', { name: 'Include overdue' }),
        ).toBeTruthy();
        expect(
            within(group).getByRole('button', { name: 'Overdue only' }),
        ).toBeTruthy();
        expect(
            within(group).getByRole('button', { name: 'Hide overdue' }),
        ).toBeTruthy();
    });

    it('clears a chosen day when the same day is chosen again', async () => {
        const { onChange } = renderPanel({
            filters: { schedule_day: '2026-10-05' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /Mon 5 Oct/ }),
        );

        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ day: null }),
        );
    });

    it('narrowing a day keeps the rest of the state', async () => {
        const { onChange } = renderPanel({
            filters: { schedule_horizon: '7d', schedule_type: 'certificate' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /Sun 4 Oct/ }),
        );

        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({
                day: AS_OF,
                horizon: '7d',
                type: 'certificate',
            }),
        );
    });

    it('offers no reset until something has been narrowed', () => {
        renderPanel();

        expect(screen.queryByRole('button', { name: 'Reset' })).toBeNull();
    });

    it('resets to the state a shared link would carry', async () => {
        const { onChange } = renderPanel({
            filters: {
                schedule_horizon: '7d',
                schedule_type: 'certificate',
                schedule_overdue: 'exclude',
                schedule_day: '2026-10-05',
            },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Reset' }));

        // Every field, not a patch: the page is a pure function of the query, so
        // a reset that left one field behind would be a reset to the wrong view.
        expect(onChange).toHaveBeenCalledWith({
            type: 'all',
            horizon: null,
            from: null,
            to: null,
            overdue: 'include',
            day: null,
        });
    });

    it('renders an empty query when state is written back', () => {
        // The reset button and a shared link rely on defaults costing nothing.
        expect(
            stateToQuery({
                type: 'all',
                horizon: null,
                from: null,
                to: null,
                overdue: 'include',
                day: null,
            }),
        ).toEqual({});
    });
});

describe('the day strip', () => {
    it('offers one cell per day of the window and nothing else', () => {
        renderPanel();

        const strip = screen.getByRole('group', { name: 'Day in range' });

        expect(within(strip).getAllByRole('button')).toHaveLength(15);
    });

    it('spells the date out in the accessible name', () => {
        renderPanel();

        expect(
            screen.getByRole('button', { name: 'Mon 5 Oct, 2 records' }),
        ).toBeTruthy();
    });

    it('keeps a day with no work visible rather than shortening the week', () => {
        renderPanel();

        const strip = screen.getByRole('group', { name: 'Day in range' });

        expect(
            within(strip).getByRole('button', { name: 'Wed 7 Oct, 0 records' }),
        ).toBeTruthy();
    });

    it('marks the selected day as pressed', () => {
        renderPanel({ filters: { schedule_day: '2026-10-05' } });

        expect(
            screen
                .getByRole('button', { name: /Mon 5 Oct/ })
                .getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('is not shown at all in overdue-only, where days cannot filter', () => {
        renderPanel({
            filters: { schedule_overdue: 'only' },
            scheduleOverrides: { windowHidden: true, overdueMode: 'only' },
        });

        expect(
            screen.queryByRole('group', { name: 'Day in range' }),
        ).toBeNull();
        const range = within(
            screen.getByRole('group', { name: 'Range' }),
        ).getByRole('button', { name: '14 days' }) as HTMLButtonElement;

        expect(range.disabled).toBe(true);
    });
});

describe('the kind tabs', () => {
    it('labels each tab with the count the server measured', () => {
        renderPanel();

        const tabs = screen.getByRole('group', { name: 'Record kind' });

        expect(
            within(tabs).getByRole('button', { name: 'All, 7 records' }),
        ).toBeTruthy();
        expect(
            within(tabs).getByRole('button', { name: 'Inspection, 5 records' }),
        ).toBeTruthy();
        expect(
            within(tabs).getByRole('button', {
                name: 'Certificate, 2 records',
            }),
        ).toBeTruthy();
    });

    it('writes the chosen kind without disturbing the rest', async () => {
        const { onChange } = renderPanel({
            filters: { schedule_horizon: '30d' },
        });

        await userEvent.click(
            within(
                screen.getByRole('group', { name: 'Record kind' }),
            ).getByRole('button', { name: /Certificate/ }),
        );

        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'certificate', horizon: '30d' }),
        );
    });
});

describe('loading', () => {
    it('announces itself rather than silently swapping the rows', () => {
        renderPanel({ loading: true });

        expect(screen.getAllByRole('status').length).toBeGreaterThan(0);
        expect(
            screen.getByText('Loading schedule').closest('[aria-busy]'),
        ).not.toBeNull();
    });

    it('disables the controls that would queue up further visits', () => {
        renderPanel({ loading: true });

        const control = within(
            screen.getByRole('group', { name: 'Overdue records' }),
        ).getByRole('button', { name: 'Overdue only' }) as HTMLButtonElement;

        expect(control.disabled).toBe(true);
    });
});

/**
 * The QA harness in `qa/` measures the rendered page through these attributes.
 *
 * They earn a test because their absence fails silently and in the worst
 * direction: a probe that finds no `[data-row]` reports zero rows, the
 * "rows are present" gate has nothing to contradict, and the run passes while
 * measuring nothing. That is exactly what happened once while this was being
 * built, so the hooks are now load-bearing rather than incidental.
 */
/**
 * The distinct `data-kind` values on a set of rows, sorted.
 *
 * The attribute is read through `HTMLElement` because `querySelectorAll` hands
 * back `Element`, and `dataset.kind` is optional even though every row the
 * agenda renders sets it — hence the filter rather than a cast.
 */
function kindsOf(rows: NodeListOf<Element>): string[] {
    return [
        ...new Set(
            [...rows]
                .map((row) => (row as HTMLElement).dataset.kind)
                .filter((kind): kind is string => kind !== undefined),
        ),
    ].sort((a, b) => a.localeCompare(b));
}

describe('the hooks the QA harness measures', () => {
    it('marks every row with the kind the certificates tile is checked against', () => {
        const { container } = render(
            <TooltipProvider delayDuration={0}>
                <SchedulePanel
                    schedule={schedule()}
                    kpis={KPIS}
                    filters={{}}
                    loading={false}
                    onChange={vi.fn()}
                />
            </TooltipProvider>,
        );

        const rows = container.querySelectorAll('[data-row]');

        expect(rows).toHaveLength(7);
        expect(kindsOf(rows)).toEqual(['asset', 'certificate']);
    });

    it('names each group, so the three bands can be counted separately', () => {
        const { container } = render(
            <TooltipProvider delayDuration={0}>
                <SchedulePanel
                    schedule={schedule()}
                    kpis={KPIS}
                    filters={{}}
                    loading={false}
                    onChange={vi.fn()}
                />
            </TooltipProvider>,
        );

        expect(
            [...container.querySelectorAll('[data-group]')].map(
                (section) => (section as HTMLElement).dataset.group,
            ),
        ).toEqual(['overdue', 'next', 'later']);
    });

    it('dates every day cell and marks the agenda and strip as a whole', () => {
        const { container } = render(
            <TooltipProvider delayDuration={0}>
                <SchedulePanel
                    schedule={schedule()}
                    kpis={KPIS}
                    filters={{}}
                    loading={false}
                    onChange={vi.fn()}
                />
            </TooltipProvider>,
        );

        expect(container.querySelector('[data-agenda]')).not.toBeNull();
        expect(container.querySelector('[data-day-strip]')).not.toBeNull();
        // An inclusive `14d` offset is 15 cells, not 14.
        expect(container.querySelectorAll('[data-day]')).toHaveLength(15);
    });
});
