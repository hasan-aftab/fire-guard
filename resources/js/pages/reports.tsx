import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FileText, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    download,
    index,
    regenerate,
    show,
    store,
} from '@/actions/App/Http/Controllers/ReportController';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types/fire-tracker';

type Option = { value: string; label: string };
type Client = { id: number; company_name: string };
type Site = { id: number; client_id: number; site_name: string };
type SavedReport = {
    id: number;
    report_type: string;
    title: string;
    client_id: number;
    client_name: string;
    site_id: number | null;
    site_name: string;
    date_from: string;
    date_to: string;
    include_entered_in_error: boolean;
    generated_at: string;
    generated_by_name: string;
    regenerated_from_id: number | null;
};
type HistoryFilters = {
    type: string;
    client_id: string;
    site_id: string;
    generated_from: string;
    generated_to: string;
    period_from: string;
    period_to: string;
};

function Choice({
    id,
    value,
    options,
    placeholder,
    onChange,
    disabled = false,
    invalid = false,
    required = false,
}: {
    id: string;
    value: string;
    options: Option[];
    placeholder: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    invalid?: boolean;
    required?: boolean;
}) {
    return (
        <Select
            value={value || 'none'}
            onValueChange={(next) => onChange(next === 'none' ? '' : next)}
            disabled={disabled}
        >
            <SelectTrigger
                id={id}
                aria-invalid={invalid}
                aria-required={required}
                aria-describedby={invalid ? `${id}-error` : undefined}
                className="w-full"
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none">{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function dateLabel(value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value}T12:00:00`),
    );
}

