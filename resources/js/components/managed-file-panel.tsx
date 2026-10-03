import { router, usePage } from '@inertiajs/react';
import { Download, FileText, FileUp, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    destroy as removePhoto,
    download as downloadPhoto,
    show as showPhoto,
    thumbnail as thumbnailPhoto,
} from '@/actions/App/Http/Controllers/InspectionPhotoController';
import {
    destroy as removeDocument,
    download as downloadDocument,
    show as showDocument,
    thumbnail as thumbnailDocument,
} from '@/actions/App/Http/Controllers/CertificateDocumentController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentDescription,
    AttachmentGroup,
    AttachmentMedia,
    AttachmentTitle,
    AttachmentTrigger,
} from '@/components/ui/attachment';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { uploadManagedFile } from '@/lib/managed-upload';
import type { ManagedFile } from '@/types/fire-tracker';

type Kind = 'photo' | 'document';
type FileState = {
    state: 'idle' | 'uploading' | 'processing' | 'error' | 'done';
    progress: number;
    error?: string;
};
type Preview = {
    name: string;
    url: string;
    downloadUrl: string;
    isImage: boolean;
    isLocal: boolean;
};

function isImage(name: string, mimeType?: string): boolean {
    return (
        mimeType?.startsWith('image/') === true ||
        /\.(jpe?g|png|webp)$/i.test(name)
    );
}

function fileUrl(
    kind: Kind,
    action: 'show' | 'download' | 'thumbnail' | 'remove',
    recordId: number,
    fileId: number,
): string {
    if (kind === 'photo') {
        const args: [number, number] = [recordId, fileId];
        return action === 'show'
            ? showPhoto.url(args)
            : action === 'download'
              ? downloadPhoto.url(args)
              : action === 'thumbnail'
                ? thumbnailPhoto.url(args)
                : removePhoto.url(args);
    }
    const args: [number, number] = [recordId, fileId];
    return action === 'show'
        ? showDocument.url(args)
        : action === 'download'
          ? downloadDocument.url(args)
          : action === 'thumbnail'
            ? thumbnailDocument.url(args)
            : removeDocument.url(args);
}

function AttachmentCard({
    name,
    description,
    imageUrl,
    state = 'done',
    progress,
    canPreview,
    onPreview,
    onRemove,
    disabled,
    downloadUrl,
    permanent = false,
}: {
    name: string;
    description: string;
    imageUrl?: string;
    state?: FileState['state'];
    progress?: number;
    canPreview: boolean;
    onPreview: () => void;
    onRemove: () => void;
    disabled: boolean;
    downloadUrl?: string;
    permanent?: boolean;
}) {
    const [imageReady, setImageReady] = useState(false);
    const [imageFailed, setImageFailed] = useState(false);

    useEffect(() => {
        setImageReady(false);
        setImageFailed(false);
    }, [imageUrl]);

    return (
        <Attachment
            orientation="vertical"
            state={state}
            className="w-32 overflow-hidden"
        >
            <AttachmentMedia
                variant={imageUrl && !imageFailed ? 'image' : 'icon'}
            >
                {imageUrl && !imageFailed ? (
                    <img
                        src={imageUrl}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover"
                        onLoad={() => setImageReady(true)}
                        onError={() => setImageFailed(true)}
                    />
                ) : (
                    <FileText />
                )}
                {imageUrl && !imageReady && !imageFailed && (
                    <Skeleton className="absolute inset-0 rounded-none" />
                )}
            </AttachmentMedia>
            <AttachmentContent>
                <AttachmentTitle title={name}>{name}</AttachmentTitle>
                <AttachmentDescription title={description}>
                    {description}
                </AttachmentDescription>
                {(state === 'uploading' || state === 'processing') && (
                    <Progress
                        value={progress ?? 0}
                        aria-label={`${name} upload progress`}
                        className="mt-2"
                    />
                )}
            </AttachmentContent>
            <AttachmentActions className="order-first w-full justify-end border-b px-1 py-1 group-data-[orientation=vertical]/attachment:static">
                {downloadUrl && (
                    <AttachmentAction
                        asChild
                        size="icon"
                        className="size-7"
                        aria-label={`Download ${name}`}
                    >
                        <a href={downloadUrl}>
                            <Download />
                        </a>
                    </AttachmentAction>
                )}
                <AttachmentAction
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-7"
                    aria-label={`${permanent ? 'Delete' : 'Remove'} ${name}`}
                    disabled={disabled}
                    onClick={onRemove}
                >
                    {permanent ? <Trash2 /> : <X />}
                </AttachmentAction>
            </AttachmentActions>
            <AttachmentTrigger
                aria-label={`Preview ${name}`}
                disabled={!canPreview}
                onClick={onPreview}
            />
        </Attachment>
    );
}

