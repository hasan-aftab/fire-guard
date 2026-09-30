import type { Auth } from '@/types/auth';
import type { Counts, NotificationData } from '@/types/fire-tracker';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            today: string;
            counts: Counts | null;
            fireNotifications: NotificationData | null;
            [key: string]: unknown;
        };
    }
}
