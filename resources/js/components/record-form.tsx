import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
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
        | 'url'
        | 'tel'
        | 'date'
        | 'textarea'
        | 'select'
        | 'checkbox';
    required?: boolean;
    options?: { value: string; label: string }[];
    placeholder?: string;
    description?: string;
    maxLength?: number;
    autoComplete?: string;
    afterOrEqual?: string;
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
    if (value && field.type === 'url' && !/^https?:\/\//i.test(value)) {
        return `${field.label} must start with http:// or https://.`;
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
    if (
        value &&
        field.afterOrEqual &&
        values[field.afterOrEqual] &&
        value < values[field.afterOrEqual]
    ) {
        const previousLabel = fields.find(
            (other) => other.name === field.afterOrEqual,
        )?.label;
        return `${field.label} must be on or after ${previousLabel ?? 'the earlier date'}.`;
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
    onSuccess,
    onCancel,
}: {
    fields: FormField[];
    initialValues: Record<string, string>;
    action: RouteDefinition<'post' | 'put' | 'patch'>;
    saveLabel: string;
    onSuccess: () => void;
    onCancel: () => void;
}) {
    const form = useForm(initialValues);
    const formRef = useRef<HTMLFormElement>(null);
    const serverErrorFields = useRef(new Set<string>());
    const [checkedFields, setCheckedFields] = useState<string[]>([]);
    const [submitted, setSubmitted] = useState(false);
    const visibleFields = new Set(fields.map((field) => field.name));
    const otherErrors = Object.entries(form.errors).filter(
        ([name]) => !visibleFields.has(name),
    );
    const focusFirstError = () => {
        requestAnimationFrame(() => {
            (
                formRef.current?.querySelector<HTMLElement>(
                    '[aria-invalid="true"]',
                ) ??
                formRef.current?.querySelector<HTMLElement>('[data-form-error]')
            )?.focus();
        });
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
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
            form.setError(errors);
            focusFirstError();
            return;
        }
        form.submit(action, {
            preserveScroll: true,
            onError: (errors) => {
                serverErrorFields.current = new Set(Object.keys(errors));
                focusFirstError();
            },
            onSuccess: () => {
                form.reset();
                onSuccess();
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
            <FieldGroup className="min-h-0 overflow-y-auto p-1">
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
                    const error = form.errors[field.name];
                    const length = Array.from(
                        form.data[field.name] ?? '',
                    ).length;
                    const showLimit =
                        field.maxLength !== undefined &&
                        length >= Math.max(1, field.maxLength - 20);
                    const describedBy =
                        [
                            field.description ? `${id}-description` : null,
                            showLimit ? `${id}-limit` : null,
                            error ? `${id}-error` : null,
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined;
                    const change = (value: string) => {
                        serverErrorFields.current.delete(field.name);
                        form.setData(field.name, value);
                        if (
                            submitted ||
                            checkedFields.includes(field.name) ||
                            (field.maxLength &&
                                Array.from(value).length > field.maxLength)
                        ) {
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
                        } else if (!serverErrorFields.current.has(field.name)) {
                            form.clearErrors(field.name);
                        }
                    };
                    const blur = () => {
                        setCheckedFields((current) =>
                            current.includes(field.name)
                                ? current
                                : [...current, field.name],
                        );
                        const input = formRef.current?.querySelector<
                            HTMLInputElement | HTMLTextAreaElement
                        >(`#${id}`);
                        const error = validateField(
                            field,
                            form.data,
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
                                    onChange={(event) =>
                                        change(event.target.value)
                                    }
                                    onBlur={blur}
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
                                    onChange={(event) =>
                                        change(event.target.value)
                                    }
                                    onBlur={blur}
                                    placeholder={field.placeholder}
                                    aria-required={field.required}
                                    required={field.required}
                                    aria-invalid={!!error}
                                    aria-describedby={describedBy}
                                    autoComplete={field.autoComplete}
                                />
                            )}
                            {field.description && (
                                <FieldDescription id={`${id}-description`}>
                                    {field.description}
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
            </FieldGroup>
            <div className="mt-auto flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    {saveLabel}
                </Button>
            </div>
        </form>
    );
}
