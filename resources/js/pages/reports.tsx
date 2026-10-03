import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Archive,
    CalendarDays,
    Check,
    ChevronDown,
    Download,
    Eye,
    FileCheck2,
    FileSearch,
    FileText,
    RotateCcw,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    download,
    index,
    regenerate,
    show,
    store,
} from '@/actions/App/Http/Controllers/ReportController';
import { index as clientsIndex } from '@/actions/App/Http/Controllers/ClientController';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
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

const dateFilterKeys: (keyof HistoryFilters)[] = [
    'generated_from',
    'generated_to',
    'period_from',
    'period_to',
];

function emptyFilters(): HistoryFilters {
    return {
        type: '',
        client_id: '',
        site_id: '',
        generated_from: '',
        generated_to: '',
        period_from: '',
        period_to: '',
    };
}

function filtersToHistory(filters: Partial<HistoryFilters>): HistoryFilters {
    return { ...emptyFilters(), ...filters };
}

function dateLabel(value: string): string {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value + 'T12:00:00'),
    );
}

function generatedLabel(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function typeDetails(value: string): {
    icon: LucideIcon;
    description: string;
} {
    switch (value) {
        case 'inspection':
            return {
                icon: FileSearch,
                description: 'Inspection results and evidence',
            };
        case 'service':
            return {
                icon: FileCheck2,
                description: 'Service view of inspection activity',
            };
        case 'maintenance':
            return {
                icon: SlidersHorizontal,
                description: 'Maintenance view of inspection activity',
            };
        case 'audit':
            return {
                icon: Archive,
                description: 'Assets, certificates and activity',
            };
        default:
            return {
                icon: FileText,
                description: 'Report from current records',
            };
    }
}

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
                aria-describedby={invalid ? id + '-error' : undefined}
                className="w-full bg-background"
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

function ReportActions({
    report,
    onRegenerate,
    regenerating,
    disabled,
}: {
    report: SavedReport;
    onRegenerate: (report: SavedReport) => void;
    regenerating: boolean;
    disabled: boolean;
}) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <Button asChild size="sm" variant="outline">
                <a
                    href={show(report.id).url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={
                        'View original ' +
                        report.title +
                        ' for ' +
                        report.client_name
                    }
                >
                    <Eye data-icon="inline-start" />
                    View
                </a>
            </Button>
            <Button asChild size="sm" variant="outline">
                <a
                    href={download(report.id).url}
                    aria-label={
                        'Download original ' +
                        report.title +
                        ' for ' +
                        report.client_name
                    }
                >
                    <Download data-icon="inline-start" />
                    Download
                </a>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                disabled={disabled}
                onClick={() => onRegenerate(report)}
                aria-label={
                    'Generate a new ' +
                    report.title +
                    ' for ' +
                    report.client_name
                }
            >
                <RotateCcw data-icon="inline-start" />
                {regenerating ? 'Generating…' : 'Generate Again'}
            </Button>
        </div>
    );
}

