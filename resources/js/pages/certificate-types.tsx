import { Files } from 'lucide-react';
import {
    index,
    store,
    update,
    destroy,
    restore,
} from '@/actions/App/Http/Controllers/CertificateTypeController';
import { RecordPage } from '@/components/record-page';
import { Badge } from '@/components/ui/badge';
import type {
    CertificateType,
    Paginated,
    TrackerFilters,
} from '@/types/fire-tracker';

type ManagedCertificateType = CertificateType & { certificates_count: number };

export default function CertificateTypes({
    records,
    filters,
}: {
    records: Paginated<ManagedCertificateType>;
    filters: TrackerFilters;
}) {
    return (
        <RecordPage
            title="Certificate Types"
            description="Manage certificate classifications used by compliance records"
            noun="certificate type"
            icon={Files}
            records={records}
            filters={filters}
            routes={{ index, store, update, destroy, restore }}
            searchPlaceholder="Search certificate types..."
            emptyDescription="Add a certificate type to make it available for new certificates."
            columns={[
                { key: 'name', header: 'Name', render: (type) => type.name },
                {
                    key: 'used',
                    header: 'Certificates',
                    render: (type) => type.certificates_count,
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
            defaults={{ name: '' }}
            fields={[
                { name: 'name', label: 'Name', required: true, maxLength: 255 },
            ]}
            archive={{
                isArchived: (type) => !type.is_active,
                action: 'Deactivate',
                restoreAction: 'Activate',
                description: (type) =>
                    `This type is used by ${type.certificates_count} certificate records. Existing certificates will keep it, but it will no longer be available for new certificates.`,
                restoreDescription:
                    'Make this type available for new certificates again?',
            }}
        />
    );
}

CertificateTypes.layout = {
    breadcrumbs: [{ title: 'Certificate Types', href: index() }],
};
