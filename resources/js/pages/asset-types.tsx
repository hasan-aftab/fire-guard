import { Shapes } from 'lucide-react';
import {
    index,
    store,
    update,
    destroy,
    restore,
} from '@/actions/App/Http/Controllers/AssetTypeController';
import { RecordPage } from '@/components/record-page';
import { Badge } from '@/components/ui/badge';
import type {
    AssetType,
    Paginated,
    TrackerFilters,
} from '@/types/fire-tracker';

type ManagedAssetType = AssetType & { assets_count: number };

export default function AssetTypes({
    records,
    filters,
}: {
    records: Paginated<ManagedAssetType>;
    filters: TrackerFilters;
}) {
    return (
        <RecordPage
            title="Asset Types"
            description="Manage equipment classifications and future inspection intervals"
            noun="asset type"
            icon={Shapes}
            records={records}
            filters={filters}
            routes={{ index, store, update, destroy, restore }}
            searchPlaceholder="Search asset types..."
            emptyDescription="Add an asset type to make it available for new equipment."
            columns={[
                {
                    key: 'name',
                    header: 'Name',
                    render: (type) => type.type_name,
                },
                {
                    key: 'interval',
                    header: 'Default inspection interval',
                    render: (type) =>
                        type.default_interval_months === null
                            ? 'No default'
                            : `${type.default_interval_months} months`,
                },
                {
                    key: 'used',
                    header: 'Equipment',
                    render: (type) => type.assets_count,
                },
                {
                    key: 'status',
                    header: 'Status',
                    render: (type) => (
                        <Badge
                            variant={type.is_active ? 'default' : 'secondary'}
                        >
                            {type.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    ),
                },
            ]}
            defaults={{
                type_name: '',
                default_interval_months: '12',
            }}
            fields={[
                {
                    name: 'type_name',
                    label: 'Name',
                    required: true,
                    maxLength: 255,
                },
                {
                    name: 'default_interval_months',
                    label: 'Default inspection interval (months)',
                    type: 'number',
                    required: true,
                    min: 1,
                    max: 1200,
                    description:
                        'Used when a future inspection sets its next due date. Existing scheduled dates stay unchanged.',
                },
            ]}
            archive={{
                isArchived: (type) => !type.is_active,
                action: 'Deactivate',
                restoreAction: 'Activate',
                description: (type) =>
                    `This type is used by ${type.assets_count} equipment records. Existing equipment will keep it, but it will no longer be available for new equipment.`,
                restoreDescription:
                    'Make this type available for new equipment again?',
            }}
        />
    );
}

AssetTypes.layout = { breadcrumbs: [{ title: 'Asset Types', href: index() }] };
