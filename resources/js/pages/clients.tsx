import { router } from '@inertiajs/react';
import { Building2, Eye, MapPin, Pencil, Phone, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    index,
    store,
    update,
    destroy,
} from '@/actions/App/Http/Controllers/ClientController';
import {
    store as storeSite,
    update as updateSite,
    destroy as destroySite,
} from '@/actions/App/Http/Controllers/SiteController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { RecordForm, recordValues } from '@/components/record-form';
import { RecordPage } from '@/components/record-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { ViewField, ViewModal } from '@/components/view-modal';
import type {
    Client,
    Paginated,
    Site,
    TrackerFilters,
} from '@/types/fire-tracker';

export default function Clients({
    records,
    filters,
}: {
    records: Paginated<Client>;
    filters: TrackerFilters;
}) {
    const [clientId, setClientId] = useState<number | null>(null);
    const client = records.data.find((record) => record.id === clientId);
    const [editingSite, setEditingSite] = useState<Site | null>(null);
    const [viewingSite, setViewingSite] = useState<Site | null>(null);
    const [deletingSite, setDeletingSite] = useState<Site | null>(null);
    const [deletePending, setDeletePending] = useState(false);
    const siteDefaults = {
        client_id: String(clientId ?? ''),
        site_name: '',
        address: '',
        suburb_region: '',
        access_notes: '',
        site_contact: '',
        site_phone: '',
    };
    const manageSites = (record: Client) => {
        setClientId(record.id);
        setEditingSite(null);
        setViewingSite(null);
        setDeletingSite(null);
    };
    const deleteSelectedSite = () => {
        if (!deletingSite) return;
        setDeletePending(true);
        router.delete(destroySite(deletingSite.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingSite(null);
                setEditingSite(null);
            },
            onFinish: () => setDeletePending(false),
        });
    };
    return (
        <>
            <RecordPage
                title="Clients & Sites"
                description="Manage your clients and their fire safety sites"
                noun="client"
                icon={Building2}
                records={records}
                filters={filters}
                routes={{ index, store, update, destroy }}
                searchPlaceholder="Search clients..."
                columns={[
                    {
                        key: 'company',
                        header: 'Company',
                        render: (record) => (
                            <span className="font-medium">
                                {record.company_name}
                            </span>
                        ),
                    },
                    {
                        key: 'contact',
                        header: 'Contact',
                        className: 'text-sm text-muted-foreground',
                        render: (record) => record.contact_name || '—',
                    },
                    {
                        key: 'phone',
                        header: 'Phone',
                        className: 'text-sm text-muted-foreground',
                        render: (record) => (
                            <span className="flex items-center gap-1.5">
                                <Phone
                                    className="size-3 text-muted-foreground/50"
                                    aria-hidden="true"
                                />
                                {record.phone || '—'}
                            </span>
                        ),
                    },
                    {
                        key: 'sites',
                        header: 'Sites',
                        render: (record) => (
                            <Badge
                                variant="secondary"
                                className="text-xs font-normal"
                            >
                                {record.sites_count} site
                                {record.sites_count === 1 ? '' : 's'}
                            </Badge>
                        ),
                    },
                ]}
                defaults={{
                    company_name: '',
                    contact_name: '',
                    phone: '',
                    email: '',
                    billing_address: '',
                    notes: '',
                    is_active: '1',
                }}
                fields={[
                    {
                        name: 'company_name',
                        label: 'Company name',
                        required: true,
                        placeholder: 'Company name',
                    },
                    {
                        name: 'contact_name',
                        label: 'Contact name',
                        placeholder: 'Contact name',
                    },
                    {
                        name: 'phone',
                        label: 'Phone',
                        type: 'tel',
                        placeholder: 'Phone number',
                    },
                    {
                        name: 'email',
                        label: 'Email',
                        type: 'email',
                        placeholder: 'Email address',
                    },
                    {
                        name: 'billing_address',
                        label: 'Billing address',
                        type: 'textarea',
                    },
                    { name: 'notes', label: 'Notes', type: 'textarea' },
                    {
                        name: 'is_active',
                        label: 'Active client',
                        type: 'checkbox',
                    },
                ]}
                rowActions={(record) => (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => manageSites(record)}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <MapPin data-icon="inline-start" />
                        Sites
                    </Button>
                )}
                viewContent={(record) => (
                    <>
                        <ViewField label="Email" value={record.email} />
                        <ViewField
                            label="Billing address"
                            value={record.billing_address}
                        />
                        <ViewField label="Notes" value={record.notes} />
                        <Separator />
                        <h3 className="text-sm font-medium">
                            Sites ({record.sites_count})
                        </h3>
                        {record.sites.map((site) => (
                            <div
                                key={site.id}
                                className="flex items-start gap-3 rounded-lg border bg-muted/30 p-3"
                            >
                                <MapPin
                                    className="mt-0.5 size-4 shrink-0 text-muted-foreground/60"
                                    aria-hidden="true"
                                />
                                <div className="min-w-0 space-y-0.5">
                                    <p className="text-sm font-medium break-words">
                                        {site.site_name}
                                    </p>
                                    <p className="text-xs break-words text-muted-foreground">
                                        {site.address}
                                    </p>
                                    {site.suburb_region && (
                                        <p className="text-xs break-words text-muted-foreground">
                                            {site.suburb_region}
                                        </p>
                                    )}
                                    {site.access_notes && (
                                        <p className="text-xs break-words text-muted-foreground italic">
                                            Note: {site.access_notes}
                                        </p>
                                    )}
                                </div>
                            </div>
                        ))}
                        {!record.sites.length && (
                            <p className="text-sm text-muted-foreground">
                                No sites added yet.
                            </p>
                        )}
                    </>
                )}
            />
            <Sheet
                open={!!client}
                onOpenChange={(open) => {
                    if (!open) setClientId(null);
                }}
            >
                <SheetContent className="w-full sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Manage sites</SheetTitle>
                        <SheetDescription>
                            Sites for {client?.company_name}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4">
                        {client && (
                            <>
                                <div className="rounded-lg border bg-muted/40 p-4">
                                    <h3 className="mb-4 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {editingSite
                                            ? 'Edit site'
                                            : 'Add new site'}
                                    </h3>
                                    <RecordForm
                                        key={`${client.id}-${editingSite?.id ?? 'new'}`}
                                        initialValues={
                                            editingSite
                                                ? recordValues(
                                                      editingSite,
                                                      siteDefaults,
                                                  )
                                                : siteDefaults
                                        }
                                        action={
                                            editingSite
                                                ? updateSite(editingSite.id)
                                                : storeSite()
                                        }
                                        saveLabel={
                                            editingSite
                                                ? 'Save site'
                                                : 'Add site'
                                        }
                                        onSuccess={() => setEditingSite(null)}
                                        onCancel={() =>
                                            editingSite
                                                ? setEditingSite(null)
                                                : setClientId(null)
                                        }
                                        fields={[
                                            {
                                                name: 'site_name',
                                                label: 'Site name',
                                                required: true,
                                                placeholder:
                                                    'e.g. Building A - Main Entrance',
                                            },
                                            {
                                                name: 'address',
                                                label: 'Address',
                                                required: true,
                                                placeholder:
                                                    'e.g. Section 45, Badili',
                                            },
                                            {
                                                name: 'suburb_region',
                                                label: 'Suburb / Region',
                                                placeholder:
                                                    'e.g. Port Moresby, NCD',
                                            },
                                            {
                                                name: 'access_notes',
                                                label: 'Access notes',
                                                type: 'textarea',
                                                placeholder:
                                                    'Entrance details or arrival instructions',
                                            },
                                            {
                                                name: 'site_contact',
                                                label: 'Site contact',
                                            },
                                            {
                                                name: 'site_phone',
                                                label: 'Site phone',
                                                type: 'tel',
                                            },
                                        ]}
                                    />
                                </div>
                                <h3 className="text-xs font-medium text-muted-foreground uppercase">
                                    Existing sites ({client.sites_count})
                                </h3>
                                {client.sites.length === 0 && (
                                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                        No sites added yet. Use the form above
                                        to add the first site.
                                    </p>
                                )}
                                {client.sites.map((site) => (
                                    <div
                                        key={site.id}
                                        className="flex items-center justify-between gap-2 rounded-lg border bg-background p-3"
                                    >
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            <MapPin
                                                className="size-4 shrink-0 text-muted-foreground/50"
                                                aria-hidden="true"
                                            />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {site.site_name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {site.address}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`View ${site.site_name}`}
                                                onClick={() =>
                                                    setViewingSite(site)
                                                }
                                            >
                                                <Eye />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${site.site_name}`}
                                                onClick={() =>
                                                    setEditingSite(site)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Delete ${site.site_name}`}
                                                onClick={() =>
                                                    setDeletingSite(site)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
            <ConfirmDialog
                open={!!deletingSite}
                onOpenChange={(open) => {
                    if (!open) setDeletingSite(null);
                }}
                title="Delete site?"
                description="This will permanently delete this site and its assets, inspections, and certificates. This action cannot be undone."
                onConfirm={deleteSelectedSite}
                loading={deletePending}
            />
            <ViewModal
                open={!!viewingSite}
                onClose={() => setViewingSite(null)}
                title="Site Details"
            >
                {viewingSite && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <ViewField
                            label="Site Name"
                            value={viewingSite.site_name}
                        />
                        <ViewField
                            label="Company"
                            value={client?.company_name}
                        />
                        <ViewField
                            label="Address"
                            value={viewingSite.address}
                        />
                        <ViewField
                            label="Suburb / Region"
                            value={viewingSite.suburb_region}
                        />
                        <ViewField
                            label="Access Notes"
                            value={viewingSite.access_notes}
                        />
                        <ViewField
                            label="Site Contact"
                            value={viewingSite.site_contact}
                        />
                        <ViewField
                            label="Site Phone"
                            value={viewingSite.site_phone}
                        />
                    </div>
                )}
            </ViewModal>
        </>
    );
}
