import { useForm } from '@inertiajs/react';
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
};

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
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="flex min-h-0 flex-1 flex-col gap-6"
            noValidate
        >
            <FieldGroup className="min-h-0 overflow-y-auto p-1">
                {fields.map((field) => {
                    const id = `record-${field.name}`;
                    const error = form.errors[field.name];
                    const describedBy =
                        [
                            field.description ? `${id}-description` : null,
                            error ? `${id}-error` : null,
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined;
                    const change = (value: string) => {
                        form.setData(field.name, value);
                        form.clearErrors(field.name);
                    };
                    return (
                        <Field key={field.name} data-invalid={!!error}>
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
                                <Checkbox
                                    id={id}
                                    checked={form.data[field.name] === '1'}
                                    onCheckedChange={(checked) =>
                                        change(checked ? '1' : '0')
                                    }
                                    aria-invalid={!!error}
                                />
                            ) : field.type === 'textarea' ? (
                                <Textarea
                                    id={id}
                                    value={form.data[field.name] ?? ''}
                                    onChange={(event) =>
                                        change(event.target.value)
                                    }
                                    placeholder={field.placeholder}
                                    aria-invalid={!!error}
                                    aria-describedby={describedBy}
                                    rows={3}
                                />
                            ) : (
                                <Input
                                    id={id}
                                    type={field.type || 'text'}
                                    value={form.data[field.name] ?? ''}
                                    onChange={(event) =>
                                        change(event.target.value)
                                    }
                                    placeholder={field.placeholder}
                                    aria-required={field.required}
                                    aria-invalid={!!error}
                                    aria-describedby={describedBy}
                                />
                            )}
                            {field.description && (
                                <FieldDescription id={`${id}-description`}>
                                    {field.description}
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
