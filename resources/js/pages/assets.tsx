import { usePage } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import {
    index,
    store,
    update,
    destroy,
} from '@/actions/App/Http/Controllers/AssetController';
import { RecordPage } from '@/components/record-page';
import { StatusBadge } from '@/components/status-badge';
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
    const addDisabled = sites.length === 0 || assetTypes.length === 0;

    return (
        <RecordPage
            title="Assets"
            description="Track and manage fire safety assets across all sites"
            noun="asset"
            icon={Flame}
            addDisabled={addDisabled}
            addHint={
                sites.length === 0
                    ? 'Add a client and site before creating an asset.'
                    : 'Set up an asset type before creating an asset.'
            }
            emptyDescription="Add your first fire safety asset to start tracking inspections and compliance."
            records={records}
            filters={filters}
            routes={{ index, store, update, destroy }}
            searchPlaceholder="Search by serial, location, or site..."
            dateLabel="Select date range"
            statusOptions={[
                { value: 'overdue', label: 'Overdue' },
                { value: 'due_soon', label: 'Due soon' },
                { value: 'ok', label: 'OK' },
                { value: 'unknown', label: 'No due date' },
            ]}
            columns={[
                {
                    key: 'serial',
                    header: 'Serial',
                    className: 'font-mono text-sm',
                    render: (asset) => (
                        <span>{asset.serial_number || '—'}</span>
                    ),
                },
                {
                    key: 'site',
                    header: 'Site',
                    className: 'text-sm text-muted-foreground',
                    render: (asset) =>
                        `${asset.site.client?.company_name} > ${asset.site.site_name}`,
                },
                {
                    key: 'location',
                    header: 'Location',
                    className: 'text-sm text-muted-foreground',
                    render: (asset) => asset.location_desc || '—',
                },
                {
                    key: 'due',
                    header: 'Next due',
                    className: 'w-[120px] text-sm text-muted-foreground',
                    render: (asset) => asset.next_service_due || '—',
                },
                {
                    key: 'status',
                    header: 'Status',
                    className: 'w-[100px]',
                    render: (asset) => (
                        <StatusBadge
                            date={asset.next_service_due}
                            showDate={false}
                            asOfDate={today}
                        />
                    ),
                },
            ]}
            defaults={{
                site_id: String(sites[0]?.id ?? ''),
                asset_type_id: String(assetTypes[0]?.id ?? ''),
                serial_number: '',
                location_desc: '',
                next_service_due: '',
                install_date: '',
                last_service_date: '',
                status: 'active',
            }}
            fields={[
                {
                    name: 'site_id',
                    label: 'Site',
                    type: 'select',
                    required: true,
                    options: sites.map((site) => ({
                        value: String(site.id),
                        label: `${site.client?.company_name} > ${site.site_name}`,
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
                { name: 'serial_number', label: 'Serial number' },
                { name: 'location_desc', label: 'Location' },
                {
                    name: 'next_service_due',
                    label: 'Next service due',
                    type: 'date',
                },
                { name: 'install_date', label: 'Install date', type: 'date' },
                {
                    name: 'last_service_date',
                    label: 'Last service date',
                    type: 'date',
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
