import type { ComponentProps } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export function FieldGroup({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="field-group" className={cn('flex flex-col gap-4', className)} {...props} />;
}

export function Field({ className, ...props }: ComponentProps<'div'>) {
    return <div role="group" data-slot="field" className={cn('group/field flex flex-col gap-1.5 data-[invalid=true]:text-destructive', className)} {...props} />;
}

export function FieldLabel({ className, ...props }: ComponentProps<typeof Label>) {
    return <Label data-slot="field-label" className={cn('leading-snug', className)} {...props} />;
}

export function FieldDescription({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="field-description" className={cn('text-xs text-muted-foreground', className)} {...props} />;
}

export function FieldError({ children, ...props }: ComponentProps<'p'>) {
    return children ? <p role="alert" data-slot="field-error" className="text-sm text-destructive" {...props}>{children}</p> : null;
}
