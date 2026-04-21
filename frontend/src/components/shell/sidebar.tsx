import { useEffect, useMemo, useRef, useState } from 'react';
import { NavLink, useLocation, useNavigate } from 'react-router';
import { ChevronRight, PanelLeftClose, PanelLeftOpen, Search } from 'lucide-react';
import { cn } from '@/lib/cn';
import { useAuth } from '@/lib/auth';
import { recordVisit } from '@/lib/page-frecency';
import { initials } from '@/lib/format';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { NAV, activeGroupForPath, isSubsection, type NavItem, type NavLeaf, type NavSubsection } from './sidebar-data';

interface SidebarProps {
  mobileOpen: boolean;
  onMobileClose: () => void;
  /* Collapsed state is owned by Shell so the main content's left offset
     can track the sidebar width. The sidebar renders based on the
     incoming value and asks the parent to toggle it. */
  collapsed: boolean;
  onCollapsedChange: (next: boolean) => void;
}

export function Sidebar({ mobileOpen, onMobileClose, collapsed, onCollapsedChange }: SidebarProps) {
  const { user } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const searchRef = useRef<HTMLInputElement>(null);

  const [openGroup, setOpenGroup] = useState<string>(() => activeGroupForPath(location.pathname));
  const [query, setQuery] = useState('');

  useEffect(() => {
    setOpenGroup(activeGroupForPath(location.pathname));
  }, [location.pathname]);

  // ⌘K / Ctrl+K focuses the menu search.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        searchRef.current?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const filtered = useMemo(() => filterNav(NAV, query.trim().toLowerCase()), [query]);

  const userInitials = initials(typeof user?.name === 'string' ? user.name : '');
  const userRole = typeof user?.role === 'string' ? user.role : 'Member';
  const userName = typeof user?.name === 'string' ? user.name : 'User';

  return (
    <>
      {mobileOpen && (
        <div
          className="fixed inset-0 z-40 bg-fg/40 backdrop-blur-sm lg:hidden"
          onClick={onMobileClose}
          aria-hidden
        />
      )}
      <aside
        aria-label="Primary navigation"
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex flex-col bg-brand-gradient text-fg-on-dark transition-[width,transform] duration-200 ease-out',
          collapsed ? 'w-[68px]' : 'w-[260px]',
          'border-r border-white/5',
          mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        )}
      >
        {/* Brand */}
        <div className={cn(
          'flex h-14 shrink-0 items-center border-b border-white/5',
          collapsed ? 'justify-center px-2' : 'justify-between px-4',
        )}>
          {/* Collapsed state shows a compact mark; expanded renders the
              same `public/logo.png` asset the legacy sidebar does (served
              from the Laravel root, not Vite's base). Height-capped so
              the wordmark doesn't outgrow the h-14 header. */}
          {collapsed ? (
            <div
              aria-label="Cutera"
              className="flex size-7 items-center justify-center rounded-md bg-gradient-to-br from-brand-blue to-accent-cyan text-[11px] font-bold text-white"
            >
              C
            </div>
          ) : (
            <a href="/admin-v2/" className="inline-flex items-center" aria-label="Cutera home">
              {/* Explicit 120×auto to match legacy `.cs-brand-logo img`
                  (resources/views/admin/partials/sidebar-v2.blade.php:182).
                  Inline style beats any resets that may target the <img>
                  from a global CSS layer — Tailwind classes were being
                  overridden to natural size in practice. */}
              <img
                src="/logo.png"
                alt="Cutera"
                width={120}
                height={15}
                style={{ width: 120, height: 'auto' }}
                className="block select-none"
                draggable={false}
              />
            </a>
          )}
          <button
            type="button"
            onClick={() => onCollapsedChange(!collapsed)}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            aria-pressed={collapsed}
            className="hidden size-8 items-center justify-center rounded-md text-white/60 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue lg:inline-flex"
          >
            {collapsed ? <PanelLeftOpen className="size-4" /> : <PanelLeftClose className="size-4" />}
          </button>
        </div>

        {/* Search */}
        {!collapsed && (
          <div className="px-3 pb-2 pt-3">
            <label className="flex items-center gap-2 rounded-lg bg-white/5 px-2.5 py-1.5 ring-1 ring-inset ring-white/5 transition-colors focus-within:bg-white/10 focus-within:ring-brand-blue/40">
              <Search className="size-3.5 text-white/40" aria-hidden />
              <input
                ref={searchRef}
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Filter menu…"
                className="min-w-0 flex-1 bg-transparent text-[13px] text-white/90 placeholder:text-white/40 focus:outline-none"
                aria-label="Filter menu"
              />
            </label>
          </div>
        )}

        {/* Menu */}
        <nav
          className="flex-1 overflow-y-auto overflow-x-hidden px-2 pb-3 [scrollbar-width:thin] [scrollbar-color:rgb(255_255_255/0.12)_transparent]"
          aria-label="Main menu"
        >
          {filtered.map((item) => {
            if (item.type === 'link') {
              const Icon = item.icon;
              const active = location.pathname === item.to;
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end
                  onClick={() => {
                    if (item.implemented !== false) recordVisit(item.to);
                    onMobileClose();
                  }}
                  className={cn(
                    'group flex items-center gap-3 rounded-lg px-2.5 py-2 text-[13px] font-medium text-white/70 transition-colors hover:bg-white/5 hover:text-white',
                    active && 'bg-white/10 text-white shadow-[inset_3px_0_0_var(--color-accent-cyan)]',
                    collapsed && 'justify-center px-2',
                  )}
                  title={collapsed ? item.label : undefined}
                >
                  <Icon className={cn('size-[18px] shrink-0', active ? 'text-accent-cyan' : 'text-white/60')} />
                  {!collapsed && <span className="truncate">{item.label}</span>}
                  {!collapsed && item.implemented === false && <SoonPill />}
                </NavLink>
              );
            }

            const g = item.group;
            const Icon = g.icon;
            const isOpen = openGroup === g.key && !collapsed;
            const groupHasActive = activeGroupForPath(location.pathname) === g.key;

            return (
              <div key={g.key} className="mt-0.5">
                <button
                  type="button"
                  aria-expanded={isOpen}
                  onClick={() => {
                    if (collapsed) {
                      onCollapsedChange(false);
                      setOpenGroup(g.key);
                    } else {
                      setOpenGroup(openGroup === g.key ? '' : g.key);
                    }
                  }}
                  className={cn(
                    'flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-[13px] font-medium text-white/70 transition-colors hover:bg-white/5 hover:text-white',
                    (isOpen || groupHasActive) && 'text-white',
                    collapsed && 'justify-center px-2',
                  )}
                  title={collapsed ? g.label : undefined}
                >
                  <Icon className={cn('size-[18px] shrink-0', groupHasActive ? 'text-accent-cyan' : 'text-white/60')} />
                  {!collapsed && (
                    <>
                      <span className="flex-1 truncate text-start">{g.label}</span>
                      <ChevronRight
                        className={cn('size-3.5 text-white/40 transition-transform', isOpen && 'rotate-90 text-white/70')}
                        aria-hidden
                      />
                    </>
                  )}
                </button>

                {!collapsed && (
                  <div
                    className={cn(
                      'overflow-hidden transition-[max-height] duration-300 ease-out',
                      isOpen ? 'max-h-[2400px]' : 'max-h-0',
                    )}
                  >
                    <div className="mt-0.5">
                      {g.items.map((sub, i) =>
                        isSubsection(sub) ? (
                          <div key={`sub-${i}`}>
                            <div className="px-3 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-[0.1em] text-white/30">
                              {sub.label}
                            </div>
                            {sub.items.map((leaf) => (
                              <SubLink key={leaf.to} leaf={leaf} onNavigate={onMobileClose} navigate={navigate} />
                            ))}
                          </div>
                        ) : (
                          <SubLink key={sub.to} leaf={sub} onNavigate={onMobileClose} navigate={navigate} />
                        ),
                      )}
                    </div>
                  </div>
                )}
              </div>
            );
          })}

          {filtered.length === 0 && (
            <div className="px-3 py-6 text-center text-[12px] text-white/40">
              No menu matches “{query}”
            </div>
          )}
        </nav>

        {/* User card */}
        <div className={cn(
          'flex shrink-0 items-center gap-2.5 border-t border-white/5 px-3 py-3',
          collapsed && 'justify-center px-2',
        )}>
          <Avatar className="size-8 shrink-0">
            <AvatarFallback>{userInitials}</AvatarFallback>
          </Avatar>
          {!collapsed && (
            <div className="min-w-0 flex-1">
              <div className="truncate text-[12.5px] font-medium text-white">{userName}</div>
              <div className="truncate text-[11px] text-white/40">{userRole}</div>
            </div>
          )}
        </div>
      </aside>
    </>
  );
}

