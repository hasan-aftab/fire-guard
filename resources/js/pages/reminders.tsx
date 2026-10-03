import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCheck,
    Eye,
    FileBadge2,
    Flame,
    Inbox,
    Mail,
    Plus,
    Send,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    index,
    preview,
    samplePreview,
    sampleTestEmail,
    testEmail,
} from '@/actions/App/Http/Controllers/ReminderHistoryController';
import {
    destroy as destroyRecipient,
    store as storeRecipient,
} from '@/actions/App/Http/Controllers/ReminderRecipientController';
import { DateRangePicker } from '@/components/date-range-picker';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

type ReminderType = 'asset' | 'certificate';
type ReminderAudience = 'configured' | 'configured_client' | 'staff' | 'client';
type ReminderStatus =
    | 'pending'
    | 'processing'
    | 'sent'
    | 'failed'
    | 'skipped'
    | 'uncertain';
type Reminder = {
    id: number;
    type: ReminderType;
    client_name: string;
    site_name: string;
    site_address: string | null;
    subject_name: string;
    recipient: string;
    recipient_name: string | null;
    audience: ReminderAudience;
    due_date: string;
    offset: number;
    status: ReminderStatus;
    sent_at: string | null;
    created_at: string | null;
    attempt_count: number;
    last_error: string | null;
};
type Filters = {
    status: string;
    type: string;
    audience: string;
    client_id: string;
    due_from: string;
    due_to: string;
};
type Client = { id: number; company_name: string };
type Recipient = { id: number; email: string };
type FallbackAdmin = { id: number; name: string; email: string };
type Selection =
    | { kind: 'template'; type: ReminderType }
    | { kind: 'record'; record: Reminder };

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value}T12:00:00`),
    );
}

function formatTimestamp(value: string | null): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function windowLabel(offset: number): string {
    if (offset < 0) return 'Overdue notice';
    if (offset === 0) return 'Due today';
    return `${offset} ${offset === 1 ? 'day' : 'days'} ahead`;
}

function audienceLabel(audience: ReminderAudience): string {
    return {
        configured: 'Selected email',
        configured_client: 'Selected client',
        staff: 'Admin',
        client: 'Past client copy',
    }[audience];
}

function DeliveryBadge({ status }: { status: ReminderStatus }) {
    const tone = {
        sent: 'border-success/40 bg-success/10 text-success',
        pending: 'border-info/40 bg-info/10 text-info',
        processing: 'border-info/40 bg-info/10 text-info',
        failed: 'border-destructive/40 bg-destructive/10 text-destructive',
        skipped: 'border-border bg-muted text-muted-foreground',
        uncertain: 'border-warning/40 bg-warning/10 text-warning',
    }[status];
    const label = {
        sent: 'Delivered',
        pending: 'Queued',
        processing: 'Sending',
        failed: 'Failed',
        skipped: 'Skipped',
        uncertain: 'Needs review',
    }[status];

    return (
        <Badge variant="outline" className={cn('gap-1.5', tone)}>
            <span className="size-1.5 rounded-full bg-current" />
            {label}
        </Badge>
    );
}

function FilterChoice({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-1.5">
            <span className="block text-xs font-medium text-muted-foreground">
                {label}
            </span>
            <Select value={value || 'all'} onValueChange={onChange}>
                <SelectTrigger aria-label={label} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">
                        All {label.toLowerCase()}
                    </SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function TypeMark({ type }: { type: ReminderType }) {
    const Icon = type === 'asset' ? Flame : FileBadge2;
    return (
        <div
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-lg',
                type === 'asset'
                    ? 'bg-warning/10 text-warning'
                    : 'bg-info/10 text-info',
            )}
        >
            <Icon className="size-5" aria-hidden="true" />
        </div>
    );
}

export default function Reminders({
    records,
    filters,
    clients,
    recipients,
    fallbackAdmins,
    summary,
}: {
    records: Paginated<Reminder>;
    filters: Partial<Filters>;
    clients: Client[];
    recipients: Recipient[];
    fallbackAdmins: FallbackAdmin[];
    summary: { sent: number; in_progress: number; needs_review: number };
}) {
    const [working, setWorking] = useState<Filters>({
        status: filters.status ?? '',
        type: filters.type ?? '',
        audience: filters.audience ?? '',
        client_id: String(filters.client_id ?? ''),
        due_from: filters.due_from ?? '',
        due_to: filters.due_to ?? '',
    });
    const [selection, setSelection] = useState<Selection | null>(null);
    const [filtering, setFiltering] = useState(false);
    const [sending, setSending] = useState(false);
    const [recipientEmail, setRecipientEmail] = useState('');
    const [recipientError, setRecipientError] = useState('');
    const [savingRecipient, setSavingRecipient] = useState(false);
    const [removingRecipientId, setRemovingRecipientId] = useState<
        number | null
    >(null);
    const appliedFilterCount = Object.values(filters).filter(Boolean).length;

    const apply = (next: Filters, page = 1) => {
        router.get(
            index().url,
            {
                ...Object.fromEntries(
                    Object.entries(next).filter(([, value]) => value !== ''),
                ),
                page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setFiltering(true),
                onError: () => toast.error('Could not apply reminder filters.'),
                onFinish: () => setFiltering(false),
            },
        );
    };
    const update = (changes: Partial<Filters>) => {
        const next = { ...working, ...changes };
        setWorking(next);
        apply(next);
    };
    const sendTest = () => {
        if (!selection) return;
        const url =
            selection.kind === 'template'
                ? sampleTestEmail(selection.type).url
                : testEmail(selection.record.id).url;
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onError: (errors) =>
                    toast.error(
                        errors.test_email ?? 'Could not send the test copy.',
                    ),
                onFinish: () => setSending(false),
            },
        );
    };
    const addRecipient = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setRecipientError('');
        router.post(
            storeRecipient().url,
            { email: recipientEmail.trim() },
            {
                preserveScroll: true,
                onStart: () => setSavingRecipient(true),
                onSuccess: () => setRecipientEmail(''),
                onError: (errors) =>
                    setRecipientError(
                        errors.email ?? 'Could not add this email address.',
                    ),
                onFinish: () => setSavingRecipient(false),
            },
        );
    };
    const removeRecipient = (recipient: Recipient) => {
        router.delete(destroyRecipient(recipient.id).url, {
            preserveScroll: true,
            onStart: () => setRemovingRecipientId(recipient.id),
            onError: () => toast.error('Could not remove this email address.'),
            onFinish: () => setRemovingRecipientId(null),
        });
    };
    const selectedType =
        selection?.kind === 'template'
            ? selection.type
            : selection?.record.type;
    const selectedTitle =
        selection?.kind === 'template'
            ? selection.type === 'asset'
                ? 'Equipment inspection'
                : 'Certificate expiry'
            : selection?.record.subject_name;
    const previewUrl = selection
        ? selection.kind === 'template'
            ? samplePreview(selection.type).url
            : preview(selection.record.id).url
        : '';

    return (
        <div className="space-y-6">
            <Head title="Reminder activity" />
            <PageHeader
                title="Reminder activity"
                description="Inspection and certificate emails for your selected recipients."
            />

            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard
                    label="Delivered"
                    value={summary.sent}
                    icon={CheckCheck}
                    tone="green"
                    description="Accepted by the mail transport"
                />
                <StatCard
                    label="In progress"
                    value={summary.in_progress}
                    icon={Mail}
                    tone="blue"
                    description="Queued or sending"
                />
                <StatCard
                    label="Needs review"
                    value={summary.needs_review}
                    icon={AlertTriangle}
                    tone="amber"
                    description="Failed or uncertain handoff"
                />
            </div>

            <Card className="gap-0 overflow-hidden py-0">
                <CardHeader className="border-b px-5 py-5 sm:px-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle className="text-lg">
                                Who receives reminders
                            </CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add email addresses here to control scheduled
                                inspection and certificate reminders.
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {recipients.length > 0
                                ? `${recipients.length} selected ${recipients.length === 1 ? 'email' : 'emails'}`
                                : 'Admin fallback'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5 px-5 py-5 sm:px-6">
                    <form
                        onSubmit={addRecipient}
                        className="flex flex-col gap-2 sm:flex-row sm:items-end"
                    >
                        <div className="w-full max-w-md space-y-1.5">
                            <label
                                htmlFor="reminder-recipient-email"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Email address
                            </label>
                            <Input
                                id="reminder-recipient-email"
                                type="email"
                                autoComplete="email"
                                placeholder="name@example.com"
                                value={recipientEmail}
                                onChange={(event) => {
                                    setRecipientEmail(event.target.value);
                                    setRecipientError('');
                                }}
                                required
                                maxLength={254}
                                aria-invalid={Boolean(recipientError)}
                                aria-describedby={
                                    recipientError
                                        ? 'reminder-recipient-error'
                                        : undefined
                                }
                            />
                        </div>
                        <Button type="submit" disabled={savingRecipient}>
                            <Plus data-icon="inline-start" />
                            {savingRecipient ? 'Adding…' : 'Add email'}
                        </Button>
                    </form>
                    {recipientError && (
                        <p
                            id="reminder-recipient-error"
                            role="alert"
                            className="text-sm text-destructive"
                        >
                            {recipientError}
                        </p>
                    )}
                    {recipients.length > 0 ? (
                        <div className="space-y-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                Active email list
                            </p>
                            <div className="grid gap-2 md:grid-cols-2">
                                {recipients.map((recipient) => (
                                    <div
                                        key={recipient.id}
                                        className="flex min-w-0 items-center justify-between gap-2 rounded-lg border bg-muted/20 px-3 py-2"
                                    >
                                        <span className="min-w-0 truncate text-sm font-medium">
                                            {recipient.email}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-muted-foreground"
                                            aria-label={`Remove ${recipient.email}`}
                                            disabled={
                                                removingRecipientId ===
                                                recipient.id
                                            }
                                            onClick={() =>
                                                removeRecipient(recipient)
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Only these addresses receive scheduled
                                reminders. Removing the last address switches
                                back to verified admins.
                            </p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed bg-muted/20 p-4">
                            <p className="text-sm font-medium">
                                Verified admins receive reminders
                            </p>
                            {fallbackAdmins.length > 0 ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {fallbackAdmins
                                        .map(
                                            (admin) =>
                                                `${admin.name} (${admin.email})`,
                                        )
                                        .join(' · ')}
                                </p>
                            ) : (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    No verified admin is available. Add an email
                                    above to enable reminders.
                                </p>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            <section aria-labelledby="template-heading" className="space-y-3">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2
                            id="template-heading"
                            className="text-lg font-semibold text-foreground"
                        >
                            Email designs
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Preview a sample or send a labeled test copy to your
                            own inbox.
                        </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Samples use fictional client details.
                    </p>
                </div>
                <div className="grid gap-3 md:grid-cols-2">
                    {[
                        {
                            type: 'asset' as const,
                            title: 'Equipment inspection',
                            description:
                                'A clear service-date notice with the asset, site, and client contact in one place.',
                            icon: Flame,
                        },
                        {
                            type: 'certificate' as const,
                            title: 'Certificate expiry',
                            description:
                                'A renewal notice focused on the expiry date and the certificate that needs attention.',
                            icon: ShieldCheck,
                        },
                    ].map((template) => {
                        const Icon = template.icon;
                        return (
                            <Card
                                key={template.type}
                                className="overflow-hidden border-brand/20 bg-gradient-to-br from-brand/10 via-card to-card"
                            >
                                <CardContent className="flex h-full flex-col gap-5 p-5 sm:p-6">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-foreground text-background">
                                            <Icon
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <div className="min-w-0">
                                            <h3 className="font-semibold text-foreground">
                                                {template.title}
                                            </h3>
                                            <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                                {template.description}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        variant="outline"
                                        className="mt-auto w-fit bg-card"
                                        onClick={() =>
                                            setSelection({
                                                kind: 'template',
                                                type: template.type,
                                            })
                                        }
                                    >
                                        <Eye data-icon="inline-start" />
                                        Preview and send test
                                    </Button>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </section>

            <Card className="gap-0 overflow-hidden py-0">
                <CardHeader className="border-b px-5 py-5 sm:px-6">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <CardTitle className="text-lg">
                                Delivery history
                            </CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {records.total} matching{' '}
                                {records.total === 1
                                    ? 'delivery'
                                    : 'deliveries'}
                            </p>
                        </div>
                        {appliedFilterCount > 0 && (
                            <Badge variant="secondary">
                                {appliedFilterCount} active{' '}
                                {appliedFilterCount === 1
                                    ? 'filter'
                                    : 'filters'}
                            </Badge>
                        )}
                    </div>
                </CardHeader>
                <CardContent className="space-y-5 px-5 py-5 sm:px-6">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <FilterChoice
                            label="Status"
                            value={working.status}
                            options={[
                                { value: 'sent', label: 'Delivered' },
                                { value: 'pending', label: 'Queued' },
                                { value: 'processing', label: 'Sending' },
                                { value: 'failed', label: 'Failed' },
                                { value: 'uncertain', label: 'Needs review' },
                                { value: 'skipped', label: 'Skipped' },
                            ]}
                            onChange={(value) =>
                                update({ status: value === 'all' ? '' : value })
                            }
                        />
                        <FilterChoice
                            label="Type"
                            value={working.type}
                            options={[
                                { value: 'asset', label: 'Equipment' },
                                { value: 'certificate', label: 'Certificate' },
                            ]}
                            onChange={(value) =>
                                update({ type: value === 'all' ? '' : value })
                            }
                        />
                        <FilterChoice
                            label="Recipient"
                            value={working.audience}
                            options={[
                                {
                                    value: 'configured',
                                    label: 'Selected email',
                                },
                                {
                                    value: 'configured_client',
                                    label: 'Selected client',
                                },
                                { value: 'staff', label: 'Team' },
                                { value: 'client', label: 'Past client copy' },
                            ]}
                            onChange={(value) =>
                                update({
                                    audience: value === 'all' ? '' : value,
                                })
                            }
                        />
                        <FilterChoice
                            label="Client"
                            value={working.client_id}
                            options={clients.map((client) => ({
                                value: String(client.id),
                                label: client.company_name,
                            }))}
                            onChange={(value) =>
                                update({
                                    client_id: value === 'all' ? '' : value,
                                })
                            }
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <DateRangePicker
                            from={working.due_from}
                            to={working.due_to}
                            onSelect={(range) =>
                                update({
                                    due_from: range.from ?? '',
                                    due_to: range.to ?? '',
                                })
                            }
                            placeholder="Due or expiry date"
                        />
                        {appliedFilterCount > 0 && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    update({
                                        status: '',
                                        type: '',
                                        audience: '',
                                        client_id: '',
                                        due_from: '',
                                        due_to: '',
                                    })
                                }
                            >
                                Clear filters
                            </Button>
                        )}
                    </div>

                    <div aria-busy={filtering}>
                        <div className="hidden md:block">
                            <Table
                                containerProps={{
                                    className:
                                        'custom-scrollbar rounded-lg border',
                                    role: 'region',
                                    'aria-label': 'Reminder delivery history',
                                    tabIndex: 0,
                                }}
                            >
                                <TableHeader className="bg-muted/40">
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Reminder
                                        </TableHead>
                                        <TableHead>Due / expiry</TableHead>
                                        <TableHead>Recipient</TableHead>
                                        <TableHead>Delivery</TableHead>
                                        <TableHead className="pr-4 text-right">
                                            Email
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {records.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-28 text-center text-muted-foreground"
                                            >
                                                No deliveries match these
                                                filters.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        records.data.map((record) => (
                                            <TableRow key={record.id}>
                                                <TableCell className="min-w-64 py-4 pl-4">
                                                    <div className="flex items-start gap-3">
                                                        <TypeMark
                                                            type={record.type}
                                                        />
                                                        <div className="min-w-0">
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    record.subject_name
                                                                }
                                                            </p>
                                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                                {
                                                                    record.client_name
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    record.site_name
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    <p className="font-medium text-foreground">
                                                        {formatDate(
                                                            record.due_date,
                                                        )}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {windowLabel(
                                                            record.offset,
                                                        )}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="min-w-44">
                                                    <div className="flex items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            {audienceLabel(
                                                                record.audience,
                                                            )}
                                                        </Badge>
                                                        <span className="truncate text-xs text-muted-foreground">
                                                            {
                                                                record.recipient_name
                                                            }
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 max-w-52 truncate text-sm text-foreground">
                                                        {record.recipient ||
                                                            'No address'}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="min-w-36">
                                                    <DeliveryBadge
                                                        status={record.status}
                                                    />
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {record.sent_at
                                                            ? formatTimestamp(
                                                                  record.sent_at,
                                                              )
                                                            : `${record.attempt_count} attempts`}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="pr-4 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setSelection({
                                                                kind: 'record',
                                                                record,
                                                            })
                                                        }
                                                    >
                                                        <Eye data-icon="inline-start" />
                                                        Preview
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="space-y-3 md:hidden">
                            {records.data.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    No deliveries match these filters.
                                </p>
                            ) : (
                                records.data.map((record) => (
                                    <div
                                        key={record.id}
                                        className="space-y-4 rounded-lg border p-4"
                                    >
                                        <div className="flex items-start gap-3">
                                            <TypeMark type={record.type} />
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium text-foreground">
                                                    {record.subject_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {record.client_name} ·{' '}
                                                    {record.site_name}
                                                </p>
                                            </div>
                                            <DeliveryBadge
                                                status={record.status}
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Due / expiry
                                                </p>
                                                <p className="font-medium">
                                                    {formatDate(
                                                        record.due_date,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {windowLabel(record.offset)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Recipient
                                                </p>
                                                <p className="truncate font-medium">
                                                    {audienceLabel(
                                                        record.audience,
                                                    )}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {record.recipient ||
                                                        'No address'}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                            onClick={() =>
                                                setSelection({
                                                    kind: 'record',
                                                    record,
                                                })
                                            }
                                        >
                                            <Eye data-icon="inline-start" />
                                            Preview email
                                        </Button>
                                    </div>
                                ))
                            )}
                        </div>
                        <Pagination
                            currentPage={records.current_page}
                            totalItems={records.total}
                            pageSize={records.per_page}
                            loading={filtering}
                            onPageChange={(page) => apply(working, page)}
                        />
                    </div>
                </CardContent>
            </Card>

            <Dialog
                open={selection !== null}
                onOpenChange={(open) => {
                    if (!open) setSelection(null);
                }}
            >
                <DialogContent className="max-h-[92dvh] gap-0 overflow-hidden p-0 sm:max-w-5xl">
                    <DialogHeader className="border-b px-5 py-4 text-left sm:px-6">
                        <DialogTitle className="pr-8 text-lg">
                            {selectedTitle ?? 'Email preview'}
                        </DialogTitle>
                        <DialogDescription>
                            {selection?.kind === 'template'
                                ? 'Sample design with fictional details.'
                                : 'Current email design using this delivery’s saved details.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid min-h-0 md:grid-cols-[220px_minmax(0,1fr)]">
                        <aside className="space-y-4 border-b bg-muted/30 p-5 text-sm md:border-r md:border-b-0">
                            <div className="flex items-center gap-2 font-medium">
                                {selectedType === 'asset' ? (
                                    <Flame
                                        className="size-4 text-warning"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <FileBadge2
                                        className="size-4 text-info"
                                        aria-hidden="true"
                                    />
                                )}
                                {selectedType === 'asset'
                                    ? 'Equipment inspection'
                                    : 'Certificate expiry'}
                            </div>
                            {selection?.kind === 'record' && (
                                <>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Recipient
                                        </p>
                                        <p className="font-medium break-all">
                                            {selection.record.recipient ||
                                                'No address'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {audienceLabel(
                                                selection.record.audience,
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Delivery
                                        </p>
                                        <DeliveryBadge
                                            status={selection.record.status}
                                        />
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {selection.record.attempt_count}{' '}
                                            attempts ·{' '}
                                            {formatTimestamp(
                                                selection.record.sent_at,
                                            )}
                                        </p>
                                    </div>
                                    {selection.record.last_error && (
                                        <p className="rounded-md border border-warning/30 bg-warning/10 p-3 text-xs text-foreground">
                                            {selection.record.last_error}
                                        </p>
                                    )}
                                </>
                            )}
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                Test copies go only to your signed-in email.
                                Local delivery is captured by Mailpit.
                            </p>
                        </aside>
                        <div className="min-h-0 overflow-auto bg-muted/40 p-3 sm:p-5">
                            {selection && (
                                <iframe
                                    key={previewUrl}
                                    src={previewUrl}
                                    title={`${selectedTitle ?? 'Reminder'} email design`}
                                    sandbox=""
                                    className="h-[48dvh] w-full rounded-md border bg-white shadow-sm md:h-[62dvh]"
                                />
                            )}
                        </div>
                    </div>
                    <DialogFooter className="border-t px-5 py-4 sm:px-6">
                        <Button
                            onClick={sendTest}
                            disabled={sending || !selection}
                        >
                            {sending ? (
                                <Inbox data-icon="inline-start" />
                            ) : (
                                <Send data-icon="inline-start" />
                            )}
                            {sending
                                ? 'Sending test copy…'
                                : 'Send test copy to my inbox'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
