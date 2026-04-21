import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { Bell, ChevronRight, LogOut, Menu, Search, User } from 'lucide-react';
import { cn } from '@/lib/cn';
import { useAuth } from '@/lib/auth';
import { initials } from '@/lib/format';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { CommandPalette } from './command-palette';

interface TopbarProps {
  onMobileMenuOpen: () => void;
}

export function Topbar({ onMobileMenuOpen }: TopbarProps) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const crumbs = breadcrumbsFor(location.pathname);
  const [paletteOpen, setPaletteOpen] = useState(false);

  // Global ⌘K / Ctrl+K opens the command palette. Captured at the window
  // level so it works regardless of focus, but yields if a text input is
  // already capturing the same combo (none today, but safer).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPaletteOpen((v) => !v);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  return (
    <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-hairline bg-elevated/80 px-3 backdrop-blur sm:px-5">
      {/* Mobile-only hamburger — sized 44×44 to meet Apple HIG touch-target
          guidance (CLAUDE.md UX rule, stricter than WCAG 2.2 AA's 24×24).
          Sidebar's own in-drawer buttons are already comfortably sized; this
          is the single entry point to navigation on mobile and was the
          reported pain point. Icon bumped to size-6 for visibility. */}
      <Button
        variant="ghost"
        className="size-11 p-0 [&_svg]:size-6 lg:hidden"
        onClick={onMobileMenuOpen}
        aria-label="Open navigation"
      >
        <Menu />
      </Button>

      <nav aria-label="Breadcrumb" className="hidden min-w-0 flex-1 items-center gap-1.5 text-[13px] sm:flex">
        {crumbs.map((c, i) => (
          <div key={i} className="flex min-w-0 items-center gap-1.5">
            {i > 0 && <ChevronRight className="size-3.5 shrink-0 text-fg-subtle" />}
            <span className={cn('truncate', i === crumbs.length - 1 ? 'font-medium text-fg' : 'text-fg-muted')}>
              {c}
            </span>
          </div>
        ))}
      </nav>

      <div className="flex flex-1 items-center justify-end gap-1.5 sm:flex-none">
        {/* Desktop: full-width search trigger with label + ⌘K hint.
            Mobile: icon-only button — same palette, same tap target. */}
        <Button
          variant="ghost"
          size="sm"
          className="hidden h-9 w-64 justify-start gap-2 px-3 text-fg-subtle ring-1 ring-inset ring-hairline hover:bg-surface md:inline-flex"
          onClick={() => setPaletteOpen(true)}
        >
          <Search className="size-3.5" />
          <span className="text-[13px] font-normal">Jump to a page…</span>
          <kbd className="ml-auto rounded border border-hairline bg-surface px-1 py-px font-mono text-[10px] text-fg-subtle">⌘K</kbd>
        </Button>
        <Button
          variant="ghost"
          size="icon"
          className="md:hidden"
          aria-label="Open command palette"
          onClick={() => setPaletteOpen(true)}
        >
          <Search className="size-4" />
        </Button>

        <Button variant="ghost" size="icon" aria-label="Notifications" className="relative">
          <Bell className="size-4" />
          <span className="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-accent-cyan ring-2 ring-elevated" />
        </Button>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              className="flex items-center gap-2 rounded-lg px-1.5 py-1 transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
              aria-label="User menu"
            >
              <Avatar className="size-8">
                <AvatarFallback>{initials(typeof user?.name === 'string' ? user.name : '')}</AvatarFallback>
              </Avatar>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="min-w-[12rem]">
            <DropdownMenuLabel>
              <div className="truncate text-[13px] font-medium text-fg">
                {(typeof user?.name === 'string' && user.name) || 'User'}
              </div>
              <div className="truncate text-[11px] font-normal text-fg-muted">
                {(typeof user?.email === 'string' && user.email) || ''}
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={() => navigate('/_design')}>
              <User className="size-3.5" /> Design system
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              destructive
              onSelect={() => {
                logout();
                navigate('/login');
              }}
            >
              <LogOut className="size-3.5" /> Sign out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} />
    </header>
  );
}

function breadcrumbsFor(pathname: string): string[] {
  if (pathname === '/') return ['Dashboard'];
  const parts = pathname.split('/').filter(Boolean);
  return ['Home', ...parts.map((p) => p.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()))];
}
