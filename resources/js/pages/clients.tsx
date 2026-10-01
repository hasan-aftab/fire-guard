import { router } from '@inertiajs/react';
import { Building2, Eye, MapPin, Pencil, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
import { Pagination } from '@/components/pagination';
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
    selectedClient,
    selectedSites,
}: {
    records: Paginated<Client>;
    filters: TrackerFilters;
    selectedClient: Pick<Client, 'id' | 'company_name'> | null;
    selectedSites: Paginated<Site> | null;
}) {
    const [siteLoading, setSiteLoading] = useState(false);
    const sitesTrigger = useRef<HTMLButtonElement>(null);
    const sitesTriggerClientId = useRef<number | null>(null);
    const siteSheet = useRef<HTMLDivElement>(null);
    const addSiteTrigger = useRef<HTMLButtonElement>(null);
    const siteEditTrigger = useRef<HTMLButtonElement>(null);
    const siteViewTrigger = useRef<HTMLButtonElement>(null);
    const siteDeleteTrigger = useRef<HTMLButtonElement>(null);
    const [editingSite, setEditingSite] = useState<Site | null>(null);
    const [addingSite, setAddingSite] = useState(false);
    const [viewingSite, setViewingSite] = useState<Site | null>(null);
    const [deletingSite, setDeletingSite] = useState<Site | null>(null);
    const [deletePending, setDeletePending] = useState(false);
    useEffect(() => {
        if (editingSite || addingSite) {
            siteSheet.current
                ?.querySelector<HTMLInputElement>('#record-site_name')
                ?.focus();
        }
    }, [editingSite, addingSite]);
    const siteDefaults = {
        client_id: String(selectedClient?.id ?? ''),
        site_name: '',
        address: '',
        suburb_region: '',
        access_notes: '',
        site_contact: '',
        site_phone: '',
    };
    const openSites = (clientId: number, sitePage = 1) => {
        router.get(
            index().url,
            {
                ...filters,
                page: records.current_page,
                site_client: clientId,
                site_page: sitePage,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setSiteLoading(true),
                onFinish: () => setSiteLoading(false),
            },
        );
    };
    const manageSites = (record: Client, trigger?: HTMLButtonElement) => {
        sitesTrigger.current =
            trigger ??
            document.querySelector<HTMLButtonElement>(
                `[data-client-sites-id="${record.id}"]`,
            );
        sitesTriggerClientId.current = record.id;
        setEditingSite(null);
        setAddingSite(false);
        setViewingSite(null);
        setDeletingSite(null);
        openSites(record.id);
    };
    const closeSites = () => {
        setEditingSite(null);
        setAddingSite(false);
        setViewingSite(null);
        setDeletingSite(null);
        router.get(
            index().url,
            { ...filters, page: records.current_page },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    requestAnimationFrame(() => {
                        const triggerId = sitesTriggerClientId.current;
                        const trigger = triggerId
                            ? document.querySelector<HTMLButtonElement>(
                                  `[data-client-sites-id="${triggerId}"]`,
                              )
                            : null;
                        (
                            trigger ??
                            document.querySelector<HTMLInputElement>(
                                'input[type="search"]',
                            )
                        )?.focus();
                    });
                },
            },
        );
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
                searchPlaceholder="Search clients, sites or addresses..."
                statusKey="active"
                statusOptions={[
                    { value: 'active', label: 'Active' },
                    { value: 'inactive', label: 'Inactive' },
                ]}
                extraFilters={[
                    {
                        key: 'site_presence',
                        label: 'Filter by site presence',
                        options: [
                            { value: 'all', label: 'All site counts' },
                            { value: 'with_sites', label: 'With sites' },
                            { value: 'without_sites', label: 'Without sites' },
                        ],
                    },
                    {
                        key: 'sort',
                        label: 'Sort clients',
                        options: [
                            { value: 'name', label: 'Name A–Z' },
                            { value: 'name_desc', label: 'Name Z–A' },
                            { value: 'most_sites', label: 'Most sites' },
                        ],
                    },
                ]}
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
                        render: (record) => record.phone || '—',
                    },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (record) => (
                            <Badge
                                variant={
                                    record.is_active ? 'secondary' : 'outline'
                                }
                            >
                                {record.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                        ),
                    },
                    {
                        key: 'sites',
                        header: 'Sites',
                        showInView: false,
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
                        maxLength: 255,
                        autoComplete: 'organization',
                    },
                    {
                        name: 'contact_name',
                        label: 'Contact name',
                        placeholder: 'Contact name',
                        maxLength: 255,
                        autoComplete: 'name',
                    },
                    {
                        name: 'phone',
                        label: 'Phone',
                        type: 'tel',
                        placeholder: 'Phone number',
                        description:
                            'Use 7 to 20 digits. Spaces, brackets and + are allowed.',
                        maxLength: 255,
                        autoComplete: 'tel',
                    },
                    {
                        name: 'email',
                        label: 'Email',
                        type: 'email',
                        placeholder: 'Email address',
                        maxLength: 255,
                        autoComplete: 'email',
                    },
                    {
                        name: 'billing_address',
                        label: 'Billing address',
                        type: 'textarea',
                        maxLength: 5000,
                        autoComplete: 'street-address',
                    },
                    {
                        name: 'notes',
                        label: 'Notes',
                        type: 'textarea',
                        maxLength: 10000,
                    },
                    {
                        name: 'is_active',
                        label: 'Active client',
                        type: 'checkbox',
                        description:
                            'Inactive clients remain available for historical records.',
                    },
                ]}
                rowActions={(record) => (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Manage sites for ${record.company_name}`}
                        data-client-sites-id={record.id}
                        onClick={(event) =>
                            manageSites(record, event.currentTarget)
                        }
                        className="size-7 text-muted-foreground hover:text-foreground"
                    >
                        <MapPin />
                    </Button>
                )}
                viewContent={(record, closeView) => (
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
                        {record.sites_count > 0 ? (
                            <ul className="space-y-2">
                                {record.sites?.map((site) => (
                                    <li
                                        key={site.id}
                                        className="rounded-lg border bg-muted/30 p-3"
                                    >
                                        <p className="text-sm font-medium break-words">
                                            {site.site_name}
                                        </p>
                                        <p className="text-xs break-words text-muted-foreground">
                                            {site.address}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No sites added yet.
                            </p>
                        )}
                        {record.sites_count > (record.sites?.length ?? 0) && (
                            <p className="text-xs text-muted-foreground">
                                Showing the first {record.sites?.length} sites.
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="self-start"
                            onClick={() => {
                                closeView(false);
                                manageSites(record);
                            }}
                        >
                            {record.sites_count > 0
                                ? 'Manage all sites'
                                : 'Add first site'}
                        </Button>
                    </>
                )}
            />
            <Sheet
                open={!!selectedClient}
                onOpenChange={(open) => {
                    if (!open) closeSites();
                }}
            >
                <SheetContent
                    ref={siteSheet}
                    className="w-full sm:max-w-lg"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        addSiteTrigger.current?.focus();
                    }}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        sitesTrigger.current?.focus();
                    }}
                >
                    <SheetHeader>
                        <SheetTitle>Manage sites</SheetTitle>
                        <SheetDescription>
                            Sites for {selectedClient?.company_name}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4">
                        {selectedClient && selectedSites && (
                            <>
                                <div className="flex items-center justify-between gap-3">
                                    <h3
                                        data-site-list-heading
                                        tabIndex={-1}
                                        className="text-xs font-medium text-muted-foreground uppercase"
                                    >
                                        Existing sites ({selectedSites.total})
                                    </h3>
                                    <Button
                                        ref={addSiteTrigger}
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            addingSite || editingSite !== null
                                        }
                                        onClick={() => {
                                            setEditingSite(null);
                                            setAddingSite(true);
                                        }}
                                    >
                                        Add site
                                    </Button>
                                </div>
                                {(addingSite || editingSite) && (
                                    <div className="rounded-lg border bg-muted/40 p-4">
                                        <h3 className="mb-4 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            {editingSite
                                                ? 'Edit site'
                                                : 'Add new site'}
                                        </h3>
                                        <RecordForm
                                            key={`${selectedClient.id}-${editingSite?.id ?? 'new'}`}
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
                                            onSuccess={() => {
                                                const trigger = editingSite
                                                    ? siteEditTrigger.current
                                                    : addSiteTrigger.current;
                                                setEditingSite(null);
                                                setAddingSite(false);
                                                requestAnimationFrame(() =>
                                                    trigger?.focus(),
                                                );
                                            }}
                                            onCancel={() => {
                                                const trigger = editingSite
                                                    ? siteEditTrigger.current
                                                    : addSiteTrigger.current;
                                                setEditingSite(null);
                                                setAddingSite(false);
                                                requestAnimationFrame(() =>
                                                    trigger?.focus(),
                                                );
                                            }}
                                            fields={[
                                                {
                                                    name: 'site_name',
                                                    label: 'Site name',
                                                    required: true,
                                                    placeholder:
                                                        'e.g. Building A - Main Entrance',
                                                    maxLength: 255,
                                                },
                                                {
                                                    name: 'address',
                                                    label: 'Address',
                                                    required: true,
                                                    type: 'textarea',
                                                    placeholder:
                                                        'e.g. Section 45, Badili',
                                                    maxLength: 5000,
                                                    autoComplete:
                                                        'street-address',
                                                },
                                                {
                                                    name: 'suburb_region',
                                                    label: 'Suburb / Region',
                                                    placeholder:
                                                        'e.g. Port Moresby, NCD',
                                                    maxLength: 255,
                                                },
                                                {
                                                    name: 'access_notes',
                                                    label: 'Access notes',
                                                    type: 'textarea',
                                                    placeholder:
                                                        'Entrance details or arrival instructions',
                                                    maxLength: 10000,
                                                },
                                                {
                                                    name: 'site_contact',
                                                    label: 'Site contact',
                                                    maxLength: 255,
                                                    autoComplete: 'name',
                                                },
                                                {
                                                    name: 'site_phone',
                                                    label: 'Site phone',
                                                    type: 'tel',
                                                    description:
                                                        'Use 7 to 20 digits. Spaces, brackets and + are allowed.',
                                                    maxLength: 255,
                                                    autoComplete: 'tel',
                                                },
                                            ]}
                                        />
                                    </div>
                                )}
                                {selectedSites.total === 0 && (
                                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                        No sites added yet. Use Add site to add
                                        the first location.
                                    </p>
                                )}
                                {selectedSites.data.map((site) => (
                                    <div
                                        key={site.id}
                                        className="flex flex-col gap-2 rounded-lg border bg-background p-3 sm:flex-row sm:items-center sm:justify-between"
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
                                        <div className="flex shrink-0 self-end sm:self-auto">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`View ${site.site_name}`}
                                                onClick={(event) => {
                                                    siteViewTrigger.current =
                                                        event.currentTarget;
                                                    setViewingSite(site);
                                                }}
                                            >
                                                <Eye />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${site.site_name}`}
                                                onClick={(event) => {
                                                    siteEditTrigger.current =
                                                        event.currentTarget;
                                                    setAddingSite(false);
                                                    setEditingSite(site);
                                                }}
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Delete ${site.site_name}`}
                                                onClick={(event) => {
                                                    siteDeleteTrigger.current =
                                                        event.currentTarget;
                                                    setDeletingSite(site);
                                                }}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                                {selectedSites.total >
                                    selectedSites.per_page && (
                                    <Pagination
                                        currentPage={selectedSites.current_page}
                                        totalItems={selectedSites.total}
                                        pageSize={selectedSites.per_page}
                                        onPageChange={(page) =>
                                            openSites(selectedClient.id, page)
                                        }
                                    />
                                )}
                            </>
                        )}
                        {siteLoading && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                Loading sites…
                            </p>
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
                restoreFocus={() => {
                    if (siteDeleteTrigger.current?.isConnected) {
                        siteDeleteTrigger.current.focus();
                    } else {
                        siteSheet.current
                            ?.querySelector<HTMLElement>(
                                '[data-site-list-heading]',
                            )
                            ?.focus();
                    }
                }}
            />
            <ViewModal
                open={!!viewingSite}
                onClose={() => setViewingSite(null)}
                title="Site Details"
                restoreFocus={() => siteViewTrigger.current?.focus()}
            >
                {viewingSite && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <ViewField
                            label="Site Name"
                            value={viewingSite.site_name}
                        />
                        <ViewField
                            label="Company"
                            value={selectedClient?.company_name}
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
