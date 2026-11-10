import { Radar } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <Radar className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-bold">SIFI</span>
                <span className="truncate text-[10px] text-muted-foreground">
                    Strategic Intelligence
                </span>
            </div>
        </>
    );
}
