import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, ClipboardCheck, Flame, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, login } from '@/routes';

const features = [
    {
        icon: Building2,
        title: 'Clients & Sites',
        description:
            'Keep your PNG businesses and their fire safety sites together.',
    },
    {
        icon: Flame,
        title: 'Asset Tracking',
        description:
            'Track equipment, service dates, and assets requiring attention.',
    },
    {
        icon: ClipboardCheck,
        title: 'Inspections',
        description:
            'Log results, assign technicians, and retain inspection history.',
    },
    {
        icon: ShieldCheck,
        title: 'Compliance',
        description:
            'Monitor certificates, expiry dates, and compliance alerts.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;
    return (
        <>
            <Head title="FireGuard" />
            <div className="flex min-h-screen flex-col bg-background">
                <header className="flex items-center justify-between gap-4 border-b px-6 py-4">
                    <Link
                        href={auth.user ? dashboard() : login()}
                        className="flex items-center gap-2 font-semibold"
                    >
                        <Flame className="size-6 text-warning" />
                        FireGuard
                    </Link>
                    <nav className="flex gap-2" aria-label="Account navigation">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <Button variant="ghost" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                        )}
                    </nav>
                </header>
                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-10 px-6 py-16">
                    <div className="flex max-w-2xl flex-col gap-5">
                        <span className="text-sm font-medium text-warning">
                            Fire Safety Management
                        </span>
                        <h1 className="text-4xl leading-tight font-bold tracking-tight sm:text-5xl">
                            Your fire safety operations, in one place.
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Manage clients, equipment, inspections, and
                            compliance certificates across Papua New Guinea.
                        </p>
                        <div>
                            <Button size="lg" asChild>
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user
                                        ? 'Open dashboard'
                                        : 'Log in to FireGuard'}
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature) => (
                            <Card key={feature.title}>
                                <CardHeader>
                                    <feature.icon className="mb-2 size-6 text-warning" />
                                    <CardTitle>{feature.title}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription>
                                        {feature.description}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </main>
            </div>
        </>
    );
}
