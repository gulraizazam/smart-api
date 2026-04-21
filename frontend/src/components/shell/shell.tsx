import { useEffect, useState } from 'react';
import { Outlet } from 'react-router';
import { cn } from '@/lib/cn';
import { Sidebar } from './sidebar';
import { Topbar } from './topbar';

const COLLAPSED_KEY = 'cutera.sidebar.collapsed';

/* Sidebar collapse state lives here — both the fixed-position aside and
   the main content's left offset need to read it so they stay in sync.
   Previously the Sidebar owned it internally and the content area
   hard-coded 260px, which left a huge empty gutter when collapsed. */
export function Shell() {
  const [mobileOpen, setMobileOpen] = useState(false);

  const [collapsed, setCollapsed] = useState<boolean>(() => {
    try { return localStorage.getItem(COLLAPSED_KEY) === '1'; } catch { return false; }
  });

  useEffect(() => {
    try { localStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0'); } catch { /* ignore */ }
  }, [collapsed]);

  return (
    <div className="flex h-full bg-surface">
      <Sidebar
        mobileOpen={mobileOpen}
        onMobileClose={() => setMobileOpen(false)}
        collapsed={collapsed}
        onCollapsedChange={setCollapsed}
      />

      <div
        className={cn(
          'flex min-w-0 flex-1 flex-col transition-[padding-left] duration-200 ease-out',
          collapsed ? 'lg:pl-[68px]' : 'lg:pl-[260px]',
        )}
      >
        <Topbar onMobileMenuOpen={() => setMobileOpen(true)} />
        <main className="flex-1 overflow-x-hidden">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
