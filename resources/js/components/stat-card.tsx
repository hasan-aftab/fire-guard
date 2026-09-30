import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface StatCardProps {
    label: string;
    value: number | string;
    icon?: LucideIcon;
    tone?: 'default' | 'red' | 'amber' | 'green' | 'blue';
    description?: string;
}

const toneStyles = {
    default: 'text-foreground',
    red: 'text-red-600 dark:text-red-400',
    amber: 'text-amber-600 dark:text-amber-400',
    green: 'text-emerald-600 dark:text-emerald-400',
    blue: 'text-blue-600 dark:text-blue-400',
};

const iconBgStyles = {
    default: 'bg-muted',
    red: 'bg-red-50 dark:bg-red-950/30',
    amber: 'bg-amber-50 dark:bg-amber-950/30',
    green: 'bg-emerald-50 dark:bg-emerald-950/30',
    blue: 'bg-blue-50 dark:bg-blue-950/30',
};

const iconColorStyles = {
    default: 'text-muted-foreground',
    red: 'text-red-500 dark:text-red-400',
    amber: 'text-amber-500 dark:text-amber-400',
    green: 'text-emerald-500 dark:text-emerald-400',
    blue: 'text-blue-500 dark:text-blue-400',
};

const cardToneStyles = {
    default: '',
    red: 'border-red-200/60 dark:border-red-900/40',
    amber: 'border-amber-200/60 dark:border-amber-900/40',
    green: 'border-emerald-200/60 dark:border-emerald-900/40',
    blue: 'border-blue-200/60 dark:border-blue-900/40',
};

export function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'default',
    description,
}: StatCardProps) {
    return (
        <Card className={cn('gap-3', cardToneStyles[tone])}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
                {Icon && (
                    <div
                        className={cn(
                            'flex size-10 items-center justify-center rounded-lg',
                            iconBgStyles[tone],
                        )}
                    >
                        <Icon className={cn('size-5', iconColorStyles[tone])} />
                    </div>
                )}
            </CardHeader>
            <CardContent>
                <div
                    className={cn(
                        'text-3xl font-bold tracking-tight',
                        toneStyles[tone],
                    )}
                >
                    {value}
                </div>
                {description && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
