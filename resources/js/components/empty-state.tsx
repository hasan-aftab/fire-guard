import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <Empty data-empty className={className}>
            <EmptyHeader>
                <EmptyMedia>
                    <Icon />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
            {action && <EmptyContent>{action}</EmptyContent>}
        </Empty>
    );
}
