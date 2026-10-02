import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import {
    index,
    store,
    update,
    markError,
    restoreError,
} from '@/actions/App/Http/Controllers/CertificateController';
import { RecordPage } from '@/components/record-page';
import { CertificateDocument } from '@/components/managed-file-panel';
import { StatusBadge } from '@/components/status-badge';
import { ViewField } from '@/components/view-modal';
import { index as clientsIndex } from '@/routes/clients';
import type {
    Certificate,
    CertificateSite,
    Paginated,
    TrackerFilters,
} from '@/types/fire-tracker';

export default function Compliance({
    records,
    sites,
    filters,
}: {
    records: Paginated<Certificate>;
    sites: CertificateSite[];
    filters: TrackerFilters;
}) {
    const activeSites = sites.filter(
        (site) =>
            site.is_active &&
            site.client.is_active &&
            !site.entered_in_error_at &&
            !site.client.entered_in_error_at,
    );

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
            addDisabled={activeSites.length === 0}
            addHint={
                <>
                    <Link
                        href={clientsIndex()}
                        className="font-medium text-primary underline underline-offset-2"
                    >
                        Add a site in Clients &amp; Sites
                    </Link>{' '}
                    before creating a certificate.
                </>
            }
            emptyDescription="Add compliance certificates to track expiry dates and stay compliant."
            records={records}
            filters={filters}
            routes={{ index, store, update, markError, restoreError }}
            mistake={{
                isEnteredInError: (certificate) =>
                    certificate.entered_in_error_at !== null,
            }}
            searchPlaceholder="Search by type or site..."
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
                            {certificate.certificate_type}
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
                    <CertificateDocument
                        certificateId={certificate.id}
                        document={certificate.document}
                    />
                </div>
            )}
            defaults={{
                site_id: String(activeSites[0]?.id ?? ''),
                certificate_type: '',
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
                            label: `${site.client.company_name} > ${site.site_name}${!site.is_active || !site.client.is_active ? ' (Inactive)' : ''}`,
                        })),
                },
                {
                    name: 'certificate_type',
                    label: 'Certificate type',
                    required: true,
                    maxLength: 255,
                    placeholder: 'e.g. Annual Fire Safety Statement',
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
