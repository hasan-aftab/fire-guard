import { format, isValid, parseISO } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
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
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverDescription,
    PopoverHeader,
    PopoverTitle,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useIsMobile } from '@/hooks/use-mobile';

function parseDate(value: string): Date | undefined {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return undefined;
    const date = parseISO(value);
    return isValid(date) && format(date, 'yyyy-MM-dd') === value
        ? date
        : undefined;
}

export function DatePicker({
    id,
    label,
    value,
    onChange,
    required = false,
    disabled = false,
    invalid = false,
    describedBy,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
}) {
    const isMobile = useIsMobile();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(value);
    const [displayMonth, setDisplayMonth] = useState(
        parseDate(value) ?? new Date(),
    );
    const [inputError, setInputError] = useState(false);
    const selected = parseDate(value);
    const selectedYear = selected?.getFullYear() ?? new Date().getFullYear();

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);
        if (nextOpen) {
            setDraft(value);
            setInputError(false);
            setDisplayMonth(selected ?? new Date());
        }
    };
    const choose = (date: Date) => {
        onChange(format(date, 'yyyy-MM-dd'));
        setOpen(false);
    };
    const applyTypedDate = () => {
        const date = parseDate(draft);
        if (!date) {
            setInputError(true);
            return;
        }
        choose(date);
    };
    const display = selected ? format(selected, 'PPP') : value || 'Select date';
    const trigger = (
        <Button
            id={id}
            type="button"
            variant="outline"
            disabled={disabled}
            aria-label={`${label}: ${display}`}
            aria-required={required}
            aria-invalid={invalid}
            aria-describedby={describedBy}
            className="w-full justify-start text-left font-normal"
        >
            <CalendarIcon data-icon="inline-start" />
            <span className="truncate">{display}</span>
        </Button>
    );
    const controls = (
        <>
            <Calendar
                mode="single"
                selected={selected}
                onSelect={(date) => date && choose(date)}
                month={displayMonth}
                onMonthChange={setDisplayMonth}
                captionLayout="dropdown"
                startMonth={new Date(Math.min(1900, selectedYear - 10), 0)}
                endMonth={
                    new Date(
                        Math.max(
                            new Date().getFullYear() + 50,
                            selectedYear + 10,
                        ),
                        11,
                    )
                }
            />
            <div className="space-y-2 border-t p-3">
                <label
                    htmlFor={`${id}-entry`}
                    className="text-xs font-medium text-muted-foreground"
                >
                    Enter date (YYYY-MM-DD)
                </label>
                <Input
                    id={`${id}-entry`}
                    type="text"
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={10}
                    placeholder="YYYY-MM-DD"
                    value={draft}
                    aria-invalid={inputError}
                    aria-describedby={
                        inputError ? `${id}-entry-error` : undefined
                    }
                    onChange={(event) => {
                        const nextValue = event.target.value;
                        setDraft(nextValue);
                        setInputError(false);
                        const date = parseDate(nextValue);
                        if (date) setDisplayMonth(date);
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            applyTypedDate();
                        }
                    }}
                />
                {inputError && (
                    <p
                        id={`${id}-entry-error`}
                        role="alert"
                        className="text-xs text-destructive"
                    >
                        Enter a valid date as YYYY-MM-DD.
                    </p>
                )}
                <div className="flex justify-between gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            onChange('');
                            setOpen(false);
                        }}
                    >
                        Clear
                    </Button>
                    <Button type="button" size="sm" onClick={applyTypedDate}>
                        Apply
                    </Button>
                </div>
            </div>
        </>
    );

    if (isMobile) {
        return (
            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogTrigger asChild>{trigger}</DialogTrigger>
                <DialogContent className="z-[70] max-h-[calc(100dvh-2rem)] w-auto max-w-[calc(100vw-2rem)] gap-0 overflow-y-auto p-0">
                    <DialogHeader className="px-3 pt-3 text-left">
                        <DialogTitle className="pr-8 text-base">
                            {label}
                        </DialogTitle>
                        <DialogDescription>
                            Choose a date or enter YYYY-MM-DD.
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
            <PopoverContent align="start" className="z-[60] w-auto p-0">
                <PopoverHeader className="p-3 pb-0">
                    <PopoverTitle>{label}</PopoverTitle>
                    <PopoverDescription>
                        Choose a date or enter YYYY-MM-DD.
                    </PopoverDescription>
                </PopoverHeader>
                {controls}
            </PopoverContent>
        </Popover>
    );
}
