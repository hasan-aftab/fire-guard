import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { SchedulePanel } from './schedule-panel';
import { ScheduleDayStrip } from './schedule-day-strip';
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
    item('asset:5', 'Sprinkler Head', '2026-10-14'),
];

const LATER_ITEMS: ScheduleItem[] = [];

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
        groupBoundaryDays: 14,
        groups: [
            group('overdue', 'Overdue', OVERDUE_ITEMS),
            group('next', 'Next 14 days', NEXT_ITEMS),
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
        page: 1,
        lastPage: 1,
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
    dueSoon: { total: 3, horizonPreset: '14d', horizonDays: 15 },
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
    error,
    onRetry,
}: {
    scheduleOverrides?: Partial<DashboardSchedule>;
    filters?: DashboardFilters;
    onChange?: (next: ScheduleState) => void;
    loading?: boolean;
    error?: string;
    onRetry?: () => void;
} = {}) {
    // The app shell supplies this in `app.tsx`.
    render(
        <TooltipProvider delayDuration={0}>
            <SchedulePanel
                schedule={schedule(scheduleOverrides)}
                kpis={KPIS}
                filters={filters}
                today={AS_OF}
                loading={loading}
                error={error}
                onChange={onChange}
                onRetry={onRetry}
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
            screen.getByRole('heading', { name: /^Next 14 days/ }),
        ).toBeTruthy();
        expect(screen.queryByRole('heading', { name: /^Later/ })).toBeNull();

        // Three late records, one heading; the default window ends at day 14.
        expect(screen.getAllByRole('heading')).toHaveLength(2);
    });

    it('shows the overdue group count without repeating tile detail', () => {
        renderPanel();

        const heading = screen.getByRole('heading', { name: /^Overdue/ });

        expect(heading.textContent).toContain('3');
        expect(heading.textContent).not.toContain('since');
    });

    it('drops an empty section instead of rendering a hollow heading', () => {
        renderPanel({
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 14 days', NEXT_ITEMS),
                    group('later', 'Later', []),
                ],
            },
        });

        expect(screen.queryByRole('heading', { name: /^Overdue/ })).toBeNull();
        expect(screen.queryByRole('heading', { name: /^Later/ })).toBeNull();
    });

    it('shows a later section when a wider date filter includes work after day 14', () => {
        renderPanel({
            filters: { schedule_horizon: '30d' },
            scheduleOverrides: {
                to: '2026-11-03',
                horizonPreset: '30d',
                groups: [
                    group('overdue', 'Overdue', OVERDUE_ITEMS),
                    group('next', 'Next 14 days', NEXT_ITEMS),
                    group('later', 'Later', [
                        item('asset:6', 'Exit light', '2026-10-25'),
                    ]),
                ],
            },
        });

        expect(screen.getByRole('heading', { name: /^Later/ })).toBeTruthy();
        expect(screen.getByText('Exit light')).toBeTruthy();
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
        // Scoped to the group's own list.
        const lists = screen.getAllByRole('list');
        expect(
            lists.filter(
                (list) => within(list).queryAllByRole('listitem').length === 12,
            ),
        ).toHaveLength(1);
    });
});

describe('loading and failure states', () => {
    it('keeps the current work visible with a retry after refresh fails', async () => {
        const onRetry = vi.fn();
        renderPanel({ error: 'Work could not refresh.', onRetry });

        expect(screen.getByRole('alert').textContent).toContain(
            'Work could not refresh.',
        );
        expect(screen.getByText('Extinguisher')).toBeTruthy();
        await userEvent.click(screen.getByRole('button', { name: 'Retry' }));
        expect(onRetry).toHaveBeenCalledOnce();
    });

    it('shows a skeleton while the schedule is loading for the first time', () => {
        render(
            <TooltipProvider delayDuration={0}>
                <SchedulePanel
                    schedule={null}
                    kpis={null}
                    filters={{}}
                    today={AS_OF}
                    loading
                    onChange={vi.fn()}
                />
            </TooltipProvider>,
        );

        expect(
            screen.getByRole('status', { name: 'Loading work by due date' }),
        ).toBeTruthy();
        expect(screen.queryByText('Work could not load.')).toBeNull();
    });
});

