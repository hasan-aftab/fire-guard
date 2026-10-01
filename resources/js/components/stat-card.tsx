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
    red: 'text-destructive',
    amber: 'text-warning',
    green: 'text-success',
    blue: 'text-info',
};

const iconBgStyles = {
    default: 'bg-muted',
    red: 'bg-destructive/10',
    amber: 'bg-warning/10',
    green: 'bg-success/10',
    blue: 'bg-info/10',
};

const iconColorStyles = {
    default: 'text-muted-foreground',
    red: 'text-destructive',
    amber: 'text-warning',
    green: 'text-success',
    blue: 'text-info',
};

const cardToneStyles = {
    default: '',
    red: 'border-destructive/20',
    amber: 'border-warning/20',
    green: 'border-success/20',
    blue: 'border-info/20',
};

export function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'default',
    description,
}: StatCardProps) {
    return (
        <Card className={cn('gap-2 py-4', cardToneStyles[tone])}>
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
