import type { LucideIcon } from 'lucide-react';
import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const options: { value: Appearance; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Terang', icon: Sun },
    { value: 'dark', label: 'Gelap', icon: Moon },
    { value: 'system', label: 'Ikuti sistem', icon: Monitor },
];

/**
 * Quick light/dark/system switch for the headers (same preference as
 * Pengaturan → Tampilan: localStorage + cookie, applied before first paint).
 */
export function AppearanceMenu({ className }: { className?: string }) {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const Icon = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={className}
                    aria-label="Mode tampilan"
                    title="Mode tampilan"
                >
                    <Icon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>Tampilan</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {options.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        onSelect={() => updateAppearance(option.value)}
                    >
                        <option.icon />
                        <span className="flex-1">{option.label}</span>
                        {appearance === option.value && (
                            <Check aria-label="Dipilih" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
