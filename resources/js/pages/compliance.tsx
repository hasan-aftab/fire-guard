import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import {
    index,
    store,
    update,
    markError,
    restoreError,
} from '@/actions/App/Http/Controllers/CertificateController';
import { index as certificateTypesIndex } from '@/actions/App/Http/Controllers/CertificateTypeController';
import { RecordPage } from '@/components/record-page';
import { CertificateDocument } from '@/components/managed-file-panel';
import { StatusBadge } from '@/components/status-badge';
import { ViewField } from '@/components/view-modal';
import { index as clientsIndex } from '@/routes/clients';
import type {
    Asset,
    Certificate,
    CertificateType,
    CertificateSite,
    Paginated,
    TrackerFilters,
} from '@/types/fire-tracker';

export default function Compliance({
    records,
    sites,
    certificateTypes,
    assets,
    filters,
}: {
    records: Paginated<Certificate>;
    sites: CertificateSite[];
    certificateTypes: CertificateType[];
    assets: Pick<
        Asset,
        | 'id'
        | 'site_id'
        | 'serial_number'
        | 'location_desc'
        | 'status'
        | 'entered_in_error_at'
    >[];
    filters: TrackerFilters;
}) {
    const isAdmin = usePage().props.auth.user.role === 'admin';
    const activeSites = sites.filter(
        (site) =>
            site.is_active &&
            site.client.is_active &&
            !site.entered_in_error_at &&
            !site.client.entered_in_error_at,
    );
    const activeTypes = certificateTypes.filter((type) => type.is_active);

    return (
        <RecordPage
            title="Compliance Certificates"
            description="Track certificate expiry and compliance status"
            noun="certificate"
            fileUpload={{
                kind: 'document',
                title: 'Certificate Document',
                files: (certificate) =>
                    certificate.document ? [certificate.document] : [],
            }}
            icon={ShieldCheck}
            addDisabled={activeSites.length === 0 || activeTypes.length === 0}
            addHint={
                activeSites.length === 0 ? (
                    <>
                        <Link
                            href={clientsIndex()}
                            className="font-medium text-primary underline underline-offset-2"
                        >
                            Add a site in Clients &amp; Sites
                        </Link>{' '}
                        before creating a certificate.
                    </>
                ) : isAdmin ? (
                    <>
                        <Link
                            href={certificateTypesIndex()}
                            className="font-medium text-primary underline underline-offset-2"
                        >
                            Set up a certificate type
                        </Link>{' '}
                        before creating a certificate.
                    </>
                ) : (
                    'Ask an administrator to activate a certificate type before creating a certificate.'
                )
            }
            emptyDescription="Add compliance certificates to track expiry dates and stay compliant."
            records={records}
            filters={filters}
            routes={{ index, store, update, markError, restoreError }}
            mistake={{
                isEnteredInError: (certificate) =>
                    certificate.entered_in_error_at !== null,
            }}
            searchPlaceholder="Search type, site, issuer, or equipment..."
            extraFilters={[
                {
                    key: 'site_id',
                    label: 'Site',
                    options: [
                        { value: 'all', label: 'All sites' },
                        ...sites.map((site) => ({
                            value: String(site.id),
                            label: `${site.client.company_name} > ${site.site_name}`,
                        })),
                    ],
                },
                {
                    key: 'certificate_type_id',
                    label: 'Certificate type',
                    options: [
                        { value: 'all', label: 'All types' },
                        ...certificateTypes.map((type) => ({
                            value: String(type.id),
                            label: type.name,
                        })),
                    ],
                },
                {
                    key: 'sort',
                    label: 'Sort',
                    options: [
                        { value: 'all', label: 'Earliest expiry' },
                        { value: 'due_latest', label: 'Latest expiry' },
                    ],
                },
            ]}
            dateLabel="Expiry date range"
            issueDates
            statusOptions={[
                { value: 'overdue', label: 'Expired' },
                { value: 'due_soon', label: 'Expiring soon' },
                { value: 'ok', label: 'Valid' },
            ]}
            columns={[
                {
                    key: 'type',
                    header: 'Certificate',
                    render: (certificate) => (
                        <span className="text-sm font-medium">
                            {certificate.certificate_type.name}
                        </span>
                    ),
                },
                {
                    key: 'site',
                    header: 'Site',
                    className: 'text-sm text-muted-foreground',
                    render: (certificate) =>
                        `${certificate.site.client.company_name} > ${certificate.site.site_name}`,
                },
                {
                    key: 'issue',
                    header: 'Issue date',
                    className: 'w-[120px] text-sm text-muted-foreground',
                    render: (certificate) => certificate.issue_date,
                },
                {
                    key: 'expiry',
                    header: 'Expiry',
                    className: 'w-[120px] text-sm text-muted-foreground',
                    render: (certificate) => certificate.expiry_date,
                },
                {
                    key: 'status',
                    header: 'Status',
                    className: 'w-[100px]',
                    render: (certificate) => (
                        <StatusBadge
                            status={certificate.status}
                            date={certificate.expiry_date}
                            showDate={false}
                        />
                    ),
                },
            ]}
            viewContent={(certificate) => (
                <div className="space-y-4">
                    {certificate.issued_by && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <ViewField
                                label="Issued by"
                                value={certificate.issued_by}
                            />
                        </div>
                    )}
                    {certificate.asset && (
                        <ViewField
                            label="Equipment"
                            value={`${certificate.asset.serial_number || 'No S/N'} — ${certificate.asset.location_desc || 'No location'}`}
                        />
                    )}
                    <CertificateDocument
                        certificateId={certificate.id}
                        document={certificate.document}
                    />
                </div>
            )}
            defaults={{
                site_id: String(activeSites[0]?.id ?? ''),
                certificate_type_id: String(activeTypes[0]?.id ?? ''),
                asset_id: '',
                issue_date: '',
                expiry_date: '',
                issued_by: '',
            }}
            fields={(editing) => [
                {
                    name: 'site_id',
                    label: 'Site',
                    type: 'select',
                    required: true,
                    resetFieldsOnChange: ['asset_id'],
                    options: sites
                        .filter(
                            (site) =>
                                (site.is_active &&
                                    site.client.is_active &&
                                    !site.entered_in_error_at &&
                                    !site.client.entered_in_error_at) ||
                                site.id === editing?.site_id,
                        )
                        .map((site) => ({
                            value: String(site.id),
                            label: `${site.client.company_name} > ${site.site_name}${site.entered_in_error_at || site.client.entered_in_error_at ? ' (Entered in error)' : !site.is_active || !site.client.is_active ? ' (Inactive)' : ''}`,
                        })),
                },
                {
                    name: 'asset_id',
                    label: 'Equipment',
                    type: 'select',
                    emptyLabel: 'No linked equipment',
                    description:
                        'Optional. Choose equipment at the selected site.',
                    options: (values) =>
                        assets
                            .filter(
                                (asset) =>
                                    String(asset.site_id) === values.site_id &&
                                    ((asset.status === 'active' &&
                                        !asset.entered_in_error_at) ||
                                        asset.id === editing?.asset_id),
                            )
                            .map((asset) => ({
                                value: String(asset.id),
                                label: `${asset.serial_number || 'No S/N'} — ${asset.location_desc || 'No location'}${asset.entered_in_error_at ? ' (Entered in error)' : asset.status !== 'active' ? ' (Inactive)' : ''}`,
                            })),
                },
                {
                    name: 'certificate_type_id',
                    label: 'Certificate type',
                    type: 'select',
                    required: true,
                    options: certificateTypes
                        .filter(
                            (type) =>
                                type.is_active ||
                                type.id === editing?.certificate_type_id,
                        )
                        .map((type) => ({
                            value: String(type.id),
                            label: `${type.name}${type.is_active ? '' : ' (Inactive)'}`,
                        })),
                },
                {
                    name: 'issue_date',
                    label: 'Issue date',
                    type: 'date',
                    required: true,
                },
                {
                    name: 'expiry_date',
                    label: 'Expiry date',
                    type: 'date',
                    required: true,
                    afterOrEqual: 'issue_date',
                },
                { name: 'issued_by', label: 'Issued by', maxLength: 255 },
            ]}
        />
    );
}

Compliance.layout = {
    breadcrumbs: [{ title: 'Compliance', href: index() }],
};
