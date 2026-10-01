import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import {
    index,
    store,
    update,
    destroy,
} from '@/actions/App/Http/Controllers/CertificateController';
import { RecordPage } from '@/components/record-page';
import { StatusBadge } from '@/components/status-badge';
import { ViewField } from '@/components/view-modal';
import { index as clientsIndex } from '@/routes/clients';
import type {
    Certificate,
    CertificateSite,
    Paginated,
    TrackerFilters,
} from '@/types/fire-tracker';

function safeDocumentUrl(value: string): string | null {
    try {
        const url = new URL(value);

        return url.protocol === 'https:' || url.protocol === 'http:'
            ? url.href
            : null;
    } catch {
        return null;
    }
}

export default function Compliance({
    records,
    sites,
    filters,
}: {
    records: Paginated<Certificate>;
    sites: CertificateSite[];
    filters: TrackerFilters;
}) {
    const { today } = usePage().props;

    return (
        <RecordPage
            title="Compliance Certificates"
            description="Track certificate expiry and compliance status"
            noun="certificate"
            icon={ShieldCheck}
            addDisabled={sites.length === 0}
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
            routes={{ index, store, update, destroy }}
            searchPlaceholder="Search by type or site..."
            dateLabel="Expiry date range"
            issueDates
            statusOptions={[
                { value: 'overdue', label: 'Overdue' },
                { value: 'due_soon', label: 'Due soon' },
                { value: 'ok', label: 'OK' },
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
                            date={certificate.expiry_date}
                            showDate={false}
                            asOfDate={today}
                        />
                    ),
                },
            ]}
            viewContent={(certificate) => {
                const documentUrl = certificate.document_url
                    ? safeDocumentUrl(certificate.document_url)
                    : null;

                return (
                    (certificate.issued_by || certificate.document_url) && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {certificate.issued_by && (
                                <ViewField
                                    label="Issued by"
                                    value={certificate.issued_by}
                                />
                            )}
                            {certificate.document_url && (
                                <ViewField
                                    label="Document"
                                    value={
                                        documentUrl ? (
                                            <a
                                                href={documentUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="underline underline-offset-2 hover:text-primary"
                                            >
                                                Open document
                                            </a>
                                        ) : (
                                            'Unavailable'
                                        )
                                    }
                                />
                            )}
                        </div>
                    )
                );
            }}
            defaults={{
                site_id: String(sites[0]?.id ?? ''),
                certificate_type: '',
                issue_date: '',
                expiry_date: '',
                issued_by: '',
                document_url: '',
            }}
            fields={[
                {
                    name: 'site_id',
                    label: 'Site',
                    type: 'select',
                    required: true,
                    options: sites.map((site) => ({
                        value: String(site.id),
                        label: `${site.client.company_name} > ${site.site_name}`,
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
                {
                    name: 'document_url',
                    label: 'Document URL',
                    type: 'url',
                    maxLength: 2000,
                    placeholder: 'https://example.com/certificate.pdf',
                },
            ]}
        />
    );
}
