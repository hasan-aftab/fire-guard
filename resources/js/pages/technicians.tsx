import { Link } from '@inertiajs/react';
import { CalendarClock, CalendarX, ShieldAlert, Users } from 'lucide-react';
import {
    index,
    store,
    update,
    destroy,
    restore,
    markError,
    restoreError,
    purge,
} from '@/actions/App/Http/Controllers/TechnicianController';
import { index as inspectionsIndex } from '@/actions/App/Http/Controllers/InspectionController';
import { RecordPage } from '@/components/record-page';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { ViewField } from '@/components/view-modal';
import type {
    Paginated,
    Technician,
    TrackerFilters,
} from '@/types/fire-tracker';

export default function Technicians({
    records,
    filters,
    overview,
}: {
    records: Paginated<Technician>;
    filters: TrackerFilters;
    overview: {
        active: number;
        expired: number;
        expiring_soon: number;
        missing: number;
    };
}) {
    return (
        <RecordPage
            title="Technicians"
            description="Manage fire safety technicians and their licences"
            noun="technician"
            icon={Users}
            overview={
                <section aria-label="Technician overview" className="mb-5">
                    <p className="mb-2 text-xs text-muted-foreground">
                        Across all current technicians · licence counts include
                        active technicians
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label="Active technicians"
                            value={overview.active}
                            icon={Users}
                        />
                        <StatCard
                            label="Expired licences"
                            value={overview.expired}
                            icon={ShieldAlert}
                            tone="red"
                        />
                        <StatCard
                            label="Expiring in 30 days"
                            value={overview.expiring_soon}
                            icon={CalendarClock}
                            tone="amber"
                        />
                        <StatCard
                            label="No expiry on file"
                            value={overview.missing}
                            icon={CalendarX}
                        />
                    </div>
                </section>
            }
            emptyDescription="Add your first technician to start assigning inspections."
            archive={{
                isArchived: (technician) => !technician.is_active,
                action: 'Deactivate',
                description:
                    'Deactivate this technician? Their past inspections will keep their name and attribution.',
                restoreDescription:
                    'Reactivate this technician? They will be available for new inspection assignments.',
            }}
            records={records}
            filters={filters}
            routes={{
                index,
                store,
                update,
                destroy,
                restore,
                markError,
                restoreError,
                purge,
            }}
            mistake={{
                isEnteredInError: (technician) =>
                    technician.entered_in_error_at !== null,
                canPurge: (technician) => technician.inspections_count === 0,
            }}
            searchPlaceholder="Search name, phone, email, or licence..."
            dateLabel="Licence expiry range"
            statusOptions={[
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ]}
            extraFilters={[
                {
                    key: 'license_status',
                    label: 'Licence status',
                    options: [
                        { value: 'all', label: 'All licences' },
                        { value: 'expired', label: 'Expired' },
                        { value: 'expiring_soon', label: 'Expiring soon' },
                        { value: 'valid', label: 'Valid' },
                        { value: 'missing', label: 'No expiry on file' },
                    ],
                },
                {
                    key: 'inspection_activity',
                    label: 'Inspection history',
                    options: [
                        { value: 'all', label: 'Any history' },
                        {
                            value: 'with_inspections',
                            label: 'With inspections',
                        },
                        {
                            value: 'without_inspections',
                            label: 'No inspections',
                        },
                    ],
                },
                {
                    key: 'sort',
                    label: 'Sort',
                    options: [
                        { value: 'name', label: 'Name A–Z' },
                        { value: 'expiry_soonest', label: 'Earliest expiry' },
                        {
                            value: 'most_inspections',
                            label: 'Most inspections',
                        },
                    ],
                },
            ]}
            columns={[
                {
                    key: 'name',
                    header: 'Name',
                    viewRender: (technician) => technician.full_name,
                    render: (technician) => (
                        <span className="flex flex-col items-start gap-1">
                            <span
                                className="max-w-36 truncate font-medium sm:max-w-none"
                                title={technician.full_name}
                            >
                                {technician.full_name}
                            </span>
                            {technician.license_expiry && (
                                <span className="text-xs text-muted-foreground sm:hidden">
                                    {technician.license_expiry}
                                </span>
                            )}
                            <span className="sm:hidden">
                                {technician.license_status === 'unknown' ? (
                                    <Badge variant="outline">
                                        No expiry on file
                                    </Badge>
                                ) : (
                                    <StatusBadge
                                        status={technician.license_status}
                                        date={null}
                                        showDate={false}
                                    />
                                )}
                            </span>
                            {!technician.is_active && (
                                <span className="text-xs text-muted-foreground sm:hidden">
                                    Inactive
                                </span>
                            )}
                        </span>
                    ),
                },
                {
                    key: 'contact',
                    header: 'Contact',
                    className: 'hidden lg:table-cell',
                    showInView: false,
                    render: (technician) => (
                        <span className="flex flex-col text-xs">
                            <span>{technician.phone || 'No phone'}</span>
                            <span className="text-muted-foreground">
                                {technician.email || 'No email'}
                            </span>
                        </span>
                    ),
                },
                {
                    key: 'license',
                    header: 'Licence',
                    className: 'hidden md:table-cell',
                    render: (technician) => technician.license_number || '—',
                },
                {
                    key: 'expiry',
                    header: 'Licence expiry',
                    className: 'hidden sm:table-cell',
                    render: (technician) => technician.license_expiry || '—',
                },
                {
                    key: 'license_status',
                    header: 'Licence status',
                    className: 'hidden sm:table-cell',
                    render: (technician) =>
                        technician.license_status === 'unknown' ? (
                            <Badge variant="outline">No expiry on file</Badge>
                        ) : (
                            <StatusBadge
                                status={technician.license_status}
                                date={null}
                                showDate={false}
                            />
                        ),
                },
                {
                    key: 'active',
                    header: 'Status',
                    className: 'hidden sm:table-cell',
                    render: (technician) => (
                        <Badge
                            variant={
                                technician.is_active ? 'secondary' : 'outline'
                            }
                        >
                            {technician.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    ),
                },
                {
                    key: 'inspections',
                    header: 'Inspections',
                    className: 'hidden md:table-cell',
                    render: (technician) => technician.inspections_count ?? 0,
                },
            ]}
            viewContent={(technician) => (
                <div className="grid gap-4 sm:grid-cols-2">
                    <ViewField label="Phone" value={technician.phone} />
                    <ViewField label="Email" value={technician.email} />
                    <ViewField
                        label="Inspection history"
                        value={
                            <Link
                                href={inspectionsIndex({
                                    query: { technician_id: technician.id },
                                })}
                                className="font-medium text-primary underline underline-offset-2"
                            >
                                View {technician.inspections_count ?? 0}{' '}
                                inspections
                            </Link>
                        }
                    />
                </div>
            )}
            defaults={{
                full_name: '',
                phone: '',
                email: '',
                license_number: '',
                license_expiry: '',
                is_active: '1',
            }}
            fields={[
                {
                    name: 'full_name',
                    label: 'Full name',
                    required: true,
                    maxLength: 255,
                },
                {
                    name: 'phone',
                    label: 'Phone',
                    type: 'tel',
                    maxLength: 255,
                    description:
                        'Use 7 to 20 digits. +, spaces, brackets, dashes, dots and extensions are allowed.',
                },
                {
                    name: 'email',
                    label: 'Email',
                    type: 'email',
                    maxLength: 255,
                },
                {
                    name: 'license_number',
                    label: 'Licence number',
                    maxLength: 255,
                },
                {
                    name: 'license_expiry',
                    label: 'Licence expiry',
                    type: 'date',
                },
                {
                    name: 'is_active',
                    label: 'Active technician',
                    type: 'checkbox',
                },
            ]}
        />
    );
}

Technicians.layout = {
    breadcrumbs: [{ title: 'Technicians', href: index() }],
};