function ReportIdentity({ report }: { report: SavedReport }) {
    const Icon = typeDetails(report.report_type).icon;
    return (
        <div className="flex min-w-0 items-start gap-3">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-brand/30 bg-brand/10 text-brand-foreground dark:text-brand">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <p className="font-medium text-foreground">{report.title}</p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Report #{report.id}
                    {report.regenerated_from_id &&
                        ' · From #' + report.regenerated_from_id}
                </p>
                {report.include_entered_in_error && (
                    <Badge variant="outline" className="mt-2 whitespace-normal">
                        Includes entered-in-error records
                    </Badge>
                )}
            </div>
        </div>
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
        date_from: today.slice(0, 7) + '-01',
        date_to: today,
        include_entered_in_error: false,
    });
    const generationForm = useRef<HTMLFormElement>(null);
    const reportError = (form.errors as Record<string, string | undefined>)
        .report;
    const [history, setHistory] = useState<HistoryFilters>(() =>
        filtersToHistory(filters),
    );
    const [showDateFilters, setShowDateFilters] = useState(() =>
        dateFilterKeys.some((key) => Boolean(filters[key])),
    );
    const [filtering, setFiltering] = useState(false);
    const [filterError, setFilterError] = useState('');
    const [regeneratingId, setRegeneratingId] = useState<number | null>(null);
    const [regenerationError, setRegenerationError] = useState('');
    const applied = filtersToHistory(filters);
    const activeFilterCount = Object.values(applied).filter(Boolean).length;
    const dateFilterCount = dateFilterKeys.filter((key) =>
        Boolean(history[key]),
    ).length;
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
    const selectedClient =
        clients.find((client) => String(client.id) === form.data.client_id)
            ?.company_name ?? 'Choose a client';
    const selectedSite = form.data.site_id
        ? sites.find((site) => String(site.id) === form.data.site_id)?.site_name
        : 'All client sites';
    const selectedType =
        reportTypes.find((type) => type.value === form.data.type)?.label ??
        'Choose a report type';

    const applyHistory = (values: HistoryFilters, page = 1) => {
        if (
            values.generated_from &&
            values.generated_to &&
            values.generated_from > values.generated_to
        ) {
            setShowDateFilters(true);
            setFilterError('Generated from must be on or before generated to.');
            return;
        }
        if (
            values.period_from &&
            values.period_to &&
            values.period_from > values.period_to
        ) {
            setShowDateFilters(true);
            setFilterError('Period from must be on or before period to.');
            return;
        }
        setFilterError('');
        const query = Object.fromEntries(
            Object.entries(values).filter(([, value]) => value !== ''),
        );
        router.get(
            index().url,
            { ...query, page },
            {
                preserveScroll: true,
                onStart: () => setFiltering(true),
                onError: (errors) =>
                    setFilterError(
                        Object.values(errors)[0] ??
                            'Could not apply the report filters.',
                    ),
                onFinish: () => setFiltering(false),
            },
        );
    };
    const resetHistory = () => {
        const empty = emptyFilters();
        setHistory(empty);
        setFilterError('');
        applyHistory(empty);
    };
    const removeFilter = (key: keyof HistoryFilters) => {
        const next = { ...applied, [key]: '' };
        if (key === 'client_id') {
            next.site_id = '';
        }
        setHistory(next);
        applyHistory(next);
    };
    const regenerateReport = (report: SavedReport) => {
        setRegenerationError('');
        setRegeneratingId(report.id);
        router.post(
            regenerate(report.id).url,
            {},
            {
                onError: (errors) =>
                    setRegenerationError(
                        errors.report ?? 'Could not generate the report again.',
                    ),
                onFinish: () => setRegeneratingId(null),
            },
        );
    };
    const actions = (report: SavedReport) => (
        <ReportActions
            report={report}
            onRegenerate={regenerateReport}
            regenerating={regeneratingId === report.id}
            disabled={regeneratingId !== null}
        />
    );
    const appliedChips: { key: keyof HistoryFilters; label: string }[] = [
        {
            key: 'type',
            label:
                reportTypes.find((type) => type.value === applied.type)
                    ?.label ?? applied.type,
        },
        {
            key: 'client_id',
            label:
                clientOptions.find(
                    (client) => client.value === applied.client_id,
                )?.label ?? 'Client',
        },
        {
            key: 'site_id',
            label:
                siteOptions(applied.client_id).find(
                    (site) => site.value === applied.site_id,
                )?.label ?? 'Site',
        },
        {
            key: 'generated_from',
            label: 'Generated from ' + applied.generated_from,
        },
        { key: 'generated_to', label: 'Generated to ' + applied.generated_to },
        { key: 'period_from', label: 'Period from ' + applied.period_from },
        { key: 'period_to', label: 'Period to ' + applied.period_to },
    ];

    return (
        <div className="space-y-7 pb-8">
            <Head title="Reports" />
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight text-foreground">
                        Reports
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Build a PDF from current fire safety records, then
                        return to the exact saved copy whenever you need it.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <a href="#report-history">
                        <Archive data-icon="inline-start" />
                        Browse saved reports
                    </a>
                </Button>
            </header>

            <section
                id="generate-report"
                aria-labelledby="generate-heading"
                className="overflow-hidden rounded-2xl border bg-card shadow-sm"
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-5 sm:px-7">
                    <div className="flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-foreground text-background">
                            <FileText className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h2
                                id="generate-heading"
                                className="text-lg font-semibold tracking-tight"
                            >
                                Create a report
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Choose the records and reporting period.
                            </p>
                        </div>
                    </div>
                    <Badge variant="outline" className="gap-1.5">
                        <Check className="size-3.5" aria-hidden="true" />
                        Saved after generation
                    </Badge>
                </div>
                <div className="grid lg:grid-cols-[minmax(0,1.45fr)_minmax(17rem,0.8fr)]">
                    <form
                        ref={generationForm}
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (form.data.date_from > form.data.date_to) {
                                form.setError(
                                    'date_to',
                                    'To must be on or after From.',
                                );
                                generationForm.current
                                    ?.querySelector<HTMLElement>('#report-to')
                                    ?.focus();
                                return;
                            }
                            form.post(store().url, {
                                onError: (errors) => {
                                    const firstField = [
                                        ['client_id', 'report-client'],
                                        ['site_id', 'report-site'],
                                        ['type', 'report-type-first'],
                                        ['date_from', 'report-from'],
                                        ['date_to', 'report-to'],
                                    ].find(([key]) => errors[key]);
                                    if (firstField) {
                                        requestAnimationFrame(() =>
                                            document
                                                .getElementById(firstField[1])
                                                ?.focus(),
                                        );
                                    }
                                },
                            });
                        }}
                        className="flex min-w-0 flex-col"
                    >
                        <div className="space-y-7 px-5 py-6 sm:px-7">
                            <div className="space-y-4">
                                <div>
                                    <h3 className="font-semibold">
                                        Who is it for?
                                    </h3>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        Select a client, then narrow to one site
                                        if needed.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="report-client">
                                            Client *
                                        </Label>
                                        <Choice
                                            id="report-client"
                                            value={form.data.client_id}
                                            options={clientOptions}
                                            placeholder="Select client"
                                            invalid={!!form.errors.client_id}
                                            required
                                            onChange={(value) =>
                                                form.setData((data) => ({
                                                    ...data,
                                                    client_id: value,
                                                    site_id: '',
                                                }))
                                            }
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
                                        <Label htmlFor="report-site">
                                            Site
                                        </Label>
                                        <Choice
                                            id="report-site"
                                            value={form.data.site_id}
                                            options={siteOptions(
                                                form.data.client_id,
                                            )}
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
                                </div>
                            </div>

                            <fieldset
                                className="space-y-4"
                                aria-invalid={!!form.errors.type}
                            >
                                <legend className="font-semibold">
                                    Report type *
                                </legend>
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    Each format presents the same source records
                                    for a different review.
                                </p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {reportTypes.map((type) => {
                                        const details = typeDetails(type.value);
                                        const Icon = details.icon;
                                        const selected =
                                            form.data.type === type.value;
                                        return (
                                            <button
                                                key={type.value}
                                                id={
                                                    type.value ===
                                                    reportTypes[0]?.value
                                                        ? 'report-type-first'
                                                        : undefined
                                                }
                                                type="button"
                                                aria-pressed={selected}
                                                onClick={() =>
                                                    form.setData(
                                                        'type',
                                                        type.value,
                                                    )
                                                }
                                                className={cn(
                                                    'flex min-h-20 items-start gap-3 rounded-xl border p-3 text-left transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                                    selected
                                                        ? 'border-brand/70 bg-brand/10'
                                                        : 'bg-background hover:border-foreground/30 hover:bg-muted/40',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                        selected
                                                            ? 'bg-brand/20 text-brand-foreground dark:text-brand'
                                                            : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    <Icon
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-medium">
                                                        {type.label}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs leading-relaxed text-muted-foreground">
                                                        {details.description}
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                {form.errors.type && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {form.errors.type}
                                    </p>
                                )}
                            </fieldset>

                            <div className="space-y-4">
                                <div>
                                    <h3 className="font-semibold">
                                        Reporting period
                                    </h3>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        Records on both selected dates are
                                        included.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="report-from">
                                            From *
                                        </Label>
                                        <Input
                                            id="report-from"
                                            type="date"
                                            required
                                            value={form.data.date_from}
                                            aria-invalid={
                                                !!form.errors.date_from
                                            }
                                            aria-describedby={
                                                form.errors.date_from
                                                    ? 'report-from-error'
                                                    : undefined
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'date_from',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.date_from && (
                                            <p
                                                id="report-from-error"
                                                className="text-sm text-destructive"
                                            >
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
                                            aria-describedby={
                                                form.errors.date_to
                                                    ? 'report-to-error'
                                                    : undefined
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'date_to',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.date_to && (
                                            <p
                                                id="report-to-error"
                                                className="text-sm text-destructive"
                                            >
                                                {form.errors.date_to}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-3">
                                <Checkbox
                                    id="report-entered-in-error"
                                    className="mt-0.5"
                                    checked={form.data.include_entered_in_error}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'include_entered_in_error',
                                            checked === true,
                                        )
                                    }
                                />
                                <div>
                                    <Label htmlFor="report-entered-in-error">
                                        Include records entered in error
                                    </Label>
                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Use this only when reviewing corrected
                                        historical records.
                                    </p>
                                </div>
                            </div>
                            {reportError && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {reportError}
                                </p>
                            )}
                            {clients.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Add a client before creating a report.{' '}
                                    <Link
                                        href={clientsIndex()}
                                        className="font-medium text-foreground underline underline-offset-4"
                                    >
                                        Go to Clients &amp; Sites
                                    </Link>
                                </p>
                            )}
                        </div>
                        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t bg-muted/20 px-5 py-4 sm:px-7">
                            <p className="text-xs text-muted-foreground">
                                The generated PDF will appear in Report History.
                            </p>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={
                                    form.processing || clients.length === 0
                                }
                            >
                                <FileText data-icon="inline-start" />
                                {form.processing
                                    ? 'Generating PDF…'
                                    : 'Generate PDF'}
                            </Button>
                        </div>
                    </form>

                    <aside className="border-t bg-muted/35 p-5 sm:p-7 lg:border-t-0 lg:border-l">
                        <div className="mb-4 flex items-center justify-between gap-2">
                            <div>
                                <h3 className="text-sm font-semibold">
                                    Report brief
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Your current selections
                                </p>
                            </div>
                            <FileCheck2
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="relative mx-auto max-w-sm overflow-hidden rounded-sm border bg-card p-6 shadow-lg shadow-foreground/5 sm:p-8">
                            <div className="absolute inset-x-0 top-0 h-1 bg-brand" />
                            <div className="flex items-center gap-2 border-b pb-5">
                                <span className="flex size-8 items-center justify-center rounded-md bg-foreground text-background">
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span className="text-xs font-semibold tracking-wide">
                                    Fire Tracker
                                </span>
                            </div>
                            <div className="min-h-56 py-8">
                                <p className="text-xs text-muted-foreground">
                                    Report
                                </p>
                                <p className="mt-2 text-2xl leading-tight font-semibold tracking-tight">
                                    {selectedType}
                                </p>
                                <div className="mt-7 space-y-4 border-t pt-5 text-sm">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Client
                                        </p>
                                        <p className="mt-1 font-medium break-words">
                                            {selectedClient}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Site
                                        </p>
                                        <p className="mt-1 font-medium break-words">
                                            {selectedSite}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Period
                                        </p>
                                        <p className="mt-1 font-medium">
                                            {form.data.date_from &&
                                            form.data.date_to
                                                ? dateLabel(
                                                      form.data.date_from,
                                                  ) +
                                                  ' – ' +
                                                  dateLabel(form.data.date_to)
                                                : 'Select a date range'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="border-t pt-4 text-xs text-muted-foreground">
                                Generated from current records
                            </div>
                        </div>
                        <p className="mx-auto mt-4 max-w-sm text-xs leading-relaxed text-muted-foreground">
                            This brief reflects your choices. The PDF is
                            prepared when you select Generate PDF.
                        </p>
                    </aside>
                </div>
            </section>

            {recentReport && (
                <section
                    aria-live="polite"
                    className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-success/35 bg-success/10 p-4 sm:p-5"
                >
                    <div className="flex items-start gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-success/15 text-success">
                            <Check className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 className="font-semibold">Report saved</h2>
                            <p className="text-sm text-muted-foreground">
                                {recentReport.title} for{' '}
                                {recentReport.client_name} is ready.
                            </p>
                        </div>
                    </div>
                    {actions(recentReport)}
                </section>
            )}

            <section
                id="report-history"
                aria-labelledby="history-heading"
                className="scroll-mt-5 overflow-hidden rounded-2xl border bg-card shadow-sm"
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-5 sm:px-7">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-foreground">
                            <Archive className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h2
                                id="history-heading"
                                className="text-lg font-semibold tracking-tight"
                            >
                                Report History
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Open an original PDF or make a new report from
                                its criteria.
                            </p>
                        </div>
                    </div>
                    <Badge variant="secondary" className="text-xs">
                        {records.total}{' '}
                        {records.total === 1
                            ? 'matching report'
                            : 'matching reports'}
                    </Badge>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyHistory(history);
                    }}
                    className="space-y-4 border-b bg-muted/20 px-5 py-5 sm:px-7"
                >
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,1fr))_auto] lg:items-end">
                        <div className="space-y-1.5">
                            <Label htmlFor="history-type">Report type</Label>
                            <Choice
                                id="history-type"
                                value={history.type}
                                options={reportTypes}
                                placeholder="All report types"
                                onChange={(type) =>
                                    setHistory({ ...history, type })
                                }
                            />
                        </div>
                        <div className="space-y-1.5">
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
                        <div className="space-y-1.5">
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
                        <Button
                            type="button"
                            variant="outline"
                            className="justify-between"
                            aria-expanded={showDateFilters}
                            aria-controls="history-date-filters"
                            onClick={() => setShowDateFilters(!showDateFilters)}
                        >
                            <CalendarDays data-icon="inline-start" />
                            Dates
                            {dateFilterCount > 0 && (
                                <span className="rounded-full bg-brand/20 px-1.5 text-xs">
                                    {dateFilterCount}
                                </span>
                            )}
                            <ChevronDown
                                className={cn(
                                    'size-4 transition-transform',
                                    showDateFilters && 'rotate-180',
                                )}
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                    {showDateFilters && (
                        <div
                            id="history-date-filters"
                            className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            {(
                                [
                                    ['generated_from', 'Generated from'],
                                    ['generated_to', 'Generated to'],
                                    ['period_from', 'Period from'],
                                    ['period_to', 'Period to'],
                                ] as [keyof HistoryFilters, string][]
                            ).map(([key, label]) => (
                                <div key={key} className="space-y-1.5">
                                    <Label htmlFor={'history-' + key}>
                                        {label}
                                    </Label>
                                    <Input
                                        id={'history-' + key}
                                        type="date"
                                        value={history[key]}
                                        onChange={(event) =>
                                            setHistory({
                                                ...history,
                                                [key]: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="submit" disabled={filtering}>
                                <SlidersHorizontal data-icon="inline-start" />
                                {filtering ? 'Applying…' : 'Apply filters'}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={filtering}
                                onClick={resetHistory}
                            >
                                Reset
                            </Button>
                            {filtering && (
                                <span
                                    role="status"
                                    className="text-xs text-muted-foreground"
                                >
                                    Loading reports…
                                </span>
                            )}
                        </div>
                        {activeFilterCount > 0 && (
                            <span className="text-xs text-muted-foreground">
                                {activeFilterCount} active{' '}
                                {activeFilterCount === 1 ? 'filter' : 'filters'}
                            </span>
                        )}
                    </div>
                    {filterError && (
                        <p role="alert" className="text-sm text-destructive">
                            {filterError}
                        </p>
                    )}
                    {activeFilterCount > 0 && (
                        <div
                            className="flex flex-wrap gap-2 border-t pt-3"
                            aria-label="Applied report filters"
                        >
                            {appliedChips
                                .filter((chip) => Boolean(applied[chip.key]))
                                .map((chip) => (
                                    <button
                                        key={chip.key}
                                        type="button"
                                        disabled={filtering}
                                        onClick={() => removeFilter(chip.key)}
                                        className="inline-flex items-center gap-1.5 rounded-full border bg-card px-3 py-1 text-xs font-medium text-foreground transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                                        aria-label={
                                            'Remove ' + chip.label + ' filter'
                                        }
                                    >
                                        {chip.label}
                                        <X
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                    </button>
                                ))}
                        </div>
                    )}
                </form>

                {regenerationError && (
                    <p
                        role="alert"
                        className="mx-5 mt-4 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive sm:mx-7"
                    >
                        {regenerationError}
                    </p>
                )}

                {records.data.length === 0 ? (
                    <div className="flex flex-col items-center px-5 py-16 text-center">
                        <span className="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                            <FileSearch className="size-7" aria-hidden="true" />
                        </span>
                        <h3 className="mt-4 font-semibold">
                            {activeFilterCount > 0
                                ? 'No matching reports'
                                : 'No reports yet'}
                        </h3>
                        <p className="mt-1 max-w-sm text-sm leading-relaxed text-muted-foreground">
                            {activeFilterCount > 0
                                ? 'Try a wider date range or remove a filter.'
                                : 'Choose a client and reporting period above to create your first PDF.'}
                        </p>
                        {activeFilterCount > 0 && (
                            <Button
                                type="button"
                                variant="outline"
                                className="mt-4"
                                onClick={resetHistory}
                            >
                                Clear filters
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="hidden md:block">
                            <Table
                                className="min-w-[880px]"
                                containerProps={{
                                    className: 'custom-scrollbar',
                                    role: 'region',
                                    'aria-label': 'Saved reports table',
                                    tabIndex: 0,
                                }}
                            >
                                <TableHeader className="bg-muted/30">
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="w-[25%] pl-7">
                                            Report
                                        </TableHead>
                                        <TableHead>Client and site</TableHead>
                                        <TableHead>Reporting period</TableHead>
                                        <TableHead>Generated</TableHead>
                                        <TableHead className="pr-7">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {records.data.map((report) => (
                                        <TableRow
                                            key={report.id}
                                            className="hover:bg-muted/25"
                                        >
                                            <TableCell className="py-4 pl-7 align-top">
                                                <ReportIdentity
                                                    report={report}
                                                />
                                            </TableCell>
                                            <TableCell className="max-w-52 py-4 align-top whitespace-normal">
                                                <p className="font-medium break-words">
                                                    {report.client_name}
                                                </p>
                                                <p className="mt-1 text-xs break-words text-muted-foreground">
                                                    {report.site_name ||
                                                        'All sites'}
                                                </p>
                                            </TableCell>
                                            <TableCell className="py-4 align-top">
                                                <p className="text-sm">
                                                    {dateLabel(
                                                        report.date_from,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    to{' '}
                                                    {dateLabel(report.date_to)}
                                                </p>
                                            </TableCell>
                                            <TableCell className="py-4 align-top">
                                                <p className="text-sm">
                                                    {generatedLabel(
                                                        report.generated_at,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    by{' '}
                                                    {report.generated_by_name}
                                                </p>
                                            </TableCell>
                                            <TableCell className="py-4 pr-7 align-top">
                                                {actions(report)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="divide-y md:hidden">
                            {records.data.map((report) => (
                                <article
                                    key={report.id}
                                    className="space-y-4 p-5"
                                >
                                    <ReportIdentity report={report} />
                                    <div className="grid grid-cols-2 gap-3 rounded-lg bg-muted/35 p-3 text-xs">
                                        <div>
                                            <p className="text-muted-foreground">
                                                Client / site
                                            </p>
                                            <p className="mt-1 font-medium break-words">
                                                {report.client_name}
                                            </p>
                                            <p className="mt-0.5 break-words text-muted-foreground">
                                                {report.site_name ||
                                                    'All sites'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">
                                                Period
                                            </p>
                                            <p className="mt-1 font-medium">
                                                {dateLabel(report.date_from)} –{' '}
                                                {dateLabel(report.date_to)}
                                            </p>
                                        </div>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Generated{' '}
                                        {generatedLabel(report.generated_at)} by{' '}
                                        {report.generated_by_name}
                                    </p>
                                    {actions(report)}
                                </article>
                            ))}
                        </div>
                        <div className="border-t px-4 py-2 sm:px-6">
                            <Pagination
                                currentPage={records.current_page}
                                totalItems={records.total}
                                pageSize={records.per_page}
                                loading={filtering}
                                onPageChange={(page) =>
                                    applyHistory(
                                        filtersToHistory(filters),
                                        page,
                                    )
                                }
                            />
                        </div>
                    </>
                )}
            </section>
        </div>
    );
}
