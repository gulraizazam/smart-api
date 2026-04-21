import { describe, it, expect } from 'vitest';
import { NAV, activeGroupForPath, isSubsection } from './sidebar-data';

describe('sidebar IA', () => {
  it('Dashboard is the only top-level link, rest are groups', () => {
    const links = NAV.filter((n) => n.type === 'link');
    const groups = NAV.filter((n) => n.type === 'group');
    expect(links).toHaveLength(1);
    expect(links[0].type === 'link' && links[0].label).toBe('Dashboard');
    expect(groups.length).toBe(7); // Clinic, Catalog, Finance, Inventory, Reports, People, Settings
  });

  it('Leads is implemented in the Clinic group', () => {
    const clinic = NAV.find((n) => n.type === 'group' && n.group.key === 'clinic');
    expect(clinic).toBeTruthy();
    if (clinic?.type === 'group') {
      const leads = clinic.group.items.find(
        (i) => !isSubsection(i) && i.label === 'Leads',
      );
      expect(leads).toBeTruthy();
      expect(leads && !isSubsection(leads) && leads.implemented).toBe(true);
    }
  });
});

describe('activeGroupForPath', () => {
  it('returns the owning group key for a leaf path', () => {
    expect(activeGroupForPath('/leads')).toBe('clinic');
    expect(activeGroupForPath('/cashflow/expenses')).toBe('finance');
    expect(activeGroupForPath('/hr/employees')).toBe('people');
    expect(activeGroupForPath('/settings/regions')).toBe('settings');
  });

  it('returns empty string for unmatched paths', () => {
    expect(activeGroupForPath('/')).toBe('');
    expect(activeGroupForPath('/totally-fake-path')).toBe('');
  });

  it('matches subpaths of a leaf (e.g. /leads/123)', () => {
    expect(activeGroupForPath('/leads/123')).toBe('clinic');
  });
});

describe('isSubsection type guard', () => {
  it('discriminates a leaf vs a subsection', () => {
    expect(isSubsection({ label: 'X', to: '/x' })).toBe(false);
    expect(isSubsection({ type: 'subsection', label: 'HR', items: [] })).toBe(true);
  });
});
