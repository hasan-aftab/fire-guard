import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { RouteDefinition } from '@/wayfinder';

export type FormField = {
    name: string;
    label: string;
    type?:
        | 'text'
        | 'email'
        | 'tel'
        | 'date'
        | 'textarea'
        | 'select'
        | 'checkbox';
    required?: boolean;
    options?: { value: string; label: string }[];
    placeholder?: string;
    description?: string | ((values: Record<string, string>) => string);
    maxLength?: number;
    autoComplete?: string;
    afterOrEqual?: string | string[];
};

function validateField(
    field: FormField,
    values: Record<string, string>,
    fields: FormField[],
    input?: HTMLInputElement | HTMLTextAreaElement | null,
): string | undefined {
    const value = (values[field.name] ?? '').trim();
    if (input instanceof HTMLInputElement && input.validity.badInput) {
        return `Enter a valid ${field.label.toLowerCase()}.`;
    }
    if (field.required && !value) {
        return `${field.label} is required.`;
    }
    if (field.maxLength && Array.from(value).length > field.maxLength) {
        return `${field.label} must be ${field.maxLength} characters or fewer.`;
    }
    if (
        value &&
        input instanceof HTMLInputElement &&
        input.validity.typeMismatch
    ) {
        return `Enter a valid ${field.label.toLowerCase()}.`;
    }
    if (
        value &&
        field.type === 'tel' &&
        !/^(?=(?:\D*\d){7,20}\D*$)\+?(?:\d+|\(\d+\))(?:[ .-]*(?:\d+|\(\d+\)))*(?:\s*(?:x|ext\.?)\s*\d+)?$/i.test(
            value,
        )
    ) {
        return `Enter a valid ${field.label.toLowerCase()} with 7 to 20 digits.`;
    }
    const previousFields = field.afterOrEqual
        ? [field.afterOrEqual].flat()
        : [];
    for (const previousField of previousFields) {
        if (value && values[previousField] && value < values[previousField]) {
            const previousLabel = fields.find(
                (other) => other.name === previousField,
            )?.label;
            return `${field.label} must be on or after ${previousLabel ?? 'the earlier date'}.`;
        }
    }
    return undefined;
}

export function recordValues<T extends object>(
    record: T,
    defaults: Record<string, string>,
): Record<string, string> {
    return Object.fromEntries(
        Object.keys(defaults).map((key) => {
            const value = record[key as keyof T];
            return [
                key,
                typeof value === 'boolean'
                    ? value
                        ? '1'
                        : '0'
                    : String(value ?? ''),
            ];
        }),
    );
}