describe('empty states', () => {
    it('celebrates an empty backlog rather than showing a bare list', () => {
        renderPanel({
            filters: { schedule_overdue: 'only' },
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 14 days', []),
                    group('later', 'Later', []),
                ],
                totalCount: 0,
            },
        });

        expect(screen.getByText('Nothing overdue')).toBeTruthy();
    });

    it('offers a way out when the chosen range is simply empty', () => {
        renderPanel({
            filters: { schedule_horizon: '7d' },
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 14 days', []),
                    group('later', 'Later', []),
                ],
                totalCount: 0,
            },
        });

        expect(screen.getByText('Nothing due in this range')).toBeTruthy();
    });

    it('names an empty default view as all clear for the current window', () => {
        renderPanel({
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 14 days', []),
                    group('later', 'Later', []),
                ],
                totalCount: 0,
            },
        });

        expect(screen.getByText('All clear for now')).toBeTruthy();
        expect(
            screen.getByText(
                'No overdue work or due dates in the next 14 days.',
            ),
        ).toBeTruthy();
    });

    it('explains that due dates need recording when no work exists yet', () => {
        renderPanel({
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 14 days', []),
                    group('later', 'Later', []),
                ],
                nextAfter: null,
                totalCount: 0,
            },
        });

        expect(screen.getByText('No due dates yet')).toBeTruthy();
        expect(
            screen.getByText(
                'Add equipment or certificates to start tracking work.',
            ),
        ).toBeTruthy();
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
            dateMode: null,
            horizon: '30d',
            from: null,
            to: null,
            overdue: 'include',
            day: null,
            page: 1,
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
            dateMode: null,
            horizon: null,
            from: null,
            to: null,
            overdue: 'only',
            day: null,
            page: 1,
        });
    });

    it('does not offer a hidden scope when there is nothing hidden', () => {
        renderPanel({
            filters: { schedule_overdue: 'exclude' },
            scheduleOverrides: {
                groups: [
                    group('overdue', 'Overdue', []),
                    group('next', 'Next 14 days', NEXT_ITEMS),
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
    it('reports the visible range and opens the next page', async () => {
        const { onChange } = renderPanel({
            scheduleOverrides: {
                truncated: true,
                returnedCount: 60,
                totalCount: 91,
                lastPage: 2,
            },
        });

        expect(screen.getByRole('status').textContent).toContain('1–60 of 91');
        await userEvent.click(screen.getByRole('button', { name: 'Next' }));
        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ page: 2 }),
        );
    });
});

describe('controls write the query string', () => {
    it('names the current due-date scope in one control', () => {
        renderPanel();
        expect(
            screen.getByRole('button', {
                name: 'Due date: Overdue + next 14 days',
            }),
        ).toBeTruthy();
    });

    it('offers presets for the available due-date questions', async () => {
        renderPanel();
        await userEvent.click(
            screen.getByRole('button', { name: /Due date:/ }),
        );
        expect(
            screen.getByRole('button', { name: 'Next 7 days' }),
        ).toBeTruthy();
        expect(
            screen.getByRole('button', { name: 'Next 14 days' }),
        ).toBeTruthy();
        expect(
            screen.getByRole('button', { name: 'Next 30 days' }),
        ).toBeTruthy();
    });

    it('writes the whole state when a preset is chosen', async () => {
        const { onChange } = renderPanel({
            filters: { schedule_overdue: 'exclude' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /Due date:/ }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Next 30 days' }),
        );

        expect(onChange).toHaveBeenCalledWith({
            type: 'all',
            dateMode: null,
            horizon: '30d',
            from: null,
            to: null,
            overdue: 'exclude',
            day: null,
            page: 1,
        });
    });

    it('labels inclusive custom date modes', async () => {
        renderPanel();
        await userEvent.click(
            screen.getByRole('button', { name: /Due date:/ }),
        );
        expect(screen.getByText('Choose dates · inclusive')).toBeTruthy();
        expect(screen.getByText('Through')).toBeTruthy();
        expect(screen.getByText('From')).toBeTruthy();
        expect(screen.getByText('Between')).toBeTruthy();
    });

    it('waits for both range dates before applying a custom filter', async () => {
        const { onChange } = renderPanel();
        await userEvent.click(
            screen.getByRole('button', { name: /Due date:/ }),
        );
        const apply = screen.getByRole('button', {
            name: 'Apply',
        }) as HTMLButtonElement;
        expect(apply.disabled).toBe(true);

        await userEvent.click(
            screen.getByRole('button', {
                name: /Today, Sunday, October 4th, 2026/,
            }),
        );
        expect(apply.disabled).toBe(true);
        expect(onChange).not.toHaveBeenCalled();

        await userEvent.click(
            screen.getByRole('button', { name: /Monday, October 5th, 2026/ }),
        );
        expect(apply.disabled).toBe(false);
        await userEvent.click(apply);
        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({
                dateMode: 'between',
                from: '2026-10-04',
                to: '2026-10-05',
            }),
        );
    });

    it('clears a selected day from a shared link', async () => {
        const { onChange } = renderPanel({
            filters: { schedule_day: '2026-10-05' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /2026-10-05/ }),
        );

        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ day: null }),
        );
    });

    it('clearing a selected day keeps the rest of the state', async () => {
        const { onChange } = renderPanel({
            filters: {
                schedule_horizon: '7d',
                schedule_type: 'certificate',
                schedule_day: '2026-10-05',
            },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /2026-10-05/ }),
        );

        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({
                day: null,
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
            dateMode: null,
            horizon: null,
            from: null,
            to: null,
            overdue: 'include',
            day: null,
            page: 1,
        });
    });

    it('renders an empty query when state is written back', () => {
        // The reset button and a shared link rely on defaults costing nothing.
        expect(
            stateToQuery({
                type: 'all',
                dateMode: null,
                horizon: null,
                from: null,
                to: null,
                overdue: 'include',
                day: null,
                page: 1,
            }),
        ).toEqual({});
    });
});