function SoonPill() {
  return (
    <span className="ml-auto rounded-full bg-accent-cyan/15 px-1.5 py-px text-[9px] font-semibold uppercase tracking-wide text-accent-cyan">
      Soon
    </span>
  );
}

function SubLink({
  leaf,
  navigate,
  onNavigate,
}: {
  leaf: NavLeaf;
  navigate: ReturnType<typeof useNavigate>;
  onNavigate: () => void;
}) {
  const handleClick = (e: React.MouseEvent) => {
    onNavigate();
    if (leaf.implemented === false) {
      e.preventDefault();
      navigate(`/coming-soon?from=${encodeURIComponent(leaf.to)}`);
      return;
    }
    recordVisit(leaf.to);
  };
  return (
    <NavLink
      to={leaf.to}
      end
      onClick={handleClick}
      className={({ isActive }) =>
        cn(
          'group relative flex items-center gap-2 rounded-md py-1.5 pl-9 pr-2.5 text-[12.5px] text-white/65 transition-colors hover:bg-white/5 hover:text-white',
          isActive && leaf.implemented !== false && 'bg-white/10 font-medium text-white',
        )
      }
    >
      <span className="absolute left-5 size-1 rounded-full bg-current opacity-40" aria-hidden />
      <span className="truncate">{leaf.label}</span>
      {leaf.implemented === false && <SoonPill />}
    </NavLink>
  );
}

function filterNav(items: NavItem[], q: string): NavItem[] {
  if (!q) return items;
  const matches = (label: string) => label.toLowerCase().includes(q);
  const out: NavItem[] = [];
  for (const item of items) {
    if (item.type === 'link') {
      if (matches(item.label)) out.push(item);
      continue;
    }
    const g = item.group;
    if (matches(g.label)) {
      out.push(item);
      continue;
    }
    const filteredItems = g.items
      .map((sub): NavLeaf | NavSubsection | null => {
        if (isSubsection(sub)) {
          const filteredLeaves = sub.items.filter((l) => matches(l.label));
          if (filteredLeaves.length === 0 && !matches(sub.label)) return null;
          return { ...sub, items: filteredLeaves.length ? filteredLeaves : sub.items };
        }
        return matches(sub.label) ? sub : null;
      })
      .filter((x): x is NavLeaf | NavSubsection => x !== null);
    if (filteredItems.length) {
      out.push({ type: 'group', group: { ...g, items: filteredItems } });
    }
  }
  return out;
}
