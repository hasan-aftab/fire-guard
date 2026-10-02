import {
    chunk,
    complete,
    destroy,
    store,
} from '@/actions/App/Http/Controllers/ManagedUploadController';

type UploadSession = {
    id: string;
    chunk_bytes: number;
    total_chunks: number;
};

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

async function requestJson<T>(
    url: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body?: BodyInit,
    retry = false,
): Promise<T> {
    for (let attempt = 0; attempt < (retry ? 3 : 1); attempt++) {
        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                    ...(typeof body === 'string'
                        ? { 'Content-Type': 'application/json' }
                        : body
                          ? { 'Content-Type': 'application/octet-stream' }
                          : {}),
                },
                body,
            });
            if (response.ok) return (await response.json()) as T;

            const data = (await response.json().catch(() => ({}))) as {
                message?: string;
                errors?: Record<string, string[]>;
            };
            const message =
                Object.values(data.errors ?? {})[0]?.[0] ??
                data.message ??
                `Upload failed (${response.status}).`;
            if (retry && response.status >= 500 && attempt < 2) {
                await new Promise((resolve) =>
                    setTimeout(resolve, 500 * 2 ** attempt),
                );
                continue;
            }
            throw new Error(message);
        } catch (error) {
            if (retry && attempt < 2 && error instanceof TypeError) {
                await new Promise((resolve) =>
                    setTimeout(resolve, 500 * 2 ** attempt),
                );
                continue;
            }
            throw error;
        }
    }

    throw new Error('Upload failed. Please retry.');
}

export async function uploadManagedFile(
    file: File,
    kind: 'photo' | 'document',
    recordId: number,
    onProgress: (percent: number) => void,
): Promise<void> {
    const session = await requestJson<UploadSession>(
        store.url(),
        'POST',
        JSON.stringify({
            kind,
            record_id: recordId,
            name: file.name,
            size: file.size,
        }),
    );

    try {
        for (let index = 0; index < session.total_chunks; index++) {
            const start = index * session.chunk_bytes;
            const piece = file.slice(start, start + session.chunk_bytes);
            await requestJson(
                chunk.url([session.id, index]),
                'PUT',
                piece,
                true,
            );
            onProgress(
                Math.min(
                    99,
                    Math.round(((index + 1) / session.total_chunks) * 100),
                ),
            );
        }
        await requestJson(complete.url(session.id), 'POST', undefined, true);
        onProgress(100);
    } catch (error) {
        try {
            await requestJson(destroy.url(session.id), 'DELETE');
        } catch {
            // Expired sessions are removed by the scheduled cleanup.
        }
        throw error;
    }
}
