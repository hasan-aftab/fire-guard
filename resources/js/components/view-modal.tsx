import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface ViewModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    children: React.ReactNode;
    restoreFocus?: () => void;
}

export function ViewModal({
    open,
    onClose,
    title,
    description,
    children,
    restoreFocus,
}: ViewModalProps) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent
                className="flex max-h-[85vh] flex-col sm:max-w-xl"
                onCloseAutoFocus={
                    restoreFocus
                        ? (event) => {
                              event.preventDefault();
                              restoreFocus();
                          }
                        : undefined
                }
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription
                        className={description ? undefined : 'sr-only'}
                    >
                        {description || 'Review the record details.'}
                    </DialogDescription>
                </DialogHeader>
                <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto pr-1">
                    {children}
                </div>
                <div className="flex shrink-0 justify-end border-t pt-2">
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function ViewField({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm text-foreground">{value || '—'}</p>
        </div>
    );
}

export { ViewField };
