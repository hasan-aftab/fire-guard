import { usePage } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import {
    index,
    store,
    update,
    markError,
    restoreError,
} from '@/actions/App/Http/Controllers/InspectionController';
import { RecordPage } from '@/components/record-page';
import { InspectionPhotos } from '@/components/managed-file-panel';
import { ResultBadge } from '@/components/status-badge';
import { ViewField } from '@/components/view-modal';
import type {
    Inspection,
    InspectionAssetOption,
    Paginated,
    Technician,
    TrackerFilters,
} from '@/types/fire-tracker';

export default function Inspections({
    records,
    assets,
    technicians,
    filters,
}: {
    records: Paginated<Inspection>;
    assets: InspectionAssetOption[];
    technicians: Pick<
        Technician,
        'id' | 'full_name' | 'is_active' | 'entered_in_error_at'
    >[];
    filters: TrackerFilters;
}) {
    const { today } = usePage().props;
    const activeAssets = assets.filter(
        (asset) =>
            asset.status === 'active' &&
            !asset.entered_in_error_at &&
            asset.site.is_active &&
            !asset.site.entered_in_error_at &&
            asset.site.client.is_active &&
            !asset.site.client.entered_in_error_at,
    );
    const activeTechnicians = technicians.filter(
        (technician) => technician.is_active && !technician.entered_in_error_at,
    );
    const addDisabled =
        activeAssets.length === 0 || activeTechnicians.length === 0;
    const siteOptions = Array.from(
        new Map(assets.map((asset) => [asset.site.id, asset.site])).values(),
    ).sort((a, b) =>
        `${a.client.company_name} ${a.site_name}`.localeCompare(
            `${b.client.company_name} ${b.site_name}`,
        ),
    );
    return (
        <RecordPage
            title="Inspections"
            description="Log and review fire safety inspections"
            noun="inspection"
            icon={ClipboardCheck}
            addDisabled={addDisabled}
            addHint={
                activeAssets.length === 0
                    ? 'Add active equipment at an active site before logging an inspection.'
                    : 'Add an active technician before logging an inspection.'
            }
            emptyDescription="Log your first inspection to start tracking asset compliance."
            records={records}
            filters={filters}
            routes={{ index, store, update, markError, restoreError }}
            mistake={{
                isEnteredInError: (inspection) =>
                    inspection.entered_in_error_at !== null,
            }}
            searchPlaceholder="Search asset, site, client, technician, or notes..."
            dateLabel="Inspection date range"
            statusKey="result"
            statusOptions={[
                { value: 'pass', label: 'Pass' },
                { value: 'fail', label: 'Fail' },
                { value: 'needs_repair', label: 'Needs repair' },
            ]}
            extraFilters={[
                {
                    key: 'site_id',
                    label: 'Site',
                    options: [
                        { value: 'all', label: 'All sites' },
                        ...siteOptions.map((site) => ({
                            value: String(site.id),
                            label: `${site.client.company_name} > ${site.site_name}`,
                        })),
                    ],
                },
                {
                    key: 'technician_id',
                    label: 'Technician',
                    options: [
                        { value: 'all', label: 'All technicians' },
                        ...technicians.map((technician) => ({
                            value: String(technician.id),
                            label: technician.full_name,
                        })),
                    ],
                },
                {
                    key: 'sort',
                    label: 'Sort',
                    options: [
                        { value: 'all', label: 'Newest first' },
                        { value: 'oldest', label: 'Oldest first' },
                    ],
                },
            ]}
            addLabel="Log inspection"
            createTitle="Log inspection"
            fileUpload={{
                kind: 'photo',
                title: 'Inspection Photos',
                files: (inspection) => inspection.photos,
            }}
            columns={[
                {
                    key: 'asset',
                    header: 'Asset',
                    className: 'max-w-56 truncate',
                    render: (inspection) =>
                        `${inspection.asset.serial_number || 'No S/N'} — ${inspection.asset.location_desc || '—'}`,
                },
                {
                    key: 'technician',
                    header: 'Technician',
                    render: (inspection) =>
                        inspection.technician?.full_name || 'Unassigned',
                },
                {
                    key: 'site',
                    header: 'Site',
                    className: 'max-w-48 truncate',
                    render: (inspection) =>
                        `${inspection.asset.site.client.company_name} > ${inspection.asset.site.site_name}`,
                },
                {
                    key: 'date',
                    header: 'Date',
                    render: (inspection) => inspection.inspection_date,
                },
                {
                    key: 'result',
                    header: 'Result',
                    render: (inspection) => (
                        <ResultBadge result={inspection.result} />
                    ),
                },
                {
                    key: 'notes',
                    header: 'Notes',
                    className: 'max-w-52 truncate',
                    render: (inspection) => inspection.notes || '—',
                },
            ]}
            viewContent={(inspection) => (
                <div className="space-y-4">
                    {inspection.next_due_date && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <ViewField
                                label="Next due date"
                                value={inspection.next_due_date}
                            />
                        </div>
                    )}
                    <InspectionPhotos
                        inspectionId={inspection.id}
                        photos={inspection.photos}
                    />
                </div>
            )}
            defaults={{
                asset_id: '',
                technician_id: '',
                inspection_date: today,
                result: 'pass',
                notes: '',
                next_due_date: '',
            }}
            fields={(editing) => [
                {
                    name: 'asset_id',
                    label: 'Asset',
                    type: 'select',
                    required: true,
                    options: assets
                        .filter(
                            (asset) =>
                                (asset.status === 'active' &&
                                    !asset.entered_in_error_at &&
                                    asset.site.is_active &&
                                    !asset.site.entered_in_error_at &&
                                    asset.site.client.is_active &&
                                    !asset.site.client.entered_in_error_at) ||
                                asset.id === editing?.asset_id,
                        )
                        .map((asset) => ({
                            value: String(asset.id),
                            label: `${asset.serial_number || 'No S/N'} — ${asset.location_desc || asset.site.site_name}${asset.status !== 'active' || !asset.site.is_active || !asset.site.client.is_active ? ' (Inactive)' : ''}`,
                        })),
                },
                {
                    name: 'technician_id',
                    label: 'Technician',
                    type: 'select',
                    required: true,
                    options: technicians
                        .filter(
                            (technician) =>
                                (technician.is_active &&
                                    !technician.entered_in_error_at) ||
                                technician.id === editing?.technician_id,
                        )
                        .map((technician) => ({
                            value: String(technician.id),
                            label: `${technician.full_name}${technician.is_active ? '' : ' (Inactive)'}`,
                        })),
                },
                {
                    name: 'inspection_date',
                    label: 'Date',
                    type: 'date',
                    required: true,
                },
                {
                    name: 'result',
                    label: 'Result',
                    type: 'select',
                    required: true,
                    options: [
                        { value: 'pass', label: 'Pass' },
                        { value: 'fail', label: 'Fail' },
                        { value: 'needs_repair', label: 'Needs repair' },
                    ],
                },
                {
                    name: 'notes',
                    label: 'Notes',
                    type: 'textarea',
                    maxLength: 10000,
                },
                {
                    name: 'next_due_date',
                    label: 'Next inspection date',
                    type: 'date',
                    afterOrEqual: 'inspection_date',
                    description: (values) => {
                        const asset = assets.find(
                            (option) => String(option.id) === values.asset_id,
                        );
                        if (!asset) {
                            return 'Choose an asset to see its normal inspection interval.';
                        }
                        const interval =
                            asset.asset_type.default_interval_months;
                        if (!interval || interval < 1) {
                            return 'This asset type has no inspection interval. Enter the next date manually.';
                        }
                        const expected =
                            values.inspection_date === today
                                ? asset?.suggested_next_due_date
                                : null;
                        return `${expected ? `Expected: ${expected}. ` : ''}The normal interval is ${interval} ${interval === 1 ? 'month' : 'months'} after the inspection date. Leave blank to calculate automatically, or enter a date to override it.`;
                    },
                },
            ]}
        />
    );
}

Inspections.layout = {
    breadcrumbs: [{ title: 'Inspections', href: index() }],
};
