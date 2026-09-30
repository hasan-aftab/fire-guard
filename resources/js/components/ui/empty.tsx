import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function Empty({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="empty" className={cn('flex flex-col items-center justify-center gap-3 py-16 text-center', className)} {...props} />;
}
export function EmptyHeader(props: ComponentProps<'div'>) { return <div className="flex max-w-sm flex-col items-center gap-2" {...props} />; }
export function EmptyMedia(props: ComponentProps<'div'>) { return <div className="mb-2 flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground [&_svg]:size-7" {...props} />; }
export function EmptyTitle(props: ComponentProps<'h3'>) { return <h3 className="text-base font-semibold" {...props} />; }
export function EmptyDescription(props: ComponentProps<'p'>) { return <p className="text-sm text-muted-foreground" {...props} />; }
export function EmptyContent(props: ComponentProps<'div'>) { return <div className="mt-3" {...props} />; }