describe('the day strip', () => {
    it('offers one cell per day of the window and nothing else', () => {
        render(
            <ScheduleDayStrip
                days={schedule().days}
                selected={null}
                onSelect={vi.fn()}
            />,
        );

        const strip = screen.getByRole('group', { name: 'Day in range' });

        expect(within(strip).getAllByRole('button')).toHaveLength(15);
    });

    it('spells the date out in the accessible name', () => {
        render(
            <ScheduleDayStrip
                days={schedule().days}
                selected={null}
                onSelect={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Mon 5 Oct, 2 records' }),
        ).toBeTruthy();
    });

    it('keeps a day with no work visible rather than shortening the week', () => {
        render(
            <ScheduleDayStrip
                days={schedule().days}
                selected={null}
                onSelect={vi.fn()}
            />,
        );

        const strip = screen.getByRole('group', { name: 'Day in range' });

        expect(
            within(strip).getByRole('button', { name: 'Wed 7 Oct, 0 records' }),
        ).toBeTruthy();
    });

    it('marks the selected day as pressed', () => {
        render(
            <ScheduleDayStrip
                days={schedule().days}
                selected="2026-10-05"
                onSelect={vi.fn()}
            />,
        );

        expect(
            screen
                .getByRole('button', { name: /Mon 5 Oct/ })
                .getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('is not shown in the compact agenda', () => {
        renderPanel({
            filters: { schedule_overdue: 'only' },
            scheduleOverrides: { windowHidden: true, overdueMode: 'only' },
        });

        expect(
            screen.queryByRole('group', { name: 'Day in range' }),
        ).toBeNull();
        expect(
            screen.getByRole('button', { name: 'Due date: Overdue' }),
        ).toBeTruthy();
    });
});

describe('the kind tabs', () => {
    it('labels each tab with the count the server measured', () => {
        renderPanel();

        const tabs = screen.getByRole('group', { name: 'Record type' });

        expect(
            within(tabs).getByRole('button', { name: 'All 7' }),
        ).toBeTruthy();
        expect(
            within(tabs).getByRole('button', { name: 'Inspection 5' }),
        ).toBeTruthy();
        expect(
            within(tabs).getByRole('button', {
                name: 'Certificate 2',
            }),
        ).toBeTruthy();
    });

    it('writes the chosen kind without disturbing the rest', async () => {
        const { onChange } = renderPanel({
            filters: { schedule_horizon: '30d' },
        });

        await userEvent.click(
            within(
                screen.getByRole('group', { name: 'Record type' }),
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
            screen.getByText('Updating work').closest('[aria-busy]'),
        ).not.toBeNull();
    });

    it('disables the controls that would queue up further visits', () => {
        renderPanel({ loading: true });

        const control = screen.getByRole('button', {
            name: /Due date:/,
        }) as HTMLButtonElement;

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
                    today={AS_OF}
                    loading={false}
                    onChange={vi.fn()}
                />
            </TooltipProvider>,
        );

        const rows = container.querySelectorAll('[data-row]');

        expect(rows).toHaveLength(7);
        expect(kindsOf(rows)).toEqual(['asset', 'certificate']);
    });

    it('names each visible group for the layout audit', () => {
        const { container } = render(
            <TooltipProvider delayDuration={0}>
                <SchedulePanel
                    schedule={schedule()}
                    kpis={KPIS}
                    filters={{}}
                    today={AS_OF}
                    loading={false}
                    onChange={vi.fn()}
                />
            </TooltipProvider>,
        );

        expect(
            [...container.querySelectorAll('[data-group]')].map(
                (section) => (section as HTMLElement).dataset.group,
            ),
        ).toEqual(['overdue', 'next']);
    });

    it('marks the agenda without an extra day strip', () => {
        const { container } = render(
            <TooltipProvider delayDuration={0}>
                <SchedulePanel
                    schedule={schedule()}
                    kpis={KPIS}
                    filters={{}}
                    today={AS_OF}
                    loading={false}
                    onChange={vi.fn()}
                />
            </TooltipProvider>,
        );

        expect(container.querySelector('[data-agenda]')).not.toBeNull();
        expect(container.querySelector('[data-day-strip]')).toBeNull();
    });
});