export function RecordForm({
    fields,
    initialValues,
    action,
    saveLabel,
    submitExtra,
    onSuccess,
    onCancel,
    locked = false,
    onProcessingChange,
    children,
}: {
    fields: FormField[];
    initialValues: Record<string, string>;
    action: RouteDefinition<'post' | 'put' | 'patch'>;
    saveLabel: string;
    submitExtra?: Record<string, string>;
    onSuccess: (createdId?: number) => void;
    onCancel: () => void;
    locked?: boolean;
    onProcessingChange?: (processing: boolean) => void;
    children?: ReactNode;
}) {
    const form = useForm(initialValues);
    const formRef = useRef<HTMLFormElement>(null);
    const shouldFocusErrors = useRef(false);
    const visibleFields = new Set(fields.map((field) => field.name));
    const otherErrors = Object.entries(form.errors).filter(
        ([name]) => !visibleFields.has(name),
    );
    useEffect(() => {
        if (!shouldFocusErrors.current || !form.hasErrors) return;
        shouldFocusErrors.current = false;
        const frame = requestAnimationFrame(() => {
            (
                formRef.current?.querySelector<HTMLElement>(
                    '[aria-invalid="true"]',
                ) ??
                formRef.current?.querySelector<HTMLElement>('[data-form-error]')
            )?.focus();
        });
        return () => cancelAnimationFrame(frame);
    }, [form.errors, form.hasErrors]);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (locked || form.processing) return;
        const errors: Record<string, string> = {};
        for (const field of fields) {
            const input = formRef.current?.querySelector<
                HTMLInputElement | HTMLTextAreaElement
            >(`#record-${field.name}`);
            const error = validateField(field, form.data, fields, input);
            if (error) {
                errors[field.name] = error;
            }
        }
        if (Object.keys(errors).length > 0) {
            shouldFocusErrors.current = true;
            form.setError(errors);
            return;
        }
        form.transform((data) => ({ ...data, ...submitExtra }));
        form.submit(action, {
            preserveScroll: true,
            onStart: () => onProcessingChange?.(true),
            onFinish: () => onProcessingChange?.(false),
            onError: () => {
                shouldFocusErrors.current = true;
            },
            onSuccess: (page) => {
                const id = page.flash.createdRecordId;
                onSuccess(typeof id === 'number' ? id : undefined);
            },
        });
    };

    return (
        <form
            ref={formRef}
            onSubmit={submit}
            className="flex min-h-0 flex-1 flex-col gap-6"
            noValidate
        >
            <FieldGroup className="min-h-0 gap-5 overflow-y-auto p-1 pr-2">
                {otherErrors.length > 0 && (
                    <div
                        data-form-error
                        tabIndex={-1}
                        role="alert"
                        className="rounded-md border border-destructive p-3 text-sm text-destructive"
                    >
                        {otherErrors.map(([name, message]) => (
                            <p key={name}>{message}</p>
                        ))}
                    </div>
                )}
                {fields.map((field) => {
                    const id = `record-${field.name}`;
                    const description =
                        typeof field.description === 'function'
                            ? field.description(form.data)
                            : field.description;
                    const error = form.errors[field.name];
                    const length = Array.from(
                        form.data[field.name] ?? '',
                    ).length;
                    const showLimit =
                        field.maxLength !== undefined &&
                        length >= Math.max(1, field.maxLength - 20);
                    const describedBy =
                        [
                            description ? `${id}-description` : null,
                            showLimit ? `${id}-limit` : null,
                            error ? `${id}-error` : null,
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined;
                    const change = (value: string) => {
                        form.setData(field.name, value);
                        const input = formRef.current?.querySelector<
                            HTMLInputElement | HTMLTextAreaElement
                        >(`#${id}`);
                        const error = validateField(
                            field,
                            { ...form.data, [field.name]: value },
                            fields,
                            input,
                        );
                        if (error) {
                            form.setError(field.name, error);
                        } else {
                            form.clearErrors(field.name);
                        }
                    };
                    return (
                        <Field key={field.name} data-invalid={!!error}>
                            {field.type !== 'checkbox' && (
                                <FieldLabel htmlFor={id}>
                                    {field.label}
                                    {field.required && (
                                        <span
                                            aria-hidden="true"
                                            className="text-destructive"
                                        >
                                            {' '}
                                            *
                                        </span>
                                    )}
                                </FieldLabel>
                            )}
                            {field.type === 'select' ? (
                                <Select
                                    value={form.data[field.name] || undefined}
                                    onValueChange={change}
                                    disabled={locked}
                                >
                                    <SelectTrigger
                                        id={id}
                                        aria-required={field.required}
                                        aria-invalid={!!error}
                                        aria-describedby={describedBy}
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={
                                                field.placeholder ||
                                                `Select ${field.label.toLowerCase()}`
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {field.options?.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            ) : field.type === 'checkbox' ? (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={id}
                                        checked={form.data[field.name] === '1'}
                                        disabled={locked}
                                        onCheckedChange={(checked) =>
                                            change(checked ? '1' : '0')
                                        }
                                        aria-invalid={!!error}
                                        aria-describedby={describedBy}
                                    />
                                    <FieldLabel htmlFor={id}>
                                        {field.label}
                                    </FieldLabel>
                                </div>
                            ) : field.type === 'textarea' ? (
                                <Textarea
                                    id={id}
                                    value={form.data[field.name] ?? ''}
                                    disabled={locked}
                                    onChange={(event) =>
                                        change(event.target.value)
                                    }
                                    placeholder={field.placeholder}
                                    aria-invalid={!!error}
                                    aria-describedby={describedBy}
                                    aria-required={field.required}
                                    required={field.required}
                                    rows={3}
                                    autoComplete={field.autoComplete}
                                />
                            ) : (
                                <Input
                                    id={id}
                                    type={field.type || 'text'}
                                    value={form.data[field.name] ?? ''}
                                    disabled={locked}
                                    onChange={(event) =>
                                        change(event.target.value)
                                    }
                                    placeholder={field.placeholder}
                                    aria-required={field.required}
                                    required={field.required}
                                    aria-invalid={!!error}
                                    aria-describedby={describedBy}
                                    autoComplete={field.autoComplete}
                                />
                            )}
                            {description && (
                                <FieldDescription id={`${id}-description`}>
                                    {description}
                                </FieldDescription>
                            )}
                            {showLimit && (
                                <FieldDescription id={`${id}-limit`}>
                                    {length} of {field.maxLength} characters
                                </FieldDescription>
                            )}
                            <FieldError id={`${id}-error`}>{error}</FieldError>
                        </Field>
                    );
                })}
                {children}
            </FieldGroup>
            <div className="flex shrink-0 justify-end gap-2 border-t pt-4">
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing || locked}
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing || locked}>
                    {(form.processing || locked) && (
                        <Spinner data-icon="inline-start" />
                    )}
                    {saveLabel}
                </Button>
            </div>
        </form>
    );
}
