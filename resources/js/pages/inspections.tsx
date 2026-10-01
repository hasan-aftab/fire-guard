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
import { ResultBadge } from '@/components/status-badge';
import { ViewField } from '@/components/view-modal';
import type {
    Inspection,
    InspectionAssetOption,
    Paginated,
    Technician,
    TrackerFilters,
} from '@/types/fire-tracker';

function safePhotoUrl(value: string): string | null {
    try {
        const url = new URL(value);
        return url.protocol === 'https:' || url.protocol === 'http:'
            ? url.href
            : null;
    } catch {
        return null;
    }
}

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
            searchPlaceholder="Search by asset, technician, or notes..."
            dateLabel="Inspection date range"
            statusKey="result"
            statusOptions={[
                { value: 'pass', label: 'Pass' },
                { value: 'fail', label: 'Fail' },
                { value: 'needs_repair', label: 'Needs repair' },
            ]}
            addLabel="Log inspection"
            createTitle="Log inspection"
            columns={[
                {
                    key: 'asset',
                    header: 'Asset',
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
            viewContent={(inspection) => {
                const photoUrl = inspection.photo_url
                    ? safePhotoUrl(inspection.photo_url)
                    : null;

                return inspection.next_due_date || inspection.photo_url ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {inspection.next_due_date && (
                            <ViewField
                                label="Next due date"
                                value={inspection.next_due_date}
                            />
                        )}
                        {inspection.photo_url && (
                            <ViewField
                                label="Photo URL"
                                value={
                                    photoUrl ? (
                                        <a
                                            href={photoUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="break-all underline underline-offset-4"
                                        >
                                            {inspection.photo_url}
                                        </a>
                                    ) : (
                                        <span className="break-all">
                                            {inspection.photo_url}
                                        </span>
                                    )
                                }
                            />
                        )}
                    </div>
                ) : null;
            }}
            defaults={{
                asset_id: String(activeAssets[0]?.id ?? ''),
                technician_id: String(activeTechnicians[0]?.id ?? ''),
                inspection_date: today,
                result: 'pass',
                notes: '',
                next_due_date: '',
                photo_url: '',
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
                    label: 'Next due date',
                    type: 'date',
                    afterOrEqual: 'inspection_date',
                    description:
                        'Saved on this inspection. Update the asset schedule on the Assets page.',
                },
                {
                    name: 'photo_url',
                    label: 'Photo URL',
                    type: 'url',
                    maxLength: 2000,
                    description: 'Link to photo evidence for this inspection.',
                },
            ]}
        />
    );
}
