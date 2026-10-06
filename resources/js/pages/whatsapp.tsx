import { Head, router } from '@inertiajs/react';
import {
    CheckCheck,
    CircleAlert,
    MessageCircle,
    Plus,
    Send,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    index,
    storeAccount,
    updateAccount,
    setDefault,
    toggleActive,
    verify,
    saveTemplate,
    syncTemplates,
    sendTest,
    retry,
} from '@/actions/App/Http/Controllers/WhatsAppController';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
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
import type { Paginated } from '@/types/fire-tracker';

type Account = {
    id: number;
    name: string;
    display_phone_number: string;
    phone_number_id: string;
    waba_id: string;
    is_active: boolean;
    is_test: boolean;
    is_default: boolean;
    last_verified_at: string | null;
    last_error: string | null;
    messages_count: number;
    delivered_count: number;
    delivery_rate: number | null;
    last_used_at: string | null;
};
type Template = {
    id: number;
    waba_id: string;
    purpose: 'asset' | 'certificate';
    name: string;
    language: string;
    meta_status: string | null;
    category: string | null;
    parameter_keys: string[];
    is_enabled: boolean;
    last_synced_at: string | null;
    last_error: string | null;
};
type Message = {
    id: number;
    is_test: boolean;
    recipient: string;
    status: string;
    account_name: string | null;
    sender_display_phone_number: string | null;
    phone_number_id: string | null;
    waba_id: string | null;
    template_name: string | null;
    template_language: string | null;
    meta_message_id: string | null;
    attempt_count: number;
    submitted_at: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    failed_at: string | null;
    failure_code: string | null;
    last_error: string | null;
    created_at: string | null;
    client_name: string | null;
    site_name: string | null;
    subject_name: string | null;
    reminder_type: string | null;
    due_date: string | null;
    reminder_offset: number | null;
    can_retry: boolean;
};
type Filters = {
    period: string;
    status: string;
    type: string;
    account_id: string;
    search: string;
    from: string;
    to: string;
};
type Summary = {
    submitted: number;
    sent: number;
    delivered: number;
    read: number;
    failed: number;
    active_accounts: number;
    skipped_reminders: number;
    delivery_rate: number | null;
};
type Breakdown = {
    submitted: number;
    delivered: number;
    read: number;
    failed: number;
};

const times = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
const stateTone: Record<string, string> = {
    queued: 'bg-info/10 text-info',
    processing: 'bg-info/10 text-info',
    submitted: 'bg-info/10 text-info',
    sent: 'bg-info/10 text-info',
    delivered: 'bg-success/10 text-success',
    read: 'bg-success/10 text-success',
    failed: 'bg-destructive/10 text-destructive',
    skipped: 'bg-muted text-muted-foreground',
    uncertain: 'bg-warning/10 text-warning',
};

function StateBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={stateTone[status] ?? ''}>
            {status.replace('_', ' ')}
        </Badge>
    );
}

