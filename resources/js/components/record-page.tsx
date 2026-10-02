import { Head, router } from '@inertiajs/react';
import {
    Archive,
    CircleOff,
    Eye,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ManagedFilePanel } from '@/components/managed-file-panel';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { RecordForm, recordValues } from '@/components/record-form';
import type { FormField } from '@/components/record-form';
import { TrackerFilters } from '@/components/tracker-filters';
import { Badge } from '@/components/ui/badge';
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
    ManagedFile,
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
    markError?: (id: number) => RouteDefinition<'patch'>;
    restoreError?: (id: number) => RouteDefinition<'patch'>;
    purge?: (id: number) => RouteDefinition<'delete'>;
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
    mistake,
    rowActions,
    viewContent,
    fileUpload,
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
            | 'equipment_status'
            | 'record_state';
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
    mistake?: {
        isEnteredInError: (record: T) => boolean;
        canPurge?: (record: T) => boolean;
    };
    rowActions?: (record: T) => ReactNode;
    viewContent?: (
        record: T,
        closeView: (restoreFocus?: boolean) => void,
    ) => ReactNode;
    fileUpload?: {
        kind: 'photo' | 'document';
        title: string;
        files: (record: T) => ManagedFile[];
    };
}) {
    const [editing, setEditing] = useState<T | null | undefined>();
    const [editorOpen, setEditorOpen] = useState(false);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [savedRecordId, setSavedRecordId] = useState<number | null>(null);
    const [uploadRequest, setUploadRequest] = useState<{
        token: number;
        recordId: number;
    } | null>(null);
    const [formBusy, setFormBusy] = useState(false);
    const [uploadBusy, setUploadBusy] = useState(false);
    const [discardFilesOpen, setDiscardFilesOpen] = useState(false);
    const [viewing, setViewing] = useState<T | null>(null);
    const currentEditing = editing
        ? (records.data.find((record) => record.id === editing.id) ?? editing)
        : editing;
    const currentViewing = viewing
        ? (records.data.find((record) => record.id === viewing.id) ?? viewing)
        : null;
    const [deleting, setDeleting] = useState<T | null>(null);
    const [deletePending, setDeletePending] = useState(false);
    const [mistakeTarget, setMistakeTarget] = useState<{
        record: T;
        action: 'mark' | 'restore' | 'purge';
    } | null>(null);
    const [mistakePending, setMistakePending] = useState(false);
    const [pageLoading, setPageLoading] = useState(false);
    const editorTrigger = useRef<HTMLButtonElement>(null);
    const uploadToken = useRef(0);
    const editorRecordId = useRef<number | null>(null);
    const viewTrigger = useRef<HTMLButtonElement>(null);
    const skipViewFocusRestore = useRef(false);
    const deleteTrigger = useRef<HTMLButtonElement>(null);
    const mistakeTrigger = useRef<HTMLButtonElement>(null);
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
    const finishEditor = () => {
        setEditorOpen(false);
    };
    const requestCloseEditor = () => {
        if (formBusy || uploadBusy) return;
        if (selectedFiles.length > 0) {
            setDiscardFilesOpen(true);
            return;
        }
        finishEditor();
    };
    const createButton = (
        <Button
            data-record-create
            onClick={(event) => {
                editorTrigger.current = event.currentTarget;
                editorRecordId.current = null;
                setSelectedFiles([]);
                setSavedRecordId(null);
                setUploadRequest(null);
                setEditing(null);
                setEditorOpen(true);
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
        ...(mistake && records.data.some(mistake.isEnteredInError)
            ? [
                  {
                      key: 'record-state',
                      header: 'Record state',
                      render: (record: T) =>
                          mistake.isEnteredInError(record) ? (
                              <Badge variant="outline">Entered in error</Badge>
                          ) : (
                              'Current'
                          ),
                  },
              ]
            : []),
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
                            setSavedRecordId(null);
                            setSelectedFiles([]);
                            setUploadRequest(null);
                            setEditing(record);
                            setEditorOpen(true);
                        }}
                    >
                        <Pencil />
                    </Button>
                    {rowActions?.(record)}
                    {archive && !mistake?.isEnteredInError(record) && (
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
                    {mistake && (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label={`${mistake.isEnteredInError(record) ? 'Restore' : 'Mark entered in error'} ${noun} #${record.id}`}
                                onClick={(event) => {
                                    mistakeTrigger.current =
                                        event.currentTarget;
                                    setMistakeTarget({
                                        record,
                                        action: mistake.isEnteredInError(record)
                                            ? 'restore'
                                            : 'mark',
                                    });
                                }}
                            >
                                {mistake.isEnteredInError(record) ? (
                                    <RotateCcw />
                                ) : (
                                    <CircleOff />
                                )}
                            </Button>
                            {mistake.canPurge?.(record) && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    aria-label={`Permanently delete ${noun} #${record.id}`}
                                    onClick={(event) => {
                                        mistakeTrigger.current =
                                            event.currentTarget;
                                        setMistakeTarget({
                                            record,
                                            action: 'purge',
                                        });
                                    }}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
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
    const confirmMistake = () => {
        if (!mistakeTarget) return;
        const { record, action } = mistakeTarget;
        const route =
            action === 'mark'
                ? routes.markError?.(record.id)
                : action === 'restore'
                  ? routes.restoreError?.(record.id)
                  : routes.purge?.(record.id);
        if (!route) return;
        setMistakePending(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => setMistakeTarget(null),
            onFinish: () => setMistakePending(false),
        };
        if (action === 'purge') router.delete(route, options);
        else router.patch(route, {}, options);
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
                extraFilters={
                    mistake
                        ? [
                              {
                                  key: 'record_state',
                                  label: 'Record state',
                                  options: [
                                      {
                                          value: 'current',
                                          label: 'Current records',
                                      },
                                      {
                                          value: 'entered_in_error',
                                          label: 'Entered in error',
                                      },
                                      { value: 'all', label: 'All records' },
                                  ],
                              },
                              ...(extraFilters ?? []),
                          ]
                        : extraFilters
                }
                total={records.total}
                noun={noun}
            />
            {records.total === 0 &&
            !Object.values(filters).some(
                (value) =>
                    value &&
                    value !== 'all' &&
                    value !== 'name' &&
                    value !== 'current',
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
                open={editorOpen}
                onOpenChange={(open) => {
                    if (!open) requestCloseEditor();
                }}
            >
                <SheetContent
                    className={
                        fileUpload
                            ? 'w-full data-[state=closed]:duration-200 data-[state=open]:duration-[250ms] motion-reduce:animate-none sm:max-w-xl'
                            : 'w-full sm:max-w-md'
                    }
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        setEditing(undefined);
                        setUploadBusy(false);
                        restoreEditorFocus();
                    }}
                >
                    <SheetHeader>
                        <SheetTitle>
                            {editing
                                ? `Edit ${noun}`
                                : createTitle || `New ${noun}`}
                        </SheetTitle>
                        <SheetDescription>
                            {uploadBusy
                                ? `${noun[0].toUpperCase()}${noun.slice(1)} saved. Saving selected files...`
                                : fileUpload
                                  ? `${description}. Select files below, then save to upload them.`
                                  : description}
                        </SheetDescription>
                    </SheetHeader>
                    {editing !== undefined && (
                        <div className="flex min-h-0 flex-1 flex-col px-4 pb-4">
                            <RecordForm
                                key={editing?.id ?? 'new'}
                                fields={
                                    typeof fields === 'function'
                                        ? fields(currentEditing ?? null)
                                        : fields
                                }
                                initialValues={
                                    currentEditing
                                        ? recordValues(currentEditing, defaults)
                                        : defaults
                                }
                                action={
                                    editing
                                        ? routes.update(editing.id)
                                        : savedRecordId
                                          ? routes.update(savedRecordId)
                                          : routes.store()
                                }
                                saveLabel={
                                    uploadBusy
                                        ? 'Saving files...'
                                        : fileUpload && selectedFiles.length > 0
                                          ? savedRecordId || editing
                                              ? `Update ${noun} and upload files`
                                              : `Save ${noun} and upload files`
                                          : editing || savedRecordId
                                            ? `Update ${noun}`
                                            : addLabel || `Create ${noun}`
                                }
                                submitExtra={
                                    fileUpload && selectedFiles.length > 0
                                        ? { upload_pending: '1' }
                                        : undefined
                                }
                                locked={formBusy || uploadBusy}
                                onProcessingChange={setFormBusy}
                                onSuccess={(createdId) => {
                                    if (
                                        fileUpload &&
                                        selectedFiles.length > 0
                                    ) {
                                        const recordId =
                                            editing?.id ??
                                            savedRecordId ??
                                            createdId;
                                        if (!recordId) {
                                            toast.error(
                                                'The record was saved, but the upload could not start. Open it to add files.',
                                            );
                                            finishEditor();
                                            return;
                                        }
                                        setSavedRecordId(recordId);
                                        setUploadBusy(true);
                                        setUploadRequest({
                                            token: ++uploadToken.current,
                                            recordId,
                                        });
                                        return;
                                    }
                                    finishEditor();
                                }}
                                onCancel={requestCloseEditor}
                            >
                                {fileUpload && (
                                    <ManagedFilePanel
                                        kind={fileUpload.kind}
                                        title={fileUpload.title}
                                        recordId={editing?.id ?? savedRecordId}
                                        files={
                                            currentEditing
                                                ? fileUpload.files(
                                                      currentEditing,
                                                  )
                                                : []
                                        }
                                        selectedFiles={selectedFiles}
                                        onSelectedFilesChange={setSelectedFiles}
                                        uploadRequest={uploadRequest}
                                        showUploadButton={false}
                                        disabled={formBusy || uploadBusy}
                                        onUploadStateChange={setUploadBusy}
                                        onFinished={(failed) => {
                                            if (failed === 0) finishEditor();
                                        }}
                                    />
                                )}
                            </RecordForm>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
            <ConfirmDialog
                open={discardFilesOpen}
                onOpenChange={setDiscardFilesOpen}
                title="Discard selected files?"
                description={
                    savedRecordId
                        ? `The ${noun} details are saved. Selected files have not been uploaded.`
                        : `Selected files and unsaved ${noun} details will be discarded.`
                }
                confirmLabel="Discard and close"
                destructive
                onConfirm={() => {
                    setDiscardFilesOpen(false);
                    setSelectedFiles([]);
                    finishEditor();
                }}
            />
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
            {mistake && (
                <ConfirmDialog
                    open={!!mistakeTarget}
                    onOpenChange={(open) => {
                        if (!open) setMistakeTarget(null);
                    }}
                    title={`${mistakeTarget?.action === 'purge' ? 'Permanently delete' : mistakeTarget?.action === 'restore' ? 'Restore' : 'Mark entered in error'} ${noun}?`}
                    description={
                        mistakeTarget?.action === 'purge'
                            ? `This ${noun} has no linked history. It will be permanently deleted. This cannot be undone.`
                            : mistakeTarget?.action === 'restore'
                              ? `Restore this ${noun} to the normal lists and active calculations? Its other active or inactive state will stay as saved.`
                              : `Mark this ${noun} as entered in error? It will leave normal lists and active alerts, but remain available through the record state filter.`
                    }
                    confirmLabel={
                        mistakeTarget?.action === 'purge'
                            ? 'Delete permanently'
                            : mistakeTarget?.action === 'restore'
                              ? 'Restore'
                              : 'Mark entered in error'
                    }
                    destructive={mistakeTarget?.action === 'purge'}
                    onConfirm={confirmMistake}
                    loading={mistakePending}
                    restoreFocus={() => {
                        if (mistakeTrigger.current?.isConnected) {
                            mistakeTrigger.current.focus();
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
                {currentViewing && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {columns
                            .filter((column) => column.showInView !== false)
                            .map((column) => (
                                <ViewField
                                    key={column.key}
                                    label={column.header}
                                    value={column.render(currentViewing, 0)}
                                />
                            ))}
                    </div>
                )}
                {currentViewing &&
                    viewContent?.(currentViewing, (restoreFocus = true) => {
                        skipViewFocusRestore.current = !restoreFocus;
                        setViewing(null);
                    })}
            </ViewModal>
        </>
    );
}