export function ManagedFilePanel({
    kind,
    title,
    recordId,
    files = [],
    selectedFiles,
    onSelectedFilesChange,
    uploadRequest,
    showUploadButton = true,
    disabled = false,
    onUploadStateChange,
    onFinished,
}: {
    kind: Kind;
    title: string;
    recordId?: number | null;
    files?: ManagedFile[];
    selectedFiles?: File[];
    onSelectedFilesChange?: (files: File[]) => void;
    uploadRequest?: { token: number; recordId: number } | null;
    showUploadButton?: boolean;
    disabled?: boolean;
    onUploadStateChange?: (busy: boolean) => void;
    onFinished?: (failed: number) => void;
}) {
    const { fileUploadLimits } = usePage().props;
    const [localFiles, setLocalFiles] = useState<File[]>([]);
    const [previewUrls, setPreviewUrls] = useState<Map<File, string>>(
        new Map(),
    );
    const [preview, setPreview] = useState<Preview | null>(null);
    const [states, setStates] = useState<Record<number, FileState>>({});
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<ManagedFile | null>(null);
    const [error, setError] = useState<string | null>(null);
    const input = useRef<HTMLInputElement>(null);
    const lastRequest = useRef<number | null>(null);
    const selected = selectedFiles ?? localFiles;
    const setSelected = onSelectedFilesChange ?? setLocalFiles;
    const multiple = kind === 'photo';
    const maxBytes = multiple
        ? fileUploadLimits.photoBytes
        : fileUploadLimits.documentBytes;
    const accept = multiple
        ? 'image/jpeg,image/png,image/webp'
        : 'application/pdf,image/jpeg,image/png';

    useEffect(() => {
        const urls = new Map<File, string>();
        selected.forEach((file) => urls.set(file, URL.createObjectURL(file)));
        setPreviewUrls(urls);

        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [selected]);

    const selectFiles = (choices: File[]) => {
        if (busy || disabled || choices.length === 0) return;
        const next = multiple ? [...selected, ...choices] : choices.slice(0, 1);
        if (input.current) input.current.value = '';
        if (next.length > (multiple ? 10 : 1)) {
            setError('Select at most 10 photos per upload.');
            return;
        }
        const invalid = next.find((file) => {
            const extension = file.name.split('.').pop()?.toLowerCase();
            return (
                file.size === 0 ||
                file.size > maxBytes ||
                !extension ||
                !(
                    multiple
                        ? ['jpg', 'jpeg', 'png', 'webp']
                        : ['pdf', 'jpg', 'jpeg', 'png']
                ).includes(extension)
            );
        });
        if (invalid) {
            setError(
                `${invalid.name} must be an allowed file under ${Math.round(maxBytes / 1048576)} MB.`,
            );
            return;
        }
        setPreview(null);
        setError(null);
        setStates({});
        setSelected(next);
    };

    const upload = useCallback(
        async (targetId: number) => {
            if (busy || selected.length === 0) return;
            setBusy(true);
            onUploadStateChange?.(true);
            setError(null);
            const work = selected
                .map((file, index) => ({ file, index }))
                .filter(({ index }) => states[index]?.state !== 'done');
            let cursor = 0;
            const failures = new Map<number, string>();
            await Promise.all(
                Array.from({ length: Math.min(2, work.length) }, async () => {
                    while (cursor < work.length) {
                        const { file, index } = work[cursor++];
                        setStates((previous) => ({
                            ...previous,
                            [index]: { state: 'uploading', progress: 0 },
                        }));
                        try {
                            await uploadManagedFile(
                                file,
                                kind,
                                targetId,
                                (progress) => {
                                    setStates((previous) => ({
                                        ...previous,
                                        [index]: {
                                            state:
                                                progress === 99
                                                    ? 'processing'
                                                    : progress === 100
                                                      ? 'done'
                                                      : 'uploading',
                                            progress,
                                        },
                                    }));
                                },
                            );
                        } catch (cause) {
                            const message =
                                cause instanceof Error
                                    ? cause.message
                                    : 'Upload failed. Please retry.';
                            failures.set(index, message);
                            setStates((previous) => ({
                                ...previous,
                                [index]: {
                                    state: 'error',
                                    progress: 0,
                                    error: message,
                                },
                            }));
                        }
                    }
                }),
            );
            if (failures.size > 0) {
                const remaining = selected.flatMap((file, index) => {
                    const message = failures.get(index);
                    return message ? [{ file, message }] : [];
                });
                setSelected(remaining.map(({ file }) => file));
                setStates(
                    Object.fromEntries(
                        remaining.map(({ message }, index) => [
                            index,
                            { state: 'error', progress: 0, error: message },
                        ]),
                    ),
                );
                setError(
                    `${failures.size} file${failures.size === 1 ? '' : 's'} could not be uploaded. Retry the failed files.`,
                );
            }
            if (work.length > 0 && failures.size === work.length) {
                setBusy(false);
                onUploadStateChange?.(false);
                onFinished?.(failures.size);
                return;
            }

            let refreshed = false;
            router.reload({
                only: ['records'],
                onSuccess: () => {
                    refreshed = true;
                    if (failures.size === 0) {
                        toast.success(
                            multiple
                                ? 'Inspection photos uploaded.'
                                : 'Certificate document uploaded.',
                        );
                        if (onFinished) {
                            onFinished(0);
                        } else {
                            setSelected([]);
                            setStates({});
                        }
                    } else {
                        onFinished?.(failures.size);
                    }
                },
                onFinish: () => {
                    if (!refreshed && failures.size === 0) {
                        setError(
                            'Files uploaded, but the list could not refresh. Save again to refresh it.',
                        );
                    }
                    if (refreshed && failures.size === 0 && onFinished) {
                        return;
                    }
                    setBusy(false);
                    onUploadStateChange?.(false);
                },
            });
        },
        [
            busy,
            kind,
            multiple,
            onFinished,
            onUploadStateChange,
            selected,
            setSelected,
            states,
        ],
    );

    useEffect(() => {
        if (uploadRequest && lastRequest.current !== uploadRequest.token) {
            lastRequest.current = uploadRequest.token;
            void upload(uploadRequest.recordId);
        }
    }, [uploadRequest, upload]);

    const removeStored = () => {
        if (!recordId || !removing || busy || disabled) return;
        setBusy(true);
        router.delete(fileUrl(kind, 'remove', recordId, removing.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => setRemoving(null),
            onError: (errors) =>
                setError(
                    Object.values(errors)[0] ??
                        'The file could not be removed.',
                ),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <section
            className="flex min-w-0 flex-col gap-4 border-t pt-5"
            aria-label={title}
        >
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-semibold">{title}</h3>
                <p className="text-xs text-muted-foreground">
                    {showUploadButton
                        ? 'Select files and upload them here.'
                        : 'Select files, then save the record to upload them.'}
                </p>
            </div>
            {files.length === 0 && selected.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    No files uploaded.
                </p>
            )}
            {files.length > 0 && recordId && (
                <div className="flex min-w-0 flex-col gap-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        Uploaded
                    </p>
                    <AttachmentGroup
                        role="group"
                        aria-label={`Uploaded ${title.toLowerCase()}`}
                        tabIndex={0}
                    >
                        {files.map((file) => {
                            const url = fileUrl(
                                kind,
                                'show',
                                recordId,
                                file.id,
                            );
                            const thumbnailUrl = fileUrl(
                                kind,
                                'thumbnail',
                                recordId,
                                file.id,
                            );
                            const downloadUrl = fileUrl(
                                kind,
                                'download',
                                recordId,
                                file.id,
                            );
                            const image = isImage(
                                file.original_name,
                                file.mime_type,
                            );
                            return (
                                <AttachmentCard
                                    key={file.id}
                                    name={file.original_name}
                                    description={`${file.mime_type} · ${(file.file_size / 1048576).toFixed(1)} MB`}
                                    imageUrl={image ? thumbnailUrl : undefined}
                                    canPreview
                                    onPreview={() =>
                                        setPreview({
                                            name: file.original_name,
                                            url,
                                            downloadUrl,
                                            isImage: image,
                                            isLocal: false,
                                        })
                                    }
                                    onRemove={() => setRemoving(file)}
                                    disabled={busy || disabled}
                                    downloadUrl={downloadUrl}
                                    permanent
                                />
                            );
                        })}
                    </AttachmentGroup>
                </div>
            )}
            {selected.length > 0 && (
                <div className="flex min-w-0 flex-col gap-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        Selected for upload
                    </p>
                    <AttachmentGroup
                        role="group"
                        aria-label={`Selected ${title.toLowerCase()}`}
                        tabIndex={0}
                    >
                        {selected.map((file, index) => {
                            const status = states[index] ?? {
                                state: 'idle',
                                progress: 0,
                            };
                            const url = previewUrls.get(file);
                            const image = isImage(file.name, file.type);
                            const description =
                                status.error ??
                                (status.state === 'processing'
                                    ? 'Processing...'
                                    : status.state === 'uploading'
                                      ? `${status.progress}% uploaded`
                                      : status.state === 'done'
                                        ? 'Uploaded'
                                        : `${(file.size / 1048576).toFixed(1)} MB · Ready`);
                            return (
                                <AttachmentCard
                                    key={`${file.name}-${file.lastModified}-${index}`}
                                    name={file.name}
                                    description={description}
                                    imageUrl={image ? url : undefined}
                                    state={status.state}
                                    progress={status.progress}
                                    canPreview={!!url}
                                    onPreview={() => {
                                        if (url) {
                                            setPreview({
                                                name: file.name,
                                                url,
                                                downloadUrl: url,
                                                isImage: image,
                                                isLocal: true,
                                            });
                                        }
                                    }}
                                    onRemove={() => {
                                        setPreview(null);
                                        setSelected(
                                            selected.filter(
                                                (_, current) =>
                                                    current !== index,
                                            ),
                                        );
                                        setStates({});
                                    }}
                                    disabled={busy || disabled}
                                />
                            );
                        })}
                    </AttachmentGroup>
                </div>
            )}
            <Field data-invalid={!!error}>
                <Input
                    ref={input}
                    id={`managed-file-${kind}`}
                    type="file"
                    className="peer sr-only"
                    accept={accept}
                    multiple={multiple}
                    disabled={busy || disabled}
                    aria-invalid={!!error}
                    aria-describedby={`managed-file-${kind}-help`}
                    onChange={(event) =>
                        selectFiles(Array.from(event.target.files ?? []))
                    }
                />
                <FieldLabel
                    htmlFor={`managed-file-${kind}`}
                    className="flex min-h-32 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-input bg-muted/30 px-4 py-5 text-center transition-colors group-data-[invalid=true]/field:border-destructive peer-focus-visible:border-ring peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 peer-disabled:pointer-events-none peer-disabled:cursor-not-allowed peer-disabled:opacity-50 hover:bg-muted/50"
                >
                    <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <FileUp className="size-5" aria-hidden="true" />
                    </span>
                    <span className="text-sm font-medium text-foreground">
                        {multiple
                            ? selected.length > 0
                                ? 'Add more photos'
                                : 'Choose photos'
                            : selected.length > 0
                              ? 'Change selected document'
                              : files.length > 0
                                ? 'Choose replacement document'
                                : 'Choose document'}
                    </span>
                    <span className="text-xs font-normal text-muted-foreground">
                        Select {multiple ? 'images' : 'a PDF or image'} from
                        your device
                    </span>
                </FieldLabel>
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                <FieldDescription id={`managed-file-${kind}-help`}>
                    {multiple
                        ? 'JPEG, PNG or WebP. Up to 10 photos at a time.'
                        : 'PDF, JPEG or PNG.'}{' '}
                    Maximum {Math.round(maxBytes / 1048576)} MB per file.
                </FieldDescription>
            </Field>
            {showUploadButton && recordId && (
                <Button
                    type="button"
                    size="sm"
                    disabled={busy || disabled || selected.length === 0}
                    onClick={() => void upload(recordId)}
                >
                    {busy && <Spinner data-icon="inline-start" />}
                    {busy
                        ? 'Uploading...'
                        : error
                          ? 'Retry upload'
                          : multiple
                            ? 'Upload photos'
                            : files.length
                              ? 'Replace document'
                              : 'Upload document'}
                </Button>
            )}
            <Dialog
                open={!!preview}
                onOpenChange={(open) => {
                    if (!open) setPreview(null);
                }}
            >
                <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle className="truncate pr-8">
                            {preview?.name}
                        </DialogTitle>
                        <DialogDescription>
                            {preview?.isImage
                                ? 'Image preview'
                                : 'Document preview'}
                        </DialogDescription>
                    </DialogHeader>
                    {preview && (
                        <div className="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-md bg-muted">
                            {preview.isImage ? (
                                <img
                                    src={preview.url}
                                    alt={preview.name}
                                    className="max-h-[70vh] max-w-full object-contain"
                                />
                            ) : (
                                <iframe
                                    src={preview.url}
                                    title={`Preview ${preview.name}`}
                                    className="h-[70vh] w-full"
                                />
                            )}
                        </div>
                    )}
                    {preview && (
                        <DialogFooter>
                            <Button asChild variant="outline">
                                <a
                                    href={preview.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open file
                                </a>
                            </Button>
                            <Button asChild variant="outline">
                                <a
                                    href={preview.downloadUrl}
                                    download={
                                        preview.isLocal
                                            ? preview.name
                                            : undefined
                                    }
                                >
                                    <Download data-icon="inline-start" />
                                    Download
                                </a>
                            </Button>
                        </DialogFooter>
                    )}
                </DialogContent>
            </Dialog>
            <ConfirmDialog
                open={!!removing}
                onOpenChange={(open) => {
                    if (!open) setRemoving(null);
                }}
                title={`Remove ${removing?.original_name ?? 'file'}?`}
                description={`Permanently delete this ${multiple ? 'inspection photo' : 'certificate document'} now? This cannot be undone. Any unsaved form changes remain in the editor.`}
                confirmLabel="Delete file permanently"
                destructive
                loading={busy}
                onConfirm={removeStored}
            />
        </section>
    );
}

export function InspectionPhotos({
    inspectionId,
    photos,
}: {
    inspectionId: number;
    photos: ManagedFile[];
}) {
    return (
        <ManagedFilePanel
            kind="photo"
            title="Inspection Photos"
            recordId={inspectionId}
            files={photos}
        />
    );
}

export function CertificateDocument({
    certificateId,
    document,
}: {
    certificateId: number;
    document: ManagedFile | null;
}) {
    return (
        <ManagedFilePanel
            kind="document"
            title="Certificate Document"
            recordId={certificateId}
            files={document ? [document] : []}
        />
    );
}