function Choice({
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
            <Label>{label}</Label>
            <Select
                value={value || 'all'}
                onValueChange={(next) => onChange(next === 'all' ? '' : next)}
            >
                <SelectTrigger aria-label={label} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All</SelectItem>
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

function AccountEditor({
    account,
    open,
    onOpenChange,
}: {
    account: Account | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [data, setData] = useState({
        name: account?.name ?? '',
        display_phone_number: account?.display_phone_number ?? '',
        phone_number_id: account?.phone_number_id ?? '',
        waba_id: account?.waba_id ?? '',
        access_token: '',
        is_active: account?.is_active ?? true,
        is_test: account?.is_test ?? false,
        is_default: account?.is_default ?? false,
    });
    const [busy, setBusy] = useState(false);
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: () => onOpenChange(false),
            onError: (errors: Record<string, string>) =>
                toast.error(
                    Object.values(errors)[0] ?? 'Could not save account.',
                ),
        };
        if (account) router.put(updateAccount(account.id).url, data, options);
        else router.post(storeAccount().url, data, options);
    };
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {account
                            ? 'Edit sending account'
                            : 'Add sending account'}
                    </DialogTitle>
                    <DialogDescription>
                        Credentials are encrypted on the server. Verify the
                        number after saving.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="wa-name">Account name</Label>
                        <Input
                            id="wa-name"
                            required
                            value={data.name}
                            onChange={(event) =>
                                setData({ ...data, name: event.target.value })
                            }
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="wa-display">Display phone number</Label>
                        <Input
                            id="wa-display"
                            type="tel"
                            required
                            placeholder="+675 7123 4567"
                            value={data.display_phone_number}
                            onChange={(event) =>
                                setData({
                                    ...data,
                                    display_phone_number: event.target.value,
                                })
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            The visible sending number, in international format.
                        </p>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="wa-phone-id">
                            Meta Phone Number ID
                        </Label>
                        <Input
                            id="wa-phone-id"
                            required
                            inputMode="numeric"
                            value={data.phone_number_id}
                            onChange={(event) =>
                                setData({
                                    ...data,
                                    phone_number_id: event.target.value,
                                })
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            The Meta identifier used for sending; it is
                            different from the visible number.
                        </p>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="wa-waba">
                            WhatsApp Business Account ID
                        </Label>
                        <Input
                            id="wa-waba"
                            required
                            inputMode="numeric"
                            value={data.waba_id}
                            onChange={(event) =>
                                setData({
                                    ...data,
                                    waba_id: event.target.value,
                                })
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            The WABA ID from Meta. Several numbers may share one
                            WABA.
                        </p>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="wa-token">
                            {account ? 'Replace access token' : 'Access token'}
                        </Label>
                        <Input
                            id="wa-token"
                            type="password"
                            autoComplete="new-password"
                            required={!account}
                            value={data.access_token}
                            onChange={(event) =>
                                setData({
                                    ...data,
                                    access_token: event.target.value,
                                })
                            }
                            placeholder={
                                account
                                    ? 'Leave blank to keep encrypted token'
                                    : ''
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {account
                                ? 'A saved token exists. It is never shown here.'
                                : 'Use a scoped System User token for production.'}
                        </p>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) =>
                                setData({
                                    ...data,
                                    is_active: event.target.checked,
                                    is_default:
                                        event.target.checked && data.is_default,
                                })
                            }
                        />{' '}
                        Active sender
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_test}
                            onChange={(event) =>
                                setData({
                                    ...data,
                                    is_test: event.target.checked,
                                    is_default: event.target.checked
                                        ? false
                                        : data.is_default,
                                })
                            }
                        />{' '}
                        Meta test number (can verify and send tests, never
                        automatic reminders)
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_default}
                            disabled={!data.is_active || data.is_test}
                            onChange={(event) =>
                                setData({
                                    ...data,
                                    is_default: event.target.checked,
                                })
                            }
                        />{' '}
                        Default for automated reminders
                    </label>
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Saving…' : 'Save account'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function WhatsApp({
    filters,
    summary,
    byType,
    accounts,
    templates,
    messages,
}: {
    filters: Partial<Filters>;
    summary: Summary;
    byType: Record<'asset' | 'certificate', Breakdown>;
    accounts: Account[];
    templates: Template[];
    messages: Paginated<Message>;
}) {
    const [section, setSection] = useState<
        'activity' | 'accounts' | 'templates'
    >('activity');
    const [editingAccount, setEditingAccount] = useState<
        Account | null | undefined
    >(undefined);
    const [selectedMessage, setSelectedMessage] = useState<Message | null>(
        null,
    );
    const [deactivating, setDeactivating] = useState<Account | null>(null);
    const [deactivationBusy, setDeactivationBusy] = useState(false);
    const [testBusy, setTestBusy] = useState(false);
    const [filtering, setFiltering] = useState(false);
    const [working, setWorking] = useState<Filters>({
        period: filters.period ?? '30',
        status: filters.status ?? '',
        type: filters.type ?? '',
        account_id: String(filters.account_id ?? ''),
        search: filters.search ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const [mapping, setMapping] = useState({
        account_id: '',
        purpose: 'asset',
        name: '',
        language: 'en_US',
        keys: 'client_name,site_name,subject_name,due_date,timing',
        is_enabled: true,
    });
    const [test, setTest] = useState({
        type: 'asset' as 'asset' | 'certificate',
        recipient: '',
    });
    const apply = (next: Filters, page = 1) =>
        router.get(
            index().url,
            {
                ...Object.fromEntries(
                    Object.entries(next).filter(([, value]) => value),
                ),
                page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setFiltering(true),
                onFinish: () => setFiltering(false),
            },
        );
    const sectionCounts: Record<'activity' | 'accounts' | 'templates', number> =
        {
            activity: messages.total,
            accounts: accounts.length,
            templates: templates.length,
        };
    const metricCards = [
        {
            label: 'Submitted',
            value: summary.submitted,
            icon: Send,
            hint: 'Accepted by Meta, not necessarily delivered',
        },
        {
            label: 'Sent',
            value: summary.sent,
            icon: Send,
            hint: 'Sent, delivered or read webhook received',
        },
        {
            label: 'Delivered',
            value: summary.delivered,
            icon: CheckCheck,
            hint: 'Delivered or read webhook received',
        },
        {
            label: 'Read',
            value: summary.read,
            icon: MessageCircle,
            hint: 'Read webhook received',
        },
        {
            label: 'Failed',
            value: summary.failed,
            icon: CircleAlert,
            hint: 'Meta rejection or failure',
        },
        {
            label: 'Delivery rate',
            value:
                summary.delivery_rate === null
                    ? '—'
                    : `${summary.delivery_rate}%`,
            icon: ShieldCheck,
            hint: 'Delivered or read ÷ sent, delivered, read and failed outcomes',
        },
    ];
    return (
        <div className="space-y-6">
            <Head title="WhatsApp" />
            <PageHeader
                title="WhatsApp"
                description="Operational reminder delivery through Meta Cloud API."
                actions={
                    <Button
                        onClick={() => {
                            setEditingAccount(null);
                            setSection('accounts');
                        }}
                    >
                        <Plus data-icon="inline-start" /> Add account
                    </Button>
                }
            />
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                {metricCards.map(({ label, value, icon: Icon, hint }) => (
                    <Card key={label} className="gap-0 py-3">
                        <CardContent className="space-y-1 px-3 pt-0">
                            <div className="flex items-center gap-1.5">
                                <Icon
                                    className="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="truncate text-[11px] text-muted-foreground">
                                    {label}
                                </p>
                            </div>
                            <p className="text-lg font-semibold">{value}</p>
                            <p
                                className="truncate text-[10px] text-muted-foreground"
                                title={hint}
                            >
                                {hint}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Choice
                    label="Metric period"
                    value={working.period}
                    options={[
                        { value: 'today', label: 'Today' },
                        { value: '7', label: 'Last 7 days' },
                        { value: '30', label: 'Last 30 days' },
                    ]}
                    onChange={(period) => {
                        const next = { ...working, period };
                        setWorking(next);
                        apply(next);
                    }}
                />
                <Badge variant="outline">
                    {summary.active_accounts} active senders
                </Badge>
                {summary.skipped_reminders > 0 && (
                    <Badge variant="outline" className="text-warning">
                        {summary.skipped_reminders} skipped reminders
                    </Badge>
                )}
            </div>
            <div
                className="flex flex-wrap gap-2"
                role="tablist"
                aria-label="WhatsApp sections"
            >
                {(['activity', 'accounts', 'templates'] as const).map(
                    (item) => (
                        <Button
                            key={item}
                            role="tab"
                            aria-selected={section === item}
                            variant={section === item ? 'default' : 'outline'}
                            onClick={() => setSection(item)}
                            className="capitalize"
                        >
                            {item}
                            <Badge
                                variant="secondary"
                                className="ml-1 tabular-nums"
                            >
                                {sectionCounts[item]}
                            </Badge>
                        </Button>
                    ),
                )}
            </div>
            {section === 'activity' && (
                <div className="space-y-5">
                    <div className="grid gap-3 md:grid-cols-2">
                        {(['asset', 'certificate'] as const).map((type) => (
                            <Card key={type}>
                                <CardHeader>
                                    <CardTitle>
                                        {type === 'asset'
                                            ? 'Inspection reminders'
                                            : 'Certificate reminders'}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-4 gap-2 text-sm">
                                    {Object.entries(byType[type]).map(
                                        ([label, value]) => (
                                            <div key={label}>
                                                <p className="font-semibold">
                                                    {value}
                                                </p>
                                                <p className="text-muted-foreground capitalize">
                                                    {label}
                                                </p>
                                            </div>
                                        ),
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Message history{filtering ? ' · Updating…' : ''}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <Choice
                                    label="Status"
                                    value={working.status}
                                    options={[
                                        'queued',
                                        'processing',
                                        'submitted',
                                        'sent',
                                        'delivered',
                                        'read',
                                        'failed',
                                        'skipped',
                                        'uncertain',
                                    ].map((value) => ({ value, label: value }))}
                                    onChange={(status) =>
                                        setWorking({ ...working, status })
                                    }
                                />
                                <Choice
                                    label="Reminder"
                                    value={working.type}
                                    options={[
                                        { value: 'asset', label: 'Inspection' },
                                        {
                                            value: 'certificate',
                                            label: 'Certificate',
                                        },
                                        {
                                            value: 'test',
                                            label: 'Test messages',
                                        },
                                    ]}
                                    onChange={(type) =>
                                        setWorking({ ...working, type })
                                    }
                                />
                                <Choice
                                    label="Sender"
                                    value={working.account_id}
                                    options={accounts.map((account) => ({
                                        value: String(account.id),
                                        label: account.name,
                                    }))}
                                    onChange={(account_id) =>
                                        setWorking({ ...working, account_id })
                                    }
                                />
                                <div className="space-y-1.5">
                                    <Label htmlFor="wa-search">
                                        Client, site or recipient
                                    </Label>
                                    <Input
                                        id="wa-search"
                                        type="search"
                                        value={working.search}
                                        onChange={(event) =>
                                            setWorking({
                                                ...working,
                                                search: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="wa-from">From</Label>
                                    <Input
                                        id="wa-from"
                                        type="date"
                                        value={working.from}
                                        onChange={(event) =>
                                            setWorking({
                                                ...working,
                                                from: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="wa-to">To</Label>
                                    <Input
                                        id="wa-to"
                                        type="date"
                                        value={working.to}
                                        onChange={(event) =>
                                            setWorking({
                                                ...working,
                                                to: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="flex items-end">
                                    <Button onClick={() => apply(working)}>
                                        {filtering
                                            ? 'Updating…'
                                            : 'Apply filters'}
                                    </Button>
                                </div>
                            </div>
                            {filtering ? (
                                <div
                                    aria-label="Loading message history"
                                    className="space-y-3"
                                    role="status"
                                >
                                    {Array.from({ length: 4 }, (_, index) => (
                                        <Skeleton
                                            key={index}
                                            className="h-12 w-full"
                                        />
                                    ))}
                                </div>
                            ) : messages.data.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    No messages match these filters. Eligible
                                    reminders will appear here after processing.
                                </p>
                            ) : (
                                <>
                                    <div className="space-y-2 sm:hidden">
                                        {messages.data.map((message) => (
                                            <button
                                                key={message.id}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedMessage(message)
                                                }
                                                className="w-full rounded-lg border p-3 text-left"
                                            >
                                                <div className="flex justify-between gap-2">
                                                    <strong>
                                                        {message.is_test
                                                            ? 'Test'
                                                            : message.client_name}
                                                    </strong>
                                                    <StateBadge
                                                        status={message.status}
                                                    />
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {message.recipient} ·{' '}
                                                    {message.account_name ??
                                                        'No sender'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {times(message.created_at)}
                                                </p>
                                            </button>
                                        ))}
                                    </div>
                                    <div className="hidden overflow-x-auto sm:block">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Date</TableHead>
                                                    <TableHead>
                                                        Recipient
                                                    </TableHead>
                                                    <TableHead>
                                                        Client / site
                                                    </TableHead>
                                                    <TableHead>
                                                        Reminder
                                                    </TableHead>
                                                    <TableHead>
                                                        Sender
                                                    </TableHead>
                                                    <TableHead>
                                                        Template
                                                    </TableHead>
                                                    <TableHead>
                                                        Status
                                                    </TableHead>
                                                    <TableHead />
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {messages.data.map(
                                                    (message) => (
                                                        <TableRow
                                                            key={message.id}
                                                        >
                                                            <TableCell>
                                                                {times(
                                                                    message.created_at,
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {
                                                                    message.recipient
                                                                }
                                                            </TableCell>
                                                            <TableCell>
                                                                {message.is_test
                                                                    ? 'Test'
                                                                    : `${message.client_name ?? '—'} / ${message.site_name ?? '—'}`}
                                                            </TableCell>
                                                            <TableCell>
                                                                {message.reminder_type ??
                                                                    'Test'}
                                                            </TableCell>
                                                            <TableCell>
                                                                {message.account_name ??
                                                                    '—'}
                                                            </TableCell>
                                                            <TableCell>
                                                                {message.template_name ??
                                                                    '—'}
                                                            </TableCell>
                                                            <TableCell>
                                                                <StateBadge
                                                                    status={
                                                                        message.status
                                                                    }
                                                                />
                                                            </TableCell>
                                                            <TableCell>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setSelectedMessage(
                                                                            message,
                                                                        )
                                                                    }
                                                                >
                                                                    Details
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                    <Pagination
                                        currentPage={messages.current_page}
                                        totalItems={messages.total}
                                        pageSize={messages.per_page}
                                        onPageChange={(page) =>
                                            apply(working, page)
                                        }
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}
            {section === 'accounts' && (
                <div className="space-y-4">
                    {accounts.length === 0 ? (
                        <Card>
                            <CardContent className="py-10 text-center">
                                <p className="font-medium">
                                    No WhatsApp sending account yet
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Add a Meta Cloud API number to begin
                                    verifying connections and templates.
                                </p>
                                <Button
                                    className="mt-4"
                                    onClick={() => setEditingAccount(null)}
                                >
                                    Add account
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {accounts.map((account) => (
                                <Card key={account.id}>
                                    <CardHeader>
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <CardTitle>
                                                    {account.name}
                                                </CardTitle>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {
                                                        account.display_phone_number
                                                    }
                                                </p>
                                            </div>
                                            <div className="flex gap-1">
                                                {account.is_test && (
                                                    <Badge variant="outline">
                                                        Test
                                                    </Badge>
                                                )}
                                                {account.is_default && (
                                                    <Badge>Default</Badge>
                                                )}
                                                <Badge variant="outline">
                                                    {!account.is_active
                                                        ? 'Inactive'
                                                        : account.last_error
                                                          ? 'Configuration error'
                                                          : account.last_verified_at
                                                            ? 'Connected'
                                                            : 'Needs verification'}
                                                </Badge>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4 text-sm">
                                        <div className="grid grid-cols-2 gap-2">
                                            <p>
                                                <span className="text-muted-foreground">
                                                    Phone Number ID
                                                </span>
                                                <br />
                                                {account.phone_number_id}
                                            </p>
                                            <p>
                                                <span className="text-muted-foreground">
                                                    WABA ID
                                                </span>
                                                <br />
                                                {account.waba_id}
                                            </p>
                                            <p>
                                                <span className="text-muted-foreground">
                                                    Messages
                                                </span>
                                                <br />
                                                {account.messages_count}
                                            </p>
                                            <p>
                                                <span className="text-muted-foreground">
                                                    Delivered
                                                </span>
                                                <br />
                                                {account.delivered_count}
                                            </p>
                                            <p>
                                                <span className="text-muted-foreground">
                                                    Delivery rate
                                                </span>
                                                <br />
                                                {account.delivery_rate === null
                                                    ? '—'
                                                    : `${account.delivery_rate}%`}
                                            </p>
                                            <p>
                                                <span className="text-muted-foreground">
                                                    Last verified
                                                </span>
                                                <br />
                                                {times(
                                                    account.last_verified_at,
                                                )}
                                            </p>
                                            <p>
                                                <span className="text-muted-foreground">
                                                    Last used
                                                </span>
                                                <br />
                                                {times(account.last_used_at)}
                                            </p>
                                        </div>
                                        {account.last_error && (
                                            <p className="rounded-md bg-destructive/10 p-2 text-destructive">
                                                {account.last_error}
                                            </p>
                                        )}
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditingAccount(account)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        verify(account.id).url,
                                                    )
                                                }
                                            >
                                                Verify connection
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        syncTemplates(
                                                            account.id,
                                                        ).url,
                                                    )
                                                }
                                            >
                                                Sync templates
                                            </Button>
                                            {account.is_active &&
                                                !account.is_default &&
                                                !account.is_test && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.patch(
                                                                setDefault(
                                                                    account.id,
                                                                ).url,
                                                            )
                                                        }
                                                    >
                                                        Set default
                                                    </Button>
                                                )}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    if (account.is_active)
                                                        setDeactivating(
                                                            account,
                                                        );
                                                    else
                                                        router.patch(
                                                            toggleActive(
                                                                account.id,
                                                            ).url,
                                                        );
                                                }}
                                            >
                                                {account.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'}
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            )}
            {section === 'templates' && (
                <div className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>WABA template mapping</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Create and approve templates in Meta first. Map
                                each reminder type once per WABA; numbers
                                sharing that WABA reuse the mapping. Body
                                parameters must be numbered positions that match
                                this order.
                            </p>
                            <form
                                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.post(
                                        saveTemplate().url,
                                        {
                                            account_id: mapping.account_id,
                                            purpose: mapping.purpose,
                                            name: mapping.name,
                                            language: mapping.language,
                                            parameter_keys: mapping.keys
                                                .split(',')
                                                .map((value) => value.trim())
                                                .filter(Boolean),
                                            is_enabled: mapping.is_enabled,
                                        },
                                        {
                                            onStart: () => setTestBusy(true),
                                            onFinish: () => setTestBusy(false),
                                            onError: (errors) =>
                                                toast.error(
                                                    Object.values(errors)[0] ??
                                                        'Could not save mapping.',
                                                ),
                                        },
                                    );
                                }}
                            >
                                <Choice
                                    label="WABA via sender"
                                    value={mapping.account_id}
                                    options={accounts.map((account) => ({
                                        value: String(account.id),
                                        label: `${account.name} · ${account.waba_id}`,
                                    }))}
                                    onChange={(account_id) =>
                                        setMapping({ ...mapping, account_id })
                                    }
                                />
                                <Choice
                                    label="Purpose"
                                    value={mapping.purpose}
                                    options={[
                                        {
                                            value: 'asset',
                                            label: 'Inspection reminder',
                                        },
                                        {
                                            value: 'certificate',
                                            label: 'Certificate expiry',
                                        },
                                    ]}
                                    onChange={(purpose) =>
                                        setMapping({ ...mapping, purpose })
                                    }
                                />
                                <div className="space-y-1.5">
                                    <Label htmlFor="wa-template">
                                        Meta template name
                                    </Label>
                                    <Input
                                        id="wa-template"
                                        required
                                        value={mapping.name}
                                        onChange={(event) =>
                                            setMapping({
                                                ...mapping,
                                                name: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="wa-language">
                                        Language code
                                    </Label>
                                    <Input
                                        id="wa-language"
                                        required
                                        value={mapping.language}
                                        onChange={(event) =>
                                            setMapping({
                                                ...mapping,
                                                language: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="wa-keys">
                                        Body parameter order
                                    </Label>
                                    <Input
                                        id="wa-keys"
                                        value={mapping.keys}
                                        onChange={(event) =>
                                            setMapping({
                                                ...mapping,
                                                keys: event.target.value,
                                            })
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Optional. Available: client_name,
                                        site_name, subject_name, due_date,
                                        timing. Separate with commas.
                                    </p>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={mapping.is_enabled}
                                        onChange={(event) =>
                                            setMapping({
                                                ...mapping,
                                                is_enabled:
                                                    event.target.checked,
                                            })
                                        }
                                    />{' '}
                                    Enable after approval
                                </label>
                                <Button
                                    type="submit"
                                    disabled={!mapping.account_id}
                                >
                                    Save mapping
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                    <div className="grid gap-3 lg:grid-cols-2">
                        {templates.map((template) => (
                            <Card key={template.id}>
                                <CardContent className="space-y-2 pt-5">
                                    <div className="flex justify-between gap-2">
                                        <div>
                                            <strong>
                                                {template.purpose === 'asset'
                                                    ? 'Inspection reminder'
                                                    : 'Certificate expiry'}
                                            </strong>
                                            <p className="text-sm text-muted-foreground">
                                                {template.name} ·{' '}
                                                {template.language}
                                            </p>
                                        </div>
                                        <StateBadge
                                            status={
                                                template.meta_status ??
                                                'Not synced'
                                            }
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        WABA {template.waba_id} ·{' '}
                                        {template.category ??
                                            'Category unknown'}{' '}
                                        ·{' '}
                                        {template.is_enabled
                                            ? 'Enabled'
                                            : 'Disabled'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Parameters:{' '}
                                        {template.parameter_keys.join(', ') ||
                                            'None'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Last synced{' '}
                                        {times(template.last_synced_at)}
                                    </p>
                                    {template.last_error && (
                                        <p className="text-sm text-destructive">
                                            {template.last_error}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <Card>
                        <CardHeader>
                            <CardTitle>Send a test template</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Sends a sample message to one number using the
                                verified default sender and the approved
                                template for the selected reminder type. Test
                                messages stay outside reminder metrics.
                            </p>
                            <form
                                className="grid gap-3 sm:grid-cols-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.post(
                                        sendTest().url,
                                        {
                                            type: test.type,
                                            recipient: test.recipient,
                                        },
                                        {
                                            onError: (errors) =>
                                                toast.error(
                                                    Object.values(errors)[0] ??
                                                        'Could not queue test.',
                                                ),
                                        },
                                    );
                                }}
                            >
                                <div
                                    className="flex flex-wrap items-center gap-2"
                                    role="tablist"
                                    aria-label="WhatsApp test reminder type"
                                >
                                    {(['asset', 'certificate'] as const).map(
                                        (type) => (
                                            <Button
                                                key={type}
                                                type="button"
                                                role="tab"
                                                aria-selected={
                                                    test.type === type
                                                }
                                                variant={
                                                    test.type === type
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    setTest({ ...test, type })
                                                }
                                            >
                                                {type === 'asset'
                                                    ? 'Equipment'
                                                    : 'Certificate'}
                                            </Button>
                                        ),
                                    )}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="wa-recipient">
                                        Test recipient
                                    </Label>
                                    <Input
                                        id="wa-recipient"
                                        type="tel"
                                        required
                                        placeholder="+675 7123 4567"
                                        value={test.recipient}
                                        onChange={(event) =>
                                            setTest({
                                                ...test,
                                                recipient: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        disabled={testBusy || !test.recipient}
                                    >
                                        {testBusy
                                            ? 'Queueing…'
                                            : 'Send one test'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            )}
            <ConfirmDialog
                open={deactivating !== null}
                onOpenChange={(open) => {
                    if (!open) setDeactivating(null);
                }}
                title={`Deactivate ${deactivating?.name ?? 'account'}?`}
                description="Automated reminders will stop using this sender. Historical messages stay available."
                confirmLabel="Deactivate"
                destructive
                loading={deactivationBusy}
                onConfirm={() => {
                    if (!deactivating) return;
                    router.patch(
                        toggleActive(deactivating.id).url,
                        {},
                        {
                            onStart: () => setDeactivationBusy(true),
                            onFinish: () => setDeactivationBusy(false),
                            onSuccess: () => setDeactivating(null),
                        },
                    );
                }}
            />
            {editingAccount !== undefined && (
                <AccountEditor
                    key={editingAccount?.id ?? 'new'}
                    account={editingAccount}
                    open
                    onOpenChange={(open) => {
                        if (!open) setEditingAccount(undefined);
                    }}
                />
            )}
            <Dialog
                open={selectedMessage !== null}
                onOpenChange={(open) => {
                    if (!open) setSelectedMessage(null);
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Message details</DialogTitle>
                        <DialogDescription>
                            {selectedMessage?.is_test
                                ? 'Test message'
                                : 'Automated reminder'}{' '}
                            · {selectedMessage?.recipient}
                        </DialogDescription>
                    </DialogHeader>
                    {selectedMessage && (
                        <div className="grid gap-2 text-sm sm:grid-cols-2">
                            {Object.entries({
                                Status: selectedMessage.status,
                                Client: selectedMessage.client_name,
                                Site: selectedMessage.site_name,
                                Subject: selectedMessage.subject_name,
                                Reminder: selectedMessage.reminder_type,
                                'Due date': selectedMessage.due_date,
                                'Days ahead': selectedMessage.reminder_offset,
                                Sender: selectedMessage.account_name,
                                'Sender number':
                                    selectedMessage.sender_display_phone_number,
                                'Phone Number ID':
                                    selectedMessage.phone_number_id,
                                'WABA ID': selectedMessage.waba_id,
                                Template: selectedMessage.template_name,
                                Language: selectedMessage.template_language,
                                'Meta message ID':
                                    selectedMessage.meta_message_id,
                                Created: times(selectedMessage.created_at),
                                Submitted: times(selectedMessage.submitted_at),
                                Sent: times(selectedMessage.sent_at),
                                Delivered: times(selectedMessage.delivered_at),
                                Read: times(selectedMessage.read_at),
                                Failed: times(selectedMessage.failed_at),
                                'Failure code': selectedMessage.failure_code,
                                Error: selectedMessage.last_error,
                            }).map(([label, value]) => (
                                <div key={label}>
                                    <p className="text-xs text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="break-all">{value ?? '—'}</p>
                                </div>
                            ))}
                            {selectedMessage.can_retry && (
                                <Button
                                    className="sm:col-span-2"
                                    variant="outline"
                                    onClick={() => {
                                        router.post(
                                            retry(selectedMessage.id).url,
                                            {},
                                            {
                                                onSuccess: () =>
                                                    setSelectedMessage(null),
                                            },
                                        );
                                    }}
                                >
                                    Retry once after definite rejection
                                </Button>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
