import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ShieldCheck } from 'lucide-react';

interface ComplianceGaugeProps {
    pass: number;
    fail: number;
    needsRepair: number;
    total: number;
}

const COLORS = {
    pass: 'var(--success)',
    fail: 'var(--destructive)',
    needsRepair: 'var(--warning)',
};

export function ComplianceGauge({
    pass,
    fail,
    needsRepair,
    total,
}: ComplianceGaugeProps) {
    const percentage = total > 0 ? Math.round((pass / total) * 100) : 0;

    const gaugeColor =
        percentage >= 80
            ? COLORS.pass
            : percentage >= 60
              ? COLORS.needsRepair
              : COLORS.fail;

    const hasData = total > 0;

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b py-3">
                <CardTitle className="flex items-center gap-2 text-base leading-7">
                    <span className="flex size-7 items-center justify-center rounded-md bg-success/10">
                        <ShieldCheck
                            className="size-4 text-success"
                            aria-hidden="true"
                        />
                    </span>
                    Inspection Compliance
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col items-center justify-center py-4">
                {hasData ? (
                    <div className="flex w-full flex-col">
                        <div className="relative mx-auto h-28 w-48">
                            <svg
                                width={192}
                                height={112}
                                viewBox="0 0 192 112"
                                role="img"
                                aria-label={`${percentage}% inspection pass rate`}
                            >
                                <path
                                    d="M 26 96 A 70 70 0 0 1 166 96"
                                    fill="none"
                                    stroke={COLORS.fail}
                                    strokeWidth={20}
                                />
                                <path
                                    d="M 26 96 A 70 70 0 0 1 166 96"
                                    fill="none"
                                    stroke={COLORS.pass}
                                    strokeWidth={20}
                                    pathLength={100}
                                    strokeDasharray={`${percentage} 100`}
                                />
                            </svg>
                            <div className="absolute inset-x-0 bottom-0 flex flex-col items-center">
                                <span
                                    className="text-3xl font-bold tracking-tight"
                                    style={{ color: gaugeColor }}
                                >
                                    {percentage}%
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    pass rate
                                </span>
                            </div>
                        </div>
                        <div className="mt-4 flex w-full flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-1.5">
                                        <span
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor: COLORS.pass,
                                            }}
                                        />
                                        <span className="text-muted-foreground">
                                            Pass
                                        </span>
                                    </div>
                                    <span className="font-medium tabular-nums">
                                        {pass}
                                    </span>
                                </div>
                                <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full transition-all duration-500"
                                        style={{
                                            width:
                                                total > 0
                                                    ? `${(pass / total) * 100}%`
                                                    : '0%',
                                            backgroundColor: COLORS.pass,
                                        }}
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-1.5">
                                        <span
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor: COLORS.fail,
                                            }}
                                        />
                                        <span className="text-muted-foreground">
                                            Fail
                                        </span>
                                    </div>
                                    <span className="font-medium tabular-nums">
                                        {fail}
                                    </span>
                                </div>
                                <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full transition-all duration-500"
                                        style={{
                                            width:
                                                total > 0
                                                    ? `${(fail / total) * 100}%`
                                                    : '0%',
                                            backgroundColor: COLORS.fail,
                                        }}
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-1.5">
                                        <span
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    COLORS.needsRepair,
                                            }}
                                        />
                                        <span className="text-muted-foreground">
                                            Needs repair
                                        </span>
                                    </div>
                                    <span className="font-medium tabular-nums">
                                        {needsRepair}
                                    </span>
                                </div>
                                <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full transition-all duration-500"
                                        style={{
                                            width:
                                                total > 0
                                                    ? `${(needsRepair / total) * 100}%`
                                                    : '0%',
                                            backgroundColor: COLORS.needsRepair,
                                        }}
                                    />
                                </div>
                            </div>
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            {total} total inspections
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center py-8">
                        <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-muted">
                            <ShieldCheck className="size-6 text-muted-foreground" />
                        </div>
                        <p className="text-sm font-medium">
                            No inspections yet
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Compliance data will appear here
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
