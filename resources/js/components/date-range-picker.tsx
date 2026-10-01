import { format, parseISO } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverDescription,
    PopoverHeader,
    PopoverTitle,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useIsMobile } from '@/hooks/use-mobile';

type FilterRange = { from?: string; to?: string };
type FilterMode = 'range' | 'before' | 'after';

export function DateRangePicker({
    from,
    to,
    onSelect,
    placeholder = 'Select date range',
}: FilterRange & {
    onSelect: (range: FilterRange) => void;
    placeholder?: string;
}) {
    const [open, setOpen] = useState(false);
    const isMobile = useIsMobile();
    const [mode, setMode] = useState<FilterMode>('range');
    const [draft, setDraft] = useState<DateRange | undefined>();
    const [displayMonth, setDisplayMonth] = useState(new Date());
    const selection =
        from && to
            ? `${format(parseISO(from), 'MMM d, yyyy')} – ${format(parseISO(to), 'MMM d, yyyy')}`
            : from
              ? `From ${format(parseISO(from), 'MMM d, yyyy')}`
              : to
                ? `Through ${format(parseISO(to), 'MMM d, yyyy')}`
                : placeholder;

    const apply = () => {
        onSelect({
            from: draft?.from ? format(draft.from, 'yyyy-MM-dd') : '',
            to: draft?.to ? format(draft.to, 'yyyy-MM-dd') : '',
        });
        setOpen(false);
    };

    const handleOpenChange = (value: boolean) => {
        setOpen(value);
        if (value) {
            const fromDate = from ? parseISO(from) : undefined;
            const toDate = to ? parseISO(to) : undefined;
            setDraft({ from: fromDate, to: toDate });
            setMode(
                fromDate && toDate
                    ? 'range'
                    : toDate
                      ? 'before'
                      : fromDate
                        ? 'after'
                        : 'range',
            );
            setDisplayMonth(fromDate ?? toDate ?? new Date());
        }
    };

    const trigger = (
        <Button
            variant="outline"
            className="max-w-full"
            aria-label={
                from || to ? `${placeholder}: ${selection}` : placeholder
            }
        >
            <CalendarIcon data-icon="inline-start" />
            <span className="max-w-48 truncate sm:max-w-64">{selection}</span>
        </Button>
    );

    const controls = (
        <>
            <ToggleGroup
                type="single"
                value={mode}
                onValueChange={(value) => {
                    if (
                        value === 'range' ||
                        value === 'before' ||
                        value === 'after'
                    ) {
                        setMode(value);
                        setDraft(undefined);
                    }
                }}
                variant="outline"
                size="sm"
                aria-label="Date filter mode"
                className="mx-3 mt-3"
            >
                <ToggleGroupItem value="range">Range</ToggleGroupItem>
                <ToggleGroupItem value="before">Before</ToggleGroupItem>
                <ToggleGroupItem value="after">After</ToggleGroupItem>
            </ToggleGroup>
            {mode === 'range' ? (
                <Calendar
                    mode="range"
                    selected={draft}
                    onSelect={setDraft}
                    month={displayMonth}
                    onMonthChange={setDisplayMonth}
                />
            ) : (
                <Calendar
                    mode="single"
                    selected={mode === 'before' ? draft?.to : draft?.from}
                    onSelect={(date) =>
                        setDraft(
                            mode === 'before'
                                ? { from: undefined, to: date }
                                : { from: date, to: undefined },
                        )
                    }
                    month={displayMonth}
                    onMonthChange={setDisplayMonth}
                />
            )}
            <div className="flex items-center justify-between border-t p-3">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        onSelect({ from: '', to: '' });
                        setOpen(false);
                    }}
                >
                    Clear
                </Button>
                <Button
                    type="button"
                    size="sm"
                    disabled={!draft?.from && !draft?.to}
                    onClick={apply}
                >
                    Apply
                </Button>
            </div>
        </>
    );

    if (isMobile) {
        return (
            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogTrigger asChild>{trigger}</DialogTrigger>
                <DialogContent className="max-h-[calc(100dvh-2rem)] w-auto max-w-[calc(100vw-2rem)] gap-0 overflow-y-auto p-0">
                    <DialogHeader className="px-3 pt-3 text-left">
                        <DialogTitle className="pr-8 text-base">
                            {placeholder}
                        </DialogTitle>
                        <DialogDescription>
                            Choose a date or a range.
                        </DialogDescription>
                    </DialogHeader>
                    {controls}
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>{trigger}</PopoverTrigger>
            <PopoverContent align="start" className="w-auto p-0">
                <PopoverHeader className="p-3 pb-0">
                    <PopoverTitle>{placeholder}</PopoverTitle>
                    <PopoverDescription>
                        Choose a date or a range.
                    </PopoverDescription>
                </PopoverHeader>
                {controls}
            </PopoverContent>
        </Popover>
    );
}
