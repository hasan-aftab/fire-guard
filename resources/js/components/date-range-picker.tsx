import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

type DateRange = { from?: string; to?: string };

export function DateRangePicker({
    from,
    to,
    onSelect,
    placeholder = 'Select date range',
}: DateRange & { onSelect: (range: DateRange) => void; placeholder?: string }) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState({ from: from ?? '', to: to ?? '' });
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                if (value) setDraft({ from: from ?? '', to: to ?? '' });
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" className="text-muted-foreground">
                    <CalendarIcon data-icon="inline-start" />
                    {from || to
                        ? `${from || 'Any date'} – ${to || 'Any date'}`
                        : placeholder}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{placeholder}</DialogTitle>
                    <DialogDescription>
                        Include records within this date range.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSelect({ from: draft.from, to: draft.to });
                        setOpen(false);
                    }}
                >
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="range-from">From</FieldLabel>
                            <Input
                                id="range-from"
                                type="date"
                                value={draft.from}
                                max={draft.to || undefined}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        from: event.target.value,
                                    })
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="range-to">To</FieldLabel>
                            <Input
                                id="range-to"
                                type="date"
                                value={draft.to}
                                min={draft.from || undefined}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        to: event.target.value,
                                    })
                                }
                            />
                        </Field>
                    </FieldGroup>
                    <DialogFooter className="mt-5">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                onSelect({ from: '', to: '' });
                                setOpen(false);
                            }}
                        >
                            Clear
                        </Button>
                        <Button type="submit">Apply</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
