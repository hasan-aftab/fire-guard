import { Flame } from 'lucide-react';
export default function AppLogo() {
    return (
        <>
            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary/15">
                <Flame className="size-5 text-sidebar-primary" />
            </div>
            <div className="flex min-w-0 flex-col text-left">
                <span className="truncate text-sm leading-tight font-semibold text-sidebar-foreground">
                    FireTrack PNG
                </span>
                <span className="text-[11px] leading-tight text-sidebar-foreground/50">
                    Fire Safety Management
                </span>
            </div>
        </>
    );
}