export default function Reports({
    records,
    filters,
    clients,
    sites,
    reportTypes,
    recentReport,
}: {
    records: Paginated<SavedReport>;
    filters: Partial<HistoryFilters>;
    clients: Client[];
    sites: Site[];
    reportTypes: Option[];
    recentReport: SavedReport | null;
}) {
    const { today } = usePage().props;
    const form = useForm({
        client_id: '',
        site_id: '',
        type: reportTypes[0]?.value ?? '',
        date_from: `${today.slice(0, 7)}-01`,
        date_to: today,
        include_entered_in_error: false,
    });
    const reportError = (form.errors as Record<string, string | undefined>)
        .report;
    const [history, setHistory] = useState<HistoryFilters>({
        type: filters.type ?? '',
        client_id: filters.client_id ?? '',
        site_id: filters.site_id ?? '',
        generated_from: filters.generated_from ?? '',
        generated_to: filters.generated_to ?? '',
        period_from: filters.period_from ?? '',
        period_to: filters.period_to ?? '',
    });
    const [filtering, setFiltering] = useState(false);
    const [regeneratingId, setRegeneratingId] = useState<number | null>(null);
    const [regenerationError, setRegenerationError] = useState('');
    const clientOptions = clients.map((client) => ({
        value: String(client.id),
        label: client.company_name,
    }));
    const siteOptions = (clientId: string) =>
        sites
            .filter((site) => String(site.client_id) === clientId)
            .map((site) => ({
                value: String(site.id),
                label: site.site_name,
            }));
    const applyHistory = (values: HistoryFilters, page = 1) => {
        const query = Object.fromEntries(
            Object.entries(values).filter(([, value]) => value !== ''),
        );
        router.get(
            index().url,
            { ...query, page },
            {
                onStart: () => setFiltering(true),
                onFinish: () => setFiltering(false),
            },
        );
    };
    const actions = (report: SavedReport) => (
        <div className="flex items-center gap-1">
            <Button asChild variant="outline" size="sm">
                <a
                    href={show(report.id).url}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    View
                </a>
            </Button>
            <Button asChild variant="outline" size="sm">
                <a href={download(report.id).url}>Download</a>
            </Button>
            <Button
                variant="ghost"
                size="sm"
                disabled={regeneratingId !== null}
                onClick={() => {
                    setRegenerationError('');
                    setRegeneratingId(report.id);
                    router.post(
                        regenerate(report.id).url,
                        {},
                        {
                            onError: (errors) =>
                                setRegenerationError(
                                    errors.report ??
                                        'Could not generate the report again.',
                                ),
                            onFinish: () => setRegeneratingId(null),
                        },
                    );
                }}
            >
                <RotateCcw data-icon="inline-start" />
                Generate Again
            </Button>
        </div>
    );

    return (
        <div>
            <Head title="Reports" />
            <PageHeader
                title="Reports"
                description="Generate PDFs and retrieve the exact reports saved earlier."
            />
            <Card id="generate-report" className="mb-6">
                <CardHeader>
                    <CardTitle>Generate Report</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store().url);
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="report-client">Client *</Label>
                                <Choice
                                    id="report-client"
                                    value={form.data.client_id}
                                    options={clientOptions}
                                    placeholder="Select client"
                                    invalid={!!form.errors.client_id}
                                    required
                                    onChange={(value) => {
                                        form.setData((data) => ({
                                            ...data,
                                            client_id: value,
                                            site_id: '',
                                        }));
                                    }}
                                />
                                {form.errors.client_id && (
                                    <p
                                        id="report-client-error"
                                        className="text-sm text-destructive"
                                    >
                                        {form.errors.client_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="report-site">Site</Label>
                                <Choice
                                    id="report-site"
                                    value={form.data.site_id}
                                    options={siteOptions(form.data.client_id)}
                                    placeholder="All sites"
                                    disabled={!form.data.client_id}
                                    invalid={!!form.errors.site_id}
                                    onChange={(value) =>
                                        form.setData('site_id', value)
                                    }
                                />
                                {form.errors.site_id && (
                                    <p
                                        id="report-site-error"
                                        className="text-sm text-destructive"
                                    >
                                        {form.errors.site_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="report-type">
                                    Report type *
                                </Label>
                                <Choice
                                    id="report-type"
                                    value={form.data.type}
                                    options={reportTypes}
                                    placeholder="Select report type"
                                    invalid={!!form.errors.type}
                                    required
                                    onChange={(value) =>
                                        form.setData('type', value)
                                    }
                                />
                                {form.errors.type && (
                                    <p
                                        id="report-type-error"
                                        className="text-sm text-destructive"
                                    >
                                        {form.errors.type}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="report-from">From *</Label>
                                <Input
                                    id="report-from"
                                    type="date"
                                    required
                                    value={form.data.date_from}
                                    aria-invalid={!!form.errors.date_from}
                                    onChange={(event) =>
                                        form.setData(
                                            'date_from',
                                            event.target.value,
                                        )
                                    }
                                />
                                {form.errors.date_from && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.date_from}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="report-to">To *</Label>
                                <Input
                                    id="report-to"
                                    type="date"
                                    required
                                    value={form.data.date_to}
                                    aria-invalid={!!form.errors.date_to}
                                    onChange={(event) =>
                                        form.setData(
                                            'date_to',
                                            event.target.value,
                                        )
                                    }
                                />
                                {form.errors.date_to && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.date_to}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="report-entered-in-error"
                                checked={form.data.include_entered_in_error}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'include_entered_in_error',
                                        checked === true,
                                    )
                                }
                            />
                            <Label htmlFor="report-entered-in-error">
                                Include records entered in error
                            </Label>
                        </div>
                        {reportError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {reportError}
                            </p>
                        )}
                        <Button
                            type="submit"
                            disabled={form.processing || clients.length === 0}
                        >
                            <FileText data-icon="inline-start" />
                            {form.processing ? 'Generating…' : 'Generate PDF'}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            {recentReport && (
                <Card className="mb-6 border-success" role="status">
                    <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                        <p className="text-sm font-medium">
                            {recentReport.title} saved for{' '}
                            {recentReport.client_name}.
                        </p>
                        {actions(recentReport)}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Report History</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyHistory(history);
                        }}
                        className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div className="space-y-1">
                            <Label htmlFor="history-type">Report type</Label>
                            <Choice
                                id="history-type"
                                value={history.type}
                                options={reportTypes}
                                placeholder="All types"
                                onChange={(type) =>
                                    setHistory({ ...history, type })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="history-client">Client</Label>
                            <Choice
                                id="history-client"
                                value={history.client_id}
                                options={clientOptions}
                                placeholder="All clients"
                                onChange={(client_id) =>
                                    setHistory({
                                        ...history,
                                        client_id,
                                        site_id: '',
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="history-site">Site</Label>
                            <Choice
                                id="history-site"
                                value={history.site_id}
                                options={siteOptions(history.client_id)}
                                placeholder="All sites"
                                disabled={!history.client_id}
                                onChange={(site_id) =>
                                    setHistory({ ...history, site_id })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="history-generated-from">
                                Generated from
                            </Label>
                            <Input
                                id="history-generated-from"
                                type="date"
                                value={history.generated_from}
                                onChange={(event) =>
                                    setHistory({
                                        ...history,
                                        generated_from: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="history-generated-to">
                                Generated to
                            </Label>
                            <Input
                                id="history-generated-to"
                                type="date"
                                value={history.generated_to}
                                onChange={(event) =>
                                    setHistory({
                                        ...history,
                                        generated_to: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="history-period-from">
                                Period from
                            </Label>
                            <Input
                                id="history-period-from"
                                type="date"
                                value={history.period_from}
                                onChange={(event) =>
                                    setHistory({
                                        ...history,
                                        period_from: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="history-period-to">Period to</Label>
                            <Input
                                id="history-period-to"
                                type="date"
                                value={history.period_to}
                                onChange={(event) =>
                                    setHistory({
                                        ...history,
                                        period_to: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="flex items-end gap-2">
                            <Button type="submit" disabled={filtering}>
                                {filtering ? 'Filtering…' : 'Apply filters'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={filtering}
                                onClick={() => {
                                    const empty = {
                                        type: '',
                                        client_id: '',
                                        site_id: '',
                                        generated_from: '',
                                        generated_to: '',
                                        period_from: '',
                                        period_to: '',
                                    };
                                    setHistory(empty);
                                    applyHistory(empty);
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </form>
                    {regenerationError && (
                        <p role="alert" className="text-sm text-destructive">
                            {regenerationError}
                        </p>
                    )}
                    <DataTable
                        scrollLabel="Report history table"
                        data={records.data}
                        rowKey={(report) => report.id}
                        emptyMessage="No reports match these filters."
                        columns={[
                            {
                                key: 'report',
                                header: 'Report',
                                render: (report) => (
                                    <div>
                                        <span className="font-medium">
                                            {report.title}
                                        </span>
                                        {report.include_entered_in_error && (
                                            <p className="text-xs text-muted-foreground">
                                                Includes entered-in-error
                                                records
                                            </p>
                                        )}
                                        {report.regenerated_from_id && (
                                            <p className="text-xs text-muted-foreground">
                                                Generated from #
                                                {report.regenerated_from_id}
                                            </p>
                                        )}
                                    </div>
                                ),
                            },
                            {
                                key: 'client',
                                header: 'Client',
                                render: (report) => report.client_name,
                            },
                            {
                                key: 'site',
                                header: 'Site',
                                render: (report) =>
                                    report.site_name || 'All sites',
                            },
                            {
                                key: 'period',
                                header: 'Period',
                                render: (report) =>
                                    `${dateLabel(report.date_from)} – ${dateLabel(report.date_to)}`,
                            },
                            {
                                key: 'generated',
                                header: 'Generated',
                                render: (report) =>
                                    new Intl.DateTimeFormat(undefined, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    }).format(new Date(report.generated_at)),
                            },
                            {
                                key: 'by',
                                header: 'Generated by',
                                render: (report) => report.generated_by_name,
                            },
                            {
                                key: 'actions',
                                header: 'Actions',
                                render: actions,
                            },
                        ]}
                    />
                    <Pagination
                        currentPage={records.current_page}
                        totalItems={records.total}
                        pageSize={records.per_page}
                        loading={filtering}
                        onPageChange={(page) =>
                            applyHistory(filtersToHistory(filters), page)
                        }
                    />
                </CardContent>
            </Card>
        </div>
    );
}

function filtersToHistory(filters: Partial<HistoryFilters>): HistoryFilters {
    return {
        type: filters.type ?? '',
        client_id: filters.client_id ?? '',
        site_id: filters.site_id ?? '',
        generated_from: filters.generated_from ?? '',
        generated_to: filters.generated_to ?? '',
        period_from: filters.period_from ?? '',
        period_to: filters.period_to ?? '',
    };
}
