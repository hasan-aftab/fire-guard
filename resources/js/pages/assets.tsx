import { usePage } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import {
    index,
    store,
    update,
    destroy,
    restore,
    markError,
    restoreError,
    purge,
} from '@/actions/App/Http/Controllers/AssetController';
import { RecordPage } from '@/components/record-page';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { ViewField } from '@/components/view-modal';
import type {
    Asset,
    AssetType,
    Paginated,
    Site,
    TrackerFilters,
} from '@/types/fire-tracker';

export default function Assets({
    records,
    sites,
    assetTypes,
    filters,
}: {
    records: Paginated<Asset>;
    sites: Site[];
    assetTypes: AssetType[];
    filters: TrackerFilters;
}) {
    const { today } = usePage().props;
    const activeSites = sites.filter(
        (site) =>
            site.is_active &&
            site.client?.is_active &&
            !site.entered_in_error_at &&
            !site.client?.entered_in_error_at,
    );
    const equipmentStatuses: Record<string, string> = {
        active: 'Active',
        decommissioned: 'Decommissioned',
        missing: 'Missing',
        needs_replacement: 'Needs replacement',
    };

    return (
        <RecordPage
            title="Assets"
            description="Track and manage fire safety assets across all sites"
            noun="asset"
            icon={Flame}
            addDisabled={activeSites.length === 0 || assetTypes.length === 0}
            addHint={
                activeSites.length === 0
                    ? 'Add a client and site before creating an asset.'
                    : 'Set up an asset type before creating an asset.'
            }
            emptyDescription="Add your first fire safety asset to start tracking inspections and compliance."
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
                isEnteredInError: (asset) => asset.entered_in_error_at !== null,
                canPurge: (asset) =>
                    asset.inspections_count === 0 &&
                    asset.certificates_count === 0,
            }}
            archive={{
                isArchived: (asset) => asset.status === 'decommissioned',
                action: 'Decommission',
                description:
                    'Decommission this equipment? Its previous inspection and service history will remain available.',
                restoreDescription:
                    'Reactivate this equipment? Its saved service due date will again count as active work if its site and client are active.',
            }}
            searchPlaceholder="Search serial, location, site, client, or type..."
            dateLabel="Next service due"
            statusOptions={[
                { value: 'overdue', label: 'Overdue' },
                { value: 'due_soon', label: 'Due soon' },
                { value: 'ok', label: 'OK' },
                { value: 'unknown', label: 'No due date' },
            ]}
            extraFilters={[
                {
                    key: 'site_id',
                    label: 'Site',
                    options: [
                        { value: 'all', label: 'All sites' },
                        ...sites.map((site) => ({
                            value: String(site.id),
                            label: `${site.client?.company_name} > ${site.site_name}`,
                        })),
                    ],
                },
                {
                    key: 'asset_type_id',
                    label: 'Asset type',
                    options: [
                        { value: 'all', label: 'All types' },
                        ...assetTypes.map((type) => ({
                            value: String(type.id),
                            label: type.type_name,
                        })),
                    ],
                },
                {
                    key: 'equipment_status',
                    label: 'Equipment status',
                    options: [
                        { value: 'all', label: 'All equipment' },
                        ...Object.entries(equipmentStatuses).map(
                            ([value, label]) => ({ value, label }),
                        ),
                    ],
                },
                {
                    key: 'sort',
                    label: 'Sort',
                    options: [
                        { value: 'oldest', label: 'Oldest added' },
                        { value: 'newest', label: 'Newest added' },
                        { value: 'due_soonest', label: 'Due soonest' },
                        { value: 'due_latest', label: 'Due latest' },
                    ],
                },
            ]}
            columns={[
                {
                    key: 'serial',
                    header: 'Serial',
                    className: 'font-mono text-sm',
                    render: (asset) => (
                        <>
                            <span className="flex flex-col items-start gap-1 sm:hidden">
                                <span title={asset.serial_number ?? undefined}>
                                    {asset.serial_number &&
                                    asset.serial_number.length > 17
                                        ? `${asset.serial_number.slice(0, 6)}…${asset.serial_number.slice(-8)}`
                                        : asset.serial_number || '—'}
                                </span>
                                {asset.status === 'active' &&
                                !asset.entered_in_error_at &&
                                asset.site.is_active &&
                                !asset.site.entered_in_error_at &&
                                asset.site.client?.is_active &&
                                !asset.site.client?.entered_in_error_at ? (
                                    <StatusBadge
                                        date={asset.next_service_due}
                                        showDate={false}
                                        asOfDate={today}
                                    />
                                ) : (
                                    <Badge variant="outline">
                                        {asset.status === 'active'
                                            ? 'Inactive site/client'
                                            : (equipmentStatuses[
                                                  asset.status
                                              ] ?? asset.status)}
                                    </Badge>
                                )}
                            </span>
                            <span className="hidden sm:inline">
                                {asset.serial_number || '—'}
                            </span>
                        </>
                    ),
                },
                {
                    key: 'type',
                    header: 'Type',
                    className: 'hidden text-sm sm:table-cell',
                    render: (asset) => asset.asset_type.type_name,
                },
                {
                    key: 'site',
                    header: 'Site',
                    className:
                        'hidden text-sm text-muted-foreground sm:table-cell',
                    render: (asset) =>
                        `${asset.site.client?.company_name} > ${asset.site.site_name}`,
                },
                {
                    key: 'location',
                    header: 'Location',
                    className:
                        'hidden text-sm text-muted-foreground sm:table-cell',
                    render: (asset) => asset.location_desc || '—',
                },
                {
                    key: 'due',
                    header: 'Next due',
                    className:
                        'hidden w-[120px] text-sm text-muted-foreground sm:table-cell',
                    render: (asset) => asset.next_service_due || '—',
                },
                {
                    key: 'status',
                    header: 'Service status',
                    className: 'hidden w-[100px] sm:table-cell',
                    render: (asset) =>
                        asset.status === 'active' &&
                        !asset.entered_in_error_at &&
                        asset.site.is_active &&
                        !asset.site.entered_in_error_at &&
                        asset.site.client?.is_active &&
                        !asset.site.client?.entered_in_error_at ? (
                            <StatusBadge
                                date={asset.next_service_due}
                                showDate={false}
                                asOfDate={today}
                            />
                        ) : (
                            <Badge variant="outline">
                                {asset.status === 'active'
                                    ? 'Inactive site/client'
                                    : (equipmentStatuses[asset.status] ??
                                      asset.status)}
                            </Badge>
                        ),
                },
                {
                    key: 'equipment',
                    header: 'Equipment',
                    className: 'hidden sm:table-cell',
                    render: (asset) => (
                        <Badge variant="outline">
                            {equipmentStatuses[asset.status] ?? asset.status}
                        </Badge>
                    ),
                },
            ]}
            viewContent={(asset) => (
                <div className="grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-2">
                    <div className="sm:hidden">
                        <ViewField
                            label="Full serial number"
                            value={asset.serial_number}
                        />
                    </div>
                    <ViewField
                        label="Type category"
                        value={asset.asset_type.category}
                    />
                    <ViewField
                        label="Install date"
                        value={asset.install_date}
                    />
                    <ViewField
                        label="Last service date"
                        value={asset.last_service_date}
                    />
                </div>
            )}
            defaults={{
                site_id: String(activeSites[0]?.id ?? ''),
                asset_type_id: String(assetTypes[0]?.id ?? ''),
                serial_number: '',
                location_desc: '',
                next_service_due: '',
                install_date: '',
                last_service_date: '',
                status: 'active',
            }}
            fields={(editing) => [
                {
                    name: 'site_id',
                    label: 'Site',
                    type: 'select',
                    required: true,
                    options: sites
                        .filter(
                            (site) =>
                                (site.is_active &&
                                    site.client?.is_active &&
                                    !site.entered_in_error_at &&
                                    !site.client?.entered_in_error_at) ||
                                site.id === editing?.site_id,
                        )
                        .map((site) => ({
                            value: String(site.id),
                            label: `${site.client?.company_name} > ${site.site_name}${!site.is_active || !site.client?.is_active ? ' (Inactive)' : ''}`,
                        })),
                },
                {
                    name: 'asset_type_id',
                    label: 'Asset type',
                    type: 'select',
                    required: true,
                    options: assetTypes.map((type) => ({
                        value: String(type.id),
                        label: type.type_name,
                    })),
                },
                {
                    name: 'serial_number',
                    label: 'Serial number',
                    maxLength: 255,
                },
                { name: 'location_desc', label: 'Location', maxLength: 5000 },
                { name: 'install_date', label: 'Install date', type: 'date' },
                {
                    name: 'last_service_date',
                    label: 'Last service date',
                    type: 'date',
                    afterOrEqual: 'install_date',
                },
                {
                    name: 'next_service_due',
                    label: 'Next service due',
                    type: 'date',
                    afterOrEqual: ['install_date', 'last_service_date'],
                },
                {
                    name: 'status',
                    label: 'Equipment status',
                    type: 'select',
                    options: [
                        { value: 'active', label: 'Active' },
                        { value: 'decommissioned', label: 'Decommissioned' },
                        { value: 'missing', label: 'Missing' },
                        {
                            value: 'needs_replacement',
                            label: 'Needs replacement',
                        },
                    ],
                },
            ]}
        />
    );
}
