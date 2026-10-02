import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    Eye,
    Flame,
    RotateCcw,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { dashboard } from '@/routes';
import { ComplianceGauge } from '@/components/compliance-gauge';
import { DataTable } from '@/components/data-table';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import {
    UpcomingInspectionsCalendar,
    UpcomingInspectionsList,
} from '@/components/upcoming-inspections-calendar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { ViewField, ViewModal } from '@/components/view-modal';
import type {
    DashboardFilters,
    DashboardCalendar,
    DashboardSummary,
    ExpiringCertificate,
    Paginated,
    UpcomingInspection,
} from '@/types/fire-tracker';

type Section = 'attention' | 'certificates';

export default function Dashboard({
    summary,
    calendar,
    attention,
    certificates,
    filters,
}: {
    summary: DashboardSummary | null;
    calendar: DashboardCalendar | null;
    attention: Paginated<UpcomingInspection> | null;
    certificates: Paginated<ExpiringCertificate> | null;
    filters: DashboardFilters;
}) {
    const [viewingAttention, setViewingAttention] =
        useState<UpcomingInspection | null>(null);
    const [viewingCert, setViewingCert] = useState<ExpiringCertificate | null>(
        null,
    );
    const [loadingSection, setLoadingSection] = useState<Section | null>(null);
    const [loadingCalendar, setLoadingCalendar] = useState(false);
    const [error, setError] = useState('');

    if (!summary || !calendar || !attention || !certificates) {
        return (
            <div>
                <Head title="Dashboard" />
                <PageHeader
                    title="Dashboard"
                    description="Overview of your fire safety operations"
                />
                <EmptyState
                    icon={AlertTriangle}
                    title="Failed to load dashboard"
                    description="There was an error loading the dashboard data. Please try refreshing the page."
                    action={
                        <Button onClick={() => router.reload()}>Retry</Button>
                    }
                />
            </div>
        );
    }

    const applyFilters = (section: Section, changes: DashboardFilters) => {
        const nextFilters: DashboardFilters = { ...filters, ...changes };
        for (const key of Object.keys(
            nextFilters,
        ) as (keyof DashboardFilters)[]) {
            if (nextFilters[key] === '' || nextFilters[key] === undefined) {
                delete nextFilters[key];
            }
        }

        router.get(dashboard.url(), nextFilters, {
            only: ['summary', section, 'filters', 'today'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                setError('');
                setLoadingSection(section);
            },
            onError: () =>
                setError(
                    'The dashboard could not apply that filter. Please try again.',
                ),
            onFinish: () =>
                setLoadingSection((current) =>
                    current === section ? null : current,
                ),
        });
    };

    const applyCalendar = (changes: DashboardFilters) => {
        router.get(
            dashboard.url(),
            { ...filters, ...changes },
            {
                only: ['calendar', 'filters', 'today'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => {
                    setError('');
                    setLoadingCalendar(true);
                },
                onError: () =>
                    setError('The calendar could not load. Please try again.'),
                onFinish: () => setLoadingCalendar(false),
            },
        );
    };

    const attentionStart = (attention.current_page - 1) * attention.per_page;
    const certStart = (certificates.current_page - 1) * certificates.per_page;
    const attentionFiltered = Boolean(
        filters.attention_from || filters.attention_to,
    );
    const certFiltered = Boolean(filters.cert_from || filters.cert_to);

    const attentionColumns = [
        {
            key: '#',
            header: '#',
            className: 'w-10 text-xs text-muted-foreground',
            render: (_asset: UpcomingInspection, index: number) => (
                <span className="text-xs">{index + 1}</span>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            render: (asset: UpcomingInspection) => (
                <span className="text-sm font-medium">{asset.typeName}</span>
            ),
        },
        {
            key: 'serial',
            header: 'Serial',
            className: 'font-mono text-xs text-muted-foreground',
            render: (asset: UpcomingInspection) => asset.serialNumber || '—',
        },
        {
            key: 'site',
            header: 'Site / Location',
            className: 'text-sm text-muted-foreground',
            render: (asset: UpcomingInspection) =>
                [asset.siteName, asset.locationDesc]
                    .filter(Boolean)
                    .join(' · '),
        },
        {
            key: 'nextDue',
            header: 'Next due',
            className: 'text-sm text-muted-foreground',
            render: (asset: UpcomingInspection) => asset.nextServiceDue,
        },
        {
            key: 'status',
            header: 'Status',
            render: (asset: UpcomingInspection) => (
                <StatusBadge
                    status={asset.serviceStatus}
                    date={asset.nextServiceDue}
                    showDate={false}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-16',
            render: (asset: UpcomingInspection) => (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`View ${asset.typeName} ${asset.serialNumber || 'asset'}`}
                    onClick={() => setViewingAttention(asset)}
                    className="size-8 text-muted-foreground hover:text-foreground"
                >
                    <Eye className="size-4" />
                </Button>
            ),
        },
    ];

    const certificateColumns = [
        {
            key: '#',
            header: '#',
            className: 'w-10 text-xs text-muted-foreground',
            render: (_certificate: ExpiringCertificate, index: number) => (
                <span className="text-xs">{index + 1}</span>
            ),
        },
        {
            key: 'type',
            header: 'Certificate',
            render: (certificate: ExpiringCertificate) => (
                <span className="text-sm font-medium">
                    {certificate.certificateType}
                </span>
            ),
        },
        {
            key: 'site',
            header: 'Site',
            className: 'text-sm text-muted-foreground',
            render: (certificate: ExpiringCertificate) => certificate.siteName,
        },
        {
            key: 'expiry',
            header: 'Expiry',
            className: 'text-sm text-muted-foreground',
            render: (certificate: ExpiringCertificate) =>
                certificate.expiryDate,
        },
        {
            key: 'status',
            header: 'Status',
            render: (certificate: ExpiringCertificate) => (
                <StatusBadge
                    status={certificate.status}
                    date={certificate.expiryDate}
                    showDate={false}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-16',
            render: (certificate: ExpiringCertificate) => (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`View ${certificate.certificateType} at ${certificate.siteName}`}
                    onClick={() => setViewingCert(certificate)}
                    className="size-8 text-muted-foreground hover:text-foreground"
                >
                    <Eye className="size-4" />
                </Button>
            ),
        },
    ];

    return (
        <div>
            <Head title="Dashboard" />
            <PageHeader
                title="Dashboard"
                description="Overview of your fire safety operations"
            />

            {error && (
                <Alert variant="destructive" className="mb-6">
                    <AlertTriangle />
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <>
                <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Active clients"
                        value={summary.stats.activeClients}
                        icon={Building2}
                        tone="blue"
                        description="Registered companies"
                    />
                    <StatCard
                        label="Total assets"
                        value={summary.stats.totalAssets}
                        icon={Flame}
                        description="Fire safety equipment"
                    />
                    <StatCard
                        label="Overdue inspections"
                        value={summary.stats.overdueInspections}
                        icon={AlertTriangle}
                        tone="red"
                        description="Requires immediate attention"
                    />
                    <StatCard
                        label="Expired / expiring"
                        value={summary.stats.expiringCerts}
                        icon={ShieldCheck}
                        tone="amber"
                        description="Certificates due within 30 days"
                    />
                </div>

                <div className="mb-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                    <ComplianceGauge
                        {...summary.complianceRate}
                        needsRepair={summary.complianceRate.needs_repair}
                    />
                    <div className="relative" aria-busy={loadingCalendar}>
                        <UpcomingInspectionsCalendar
                            calendar={calendar}
                            dates={summary.upcomingDates}
                            asOfDate={summary.asOfDate}
                            onMonthChange={(month) =>
                                applyCalendar({
                                    calendar_month: month,
                                    calendar_page: 1,
                                })
                            }
                        />
                        {loadingCalendar && (
                            <div
                                className="absolute inset-0 flex items-center justify-center rounded-xl bg-card/70"
                                role="status"
                            >
                                <Spinner className="size-6" />
                                <span className="sr-only">
                                    Loading calendar
                                </span>
                            </div>
                        )}
                    </div>
                    <div
                        className="relative lg:col-span-2"
                        aria-busy={loadingCalendar}
                    >
                        <UpcomingInspectionsList
                            calendar={calendar}
                            onPageChange={(page) =>
                                applyCalendar({
                                    calendar_month: calendar.month,
                                    calendar_page: page,
                                })
                            }
                        />
                        {loadingCalendar && (
                            <div
                                className="absolute inset-0 flex items-center justify-center rounded-xl bg-card/70"
                                role="status"
                            >
                                <Spinner className="size-6" />
                                <span className="sr-only">
                                    Loading due items
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex flex-col gap-6">
                    <Card
                        className="relative flex flex-col gap-0 py-0"
                        aria-busy={loadingSection === 'attention'}
                    >
                        <CardHeader className="border-b py-3">
                            <div className="flex flex-col items-start gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <CardTitle className="flex items-center gap-2 text-base leading-7">
                                    <span className="flex size-7 items-center justify-center rounded-md bg-warning/10">
                                        <AlertTriangle
                                            className="size-4 text-warning"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    Needs attention
                                </CardTitle>
                                <div className="flex max-w-full items-center gap-2">
                                    <DateRangePicker
                                        from={String(
                                            filters.attention_from ?? '',
                                        )}
                                        to={String(filters.attention_to ?? '')}
                                        onSelect={(range) =>
                                            applyFilters('attention', {
                                                attention_from: range.from,
                                                attention_to: range.to,
                                                attention_page: 1,
                                            })
                                        }
                                        placeholder="Filter by due date"
                                    />
                                    {attentionFiltered && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            aria-label="Clear due date filter"
                                            onClick={() =>
                                                applyFilters('attention', {
                                                    attention_from: '',
                                                    attention_to: '',
                                                    attention_page: 1,
                                                })
                                            }
                                            className="h-8 px-2 text-muted-foreground"
                                        >
                                            <RotateCcw className="size-3.5" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col p-0">
                            {attention.total === 0 ? (
                                <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
                                    <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-success/10">
                                        <CheckCircle2 className="size-6 text-success" />
                                    </div>
                                    <p className="text-sm font-medium text-foreground">
                                        {attentionFiltered
                                            ? 'No matching assets'
                                            : 'All clear'}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {attentionFiltered
                                            ? 'Try a different due date range'
                                            : 'No assets need attention'}
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <DataTable
                                        columns={attentionColumns}
                                        data={attention.data}
                                        startIndex={attentionStart}
                                        rowKey={(asset) => asset.assetId}
                                    />
                                    {attention.total > attention.per_page && (
                                        <div className="border-t">
                                            <Pagination
                                                currentPage={
                                                    attention.current_page
                                                }
                                                totalItems={attention.total}
                                                pageSize={attention.per_page}
                                                onPageChange={(page) =>
                                                    applyFilters('attention', {
                                                        attention_page: page,
                                                    })
                                                }
                                            />
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                        {loadingSection === 'attention' && (
                            <div
                                className="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-card/70"
                                role="status"
                            >
                                <Spinner className="size-6" />
                                <span className="sr-only">Loading assets</span>
                            </div>
                        )}
                    </Card>

                    <Card
                        className="relative flex flex-col gap-0 py-0"
                        aria-busy={loadingSection === 'certificates'}
                    >
                        <CardHeader className="border-b py-3">
                            <div className="flex flex-col items-start gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <CardTitle className="flex items-center gap-2 text-base leading-7">
                                    <span className="flex size-7 items-center justify-center rounded-md bg-warning/10">
                                        <ShieldCheck
                                            className="size-4 text-warning"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    Expired or expiring certificates
                                </CardTitle>
                                <div className="flex max-w-full items-center gap-2">
                                    <DateRangePicker
                                        from={String(filters.cert_from ?? '')}
                                        to={String(filters.cert_to ?? '')}
                                        onSelect={(range) =>
                                            applyFilters('certificates', {
                                                cert_from: range.from,
                                                cert_to: range.to,
                                                cert_page: 1,
                                            })
                                        }
                                        placeholder="Filter by expiry date"
                                    />
                                    {certFiltered && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            aria-label="Clear expiry date filter"
                                            onClick={() =>
                                                applyFilters('certificates', {
                                                    cert_from: '',
                                                    cert_to: '',
                                                    cert_page: 1,
                                                })
                                            }
                                            className="h-8 px-2 text-muted-foreground"
                                        >
                                            <RotateCcw className="size-3.5" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col p-0">
                            {certificates.total === 0 ? (
                                <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
                                    <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-success/10">
                                        <CheckCircle2 className="size-6 text-success" />
                                    </div>
                                    <p className="text-sm font-medium text-foreground">
                                        {certFiltered
                                            ? 'No matching certificates'
                                            : 'All clear'}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {certFiltered
                                            ? 'Try a different expiry date range'
                                            : 'No certificates are expired or expiring soon'}
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <DataTable
                                        columns={certificateColumns}
                                        data={certificates.data}
                                        startIndex={certStart}
                                        rowKey={(certificate) =>
                                            certificate.certificateId
                                        }
                                    />
                                    {certificates.total >
                                        certificates.per_page && (
                                        <div className="border-t">
                                            <Pagination
                                                currentPage={
                                                    certificates.current_page
                                                }
                                                totalItems={certificates.total}
                                                pageSize={certificates.per_page}
                                                onPageChange={(page) =>
                                                    applyFilters(
                                                        'certificates',
                                                        { cert_page: page },
                                                    )
                                                }
                                            />
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                        {loadingSection === 'certificates' && (
                            <div
                                className="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-card/70"
                                role="status"
                            >
                                <Spinner className="size-6" />
                                <span className="sr-only">
                                    Loading certificates
                                </span>
                            </div>
                        )}
                    </Card>
                </div>
            </>

            <ViewModal
                open={viewingAttention !== null}
                onClose={() => setViewingAttention(null)}
                title="Asset Details"
            >
                {viewingAttention && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <ViewField
                            label="Type"
                            value={viewingAttention.typeName}
                        />
                        <ViewField
                            label="Serial Number"
                            value={viewingAttention.serialNumber}
                        />
                        <ViewField
                            label="Site"
                            value={viewingAttention.siteName}
                        />
                        <ViewField
                            label="Location"
                            value={viewingAttention.locationDesc}
                        />
                        <ViewField
                            label="Next Service Due"
                            value={viewingAttention.nextServiceDue}
                        />
                        <ViewField
                            label="Status"
                            value={
                                <StatusBadge
                                    status={viewingAttention.serviceStatus}
                                    date={viewingAttention.nextServiceDue}
                                />
                            }
                        />
                    </div>
                )}
            </ViewModal>

            <ViewModal
                open={viewingCert !== null}
                onClose={() => setViewingCert(null)}
                title="Certificate Details"
            >
                {viewingCert && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <ViewField
                            label="Certificate Type"
                            value={viewingCert.certificateType}
                        />
                        <ViewField label="Site" value={viewingCert.siteName} />
                        <ViewField
                            label="Expiry Date"
                            value={viewingCert.expiryDate}
                        />
                        <ViewField
                            label="Status"
                            value={
                                <StatusBadge
                                    status={viewingCert.status}
                                    date={viewingCert.expiryDate}
                                />
                            }
                        />
                    </div>
                )}
            </ViewModal>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
