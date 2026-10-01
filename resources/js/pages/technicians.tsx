import { Users } from 'lucide-react';
import {
    index,
    store,
    update,
    destroy,
    restore,
} from '@/actions/App/Http/Controllers/TechnicianController';
import { RecordPage } from '@/components/record-page';
import { Badge } from '@/components/ui/badge';
import type {
    Paginated,
    Technician,
    TrackerFilters,
} from '@/types/fire-tracker';

export default function Technicians({
    records,
    filters,
}: {
    records: Paginated<Technician>;
    filters: TrackerFilters;
}) {
    return (
        <RecordPage
            title="Technicians"
            description="Manage fire safety technicians and their licences"
            noun="technician"
            icon={Users}
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
            routes={{ index, store, update, destroy, restore }}
            searchPlaceholder="Search technicians..."
            statusOptions={[
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ]}
            columns={[
                {
                    key: 'name',
                    header: 'Name',
                    render: (technician) => technician.full_name,
                },
                {
                    key: 'phone',
                    header: 'Phone',
                    render: (technician) => technician.phone || '—',
                },
                {
                    key: 'email',
                    header: 'Email',
                    render: (technician) => technician.email || '—',
                },
                {
                    key: 'license',
                    header: 'Licence',
                    render: (technician) => technician.license_number || '—',
                },
                {
                    key: 'expiry',
                    header: 'Licence expiry',
                    render: (technician) => technician.license_expiry || '—',
                },
                {
                    key: 'active',
                    header: 'Status',
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
            ]}
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
