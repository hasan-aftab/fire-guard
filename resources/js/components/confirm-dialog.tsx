import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    destructive = false,
    onConfirm,
    loading = false,
    restoreFocus,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    onConfirm: () => void;
    loading?: boolean;
    restoreFocus?: () => void;
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!loading) onOpenChange(value);
            }}
        >
            <DialogContent
                role="alertdialog"
                className="duration-150 motion-reduce:animate-none"
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
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={loading}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={loading}
                        onClick={onConfirm}
                    >
                        {loading && <Spinner data-icon="inline-start" />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
