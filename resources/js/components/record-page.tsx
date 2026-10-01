import { Head, router } from '@inertiajs/react';
import { Archive, Eye, Pencil, RotateCcw } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { RecordForm, recordValues } from '@/components/record-form';
import type { FormField } from '@/components/record-form';
import { TrackerFilters } from '@/components/tracker-filters';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    Paginated,
    TrackerFilters as FilterValues,
} from '@/types/fire-tracker';
import type { RouteDefinition } from '@/wayfinder';

type ResourceRoutes = {
    index: () => RouteDefinition<'get'>;
    store: () => RouteDefinition<'post'>;
    update: (id: number) => RouteDefinition<'put' | 'patch'>;
    destroy?: (id: number) => RouteDefinition<'delete'>;
    restore?: (id: number) => RouteDefinition<'patch'>;
};

export function RecordPage<T extends { id: number }>({
    title,
    description,
    noun,
    icon,
    records,
    filters,
    routes,
    columns,
    fields,
    defaults,
    dateLabel,
    issueDates,
    statusOptions,
    statusKey,
    extraFilters,
    searchPlaceholder,
    addLabel,
    createTitle,
    addDisabled = false,
    addHint,
    emptyDescription,
    archive,
    rowActions,
    viewContent,
}: {
    title: string;
    description: string;
    noun: string;
    icon: LucideIcon;
    records: Paginated<T>;
    filters: FilterValues;
    routes: ResourceRoutes;
    columns: Column<T>[];
    fields: FormField[] | ((record: T | null) => FormField[]);
    defaults: Record<string, string>;
    dateLabel?: string;
    issueDates?: boolean;
    statusOptions?: { value: string; label: string }[];
    statusKey?: 'status' | 'result' | 'active';
    extraFilters?: {
        key:
            | 'site_presence'
            | 'sort'
            | 'site_id'
            | 'asset_type_id'
            | 'equipment_status';
        label: string;
        options: { value: string; label: string }[];
    }[];
    searchPlaceholder: string;
    addLabel?: string;
    createTitle?: string;
    addDisabled?: boolean;
    addHint?: ReactNode;
    emptyDescription?: string;
    archive?: {
        isArchived: (record: T) => boolean;
        action: string;
        description: string;
        restoreDescription: string;
    };
    rowActions?: (record: T) => ReactNode;
    viewContent?: (
        record: T,
        closeView: (restoreFocus?: boolean) => void,
    ) => ReactNode;
}) {
    const [editing, setEditing] = useState<T | null | undefined>();
    const [viewing, setViewing] = useState<T | null>(null);
    const [deleting, setDeleting] = useState<T | null>(null);
    const [deletePending, setDeletePending] = useState(false);
    const [pageLoading, setPageLoading] = useState(false);
    const editorTrigger = useRef<HTMLButtonElement>(null);
    const editorRecordId = useRef<number | null>(null);
    const viewTrigger = useRef<HTMLButtonElement>(null);
    const skipViewFocusRestore = useRef(false);
    const deleteTrigger = useRef<HTMLButtonElement>(null);
    useEffect(
        () =>
            router.on('networkError', () => {
                toast.error(
                    'Could not connect. Check your connection and try again.',
                );

                return false;
            }),
        [],
    );
    const restoreEditorFocus = () => {
        requestAnimationFrame(() => {
            const recordId = editorRecordId.current;
            const trigger = recordId
                ? document.querySelector<HTMLButtonElement>(
                      `[data-record-edit-id="${recordId}"]`,
                  )
                : document.querySelector<HTMLButtonElement>(
                      '[data-record-create]',
                  );
            (trigger ?? editorTrigger.current)?.focus();
        });
    };
    const createButton = (
        <Button
            data-record-create
            onClick={(event) => {
                editorTrigger.current = event.currentTarget;
                editorRecordId.current = null;
                setEditing(null);
            }}
            disabled={addDisabled}
        >
            {addLabel || `Add ${noun}`}
        </Button>
    );
    const start = (records.current_page - 1) * records.per_page;
    const allColumns: Column<T>[] = [
        {
            key: 'number',
            header: '#',
            className: 'w-10 text-xs text-muted-foreground',
            render: (_, index) => start + index + 1,
        },
        ...columns,
        {
            key: 'actions',
            header: 'Actions',
            className: 'sticky right-0 z-10 border-l bg-card',
            render: (record) => (
                <div className="flex gap-0.5">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        aria-label={`View ${noun} #${record.id}`}
                        onClick={(event) => {
                            viewTrigger.current = event.currentTarget;
                            skipViewFocusRestore.current = false;
                            setViewing(record);
                        }}
                    >
                        <Eye />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        aria-label={`Edit ${noun} #${record.id}`}
                        data-record-edit-id={record.id}
                        onClick={(event) => {
                            editorTrigger.current = event.currentTarget;
                            editorRecordId.current = record.id;
                            setEditing(record);
                        }}
                    >
                        <Pencil />
                    </Button>
                    {rowActions?.(record)}
                    {archive && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label={`${archive.isArchived(record) ? 'Reactivate' : archive.action} ${noun} #${record.id}`}
                            onClick={(event) => {
                                deleteTrigger.current = event.currentTarget;
                                setDeleting(record);
                            }}
                        >
                            {archive.isArchived(record) ? (
                                <RotateCcw />
                            ) : (
                                <Archive />
                            )}
                        </Button>
                    )}
                </div>
            ),
        },
    ];
    const confirmDelete = () => {
        if (!deleting || !archive) return;
        const route = archive.isArchived(deleting)
            ? routes.restore?.(deleting.id)
            : routes.destroy?.(deleting.id);
        if (!route) return;
        setDeletePending(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onFinish: () => setDeletePending(false),
        };
        if (archive.isArchived(deleting)) router.patch(route, {}, options);
        else router.delete(route, options);
    };

    return (
        <>
            <Head title={title} />
            <PageHeader
                title={title}
                description={description}
                actions={createButton}
            />
            {addDisabled && addHint && (
                <p className="mb-4 text-sm text-muted-foreground" role="status">
                    {addHint}
                </p>
            )}
            <TrackerFilters
                filters={filters}
                route={routes.index()}
                searchPlaceholder={searchPlaceholder}
                dateLabel={dateLabel}
                issueDates={issueDates}
                statusOptions={statusOptions}
                statusKey={statusKey}
                extraFilters={extraFilters}
                total={records.total}
                noun={noun}
            />
            {records.total === 0 &&
            !Object.values(filters).some(
                (value) => value && value !== 'all' && value !== 'name',
            ) ? (
                <EmptyState
                    icon={icon}
                    title={`No ${noun}s yet`}
                    description={emptyDescription || description}
                    action={addDisabled ? undefined : createButton}
                />
            ) : (
                <Card
                    className="gap-0 overflow-hidden py-0"
                    aria-busy={pageLoading}
                >
                    <CardHeader className="sr-only">
                        <CardTitle>{title}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <DataTable
                            columns={allColumns}
                            data={records.data}
                            scrollLabel={`${title} table`}
                            rowKey={(record) => record.id}
                            emptyMessage={`No ${noun}s match your filters.`}
                        />
                    </CardContent>
                    {records.total > records.per_page && (
                        <>
                            <Separator />
                            <Pagination
                                currentPage={records.current_page}
                                totalItems={records.total}
                                pageSize={records.per_page}
                                loading={pageLoading}
                                onPageChange={(page) =>
                                    router.get(
                                        routes.index(),
                                        { ...filters, page },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                            onStart: () => setPageLoading(true),
                                            onError: () =>
                                                toast.error(
                                                    'Could not load that page. Please try again.',
                                                ),
                                            onFinish: () =>
                                                setPageLoading(false),
                                        },
                                    )
                                }
                            />
                        </>
                    )}
                </Card>
            )}
            <Sheet
                open={editing !== undefined}
                onOpenChange={(open) => {
                    if (!open) setEditing(undefined);
                }}
            >
                <SheetContent
                    className="w-full sm:max-w-md"
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        restoreEditorFocus();
                    }}
                >
                    <SheetHeader>
                        <SheetTitle>
                            {editing
                                ? `Edit ${noun}`
                                : createTitle || `New ${noun}`}
                        </SheetTitle>
                        <SheetDescription>{description}</SheetDescription>
                    </SheetHeader>
                    {editing !== undefined && (
                        <div className="flex min-h-0 flex-1 flex-col px-4 pb-4">
                            <RecordForm
                                key={editing?.id ?? 'new'}
                                fields={
                                    typeof fields === 'function'
                                        ? fields(editing)
                                        : fields
                                }
                                initialValues={
                                    editing
                                        ? recordValues(editing, defaults)
                                        : defaults
                                }
                                action={
                                    editing
                                        ? routes.update(editing.id)
                                        : routes.store()
                                }
                                saveLabel={
                                    editing
                                        ? `Update ${noun}`
                                        : addLabel || `Create ${noun}`
                                }
                                onSuccess={() => {
                                    setEditing(undefined);
                                    restoreEditorFocus();
                                }}
                                onCancel={() => {
                                    setEditing(undefined);
                                    restoreEditorFocus();
                                }}
                            />
                        </div>
                    )}
                </SheetContent>
            </Sheet>
            {archive && (
                <ConfirmDialog
                    open={!!deleting}
                    onOpenChange={(open) => {
                        if (!open) setDeleting(null);
                    }}
                    title={`${deleting && archive.isArchived(deleting) ? 'Reactivate' : archive.action} ${noun}?`}
                    description={
                        deleting && archive.isArchived(deleting)
                            ? archive.restoreDescription
                            : archive.description
                    }
                    confirmLabel={
                        deleting && archive.isArchived(deleting)
                            ? 'Reactivate'
                            : archive.action
                    }
                    onConfirm={confirmDelete}
                    loading={deletePending}
                    restoreFocus={() => {
                        if (deleteTrigger.current?.isConnected) {
                            deleteTrigger.current.focus();
                        } else {
                            document
                                .querySelector<HTMLButtonElement>(
                                    '[data-record-create]',
                                )
                                ?.focus();
                        }
                    }}
                />
            )}
            <ViewModal
                open={!!viewing}
                onClose={() => setViewing(null)}
                title={`${noun.charAt(0).toUpperCase() + noun.slice(1)} Details`}
                restoreFocus={() => {
                    if (!skipViewFocusRestore.current) {
                        viewTrigger.current?.focus();
                    }
                    skipViewFocusRestore.current = false;
                }}
            >
                {viewing && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {columns
                            .filter((column) => column.showInView !== false)
                            .map((column) => (
                                <ViewField
                                    key={column.key}
                                    label={column.header}
                                    value={column.render(viewing, 0)}
                                />
                            ))}
                    </div>
                )}
                {viewing &&
                    viewContent?.(viewing, (restoreFocus = true) => {
                        skipViewFocusRestore.current = !restoreFocus;
                        setViewing(null);
                    })}
            </ViewModal>
        </>
    );
}
