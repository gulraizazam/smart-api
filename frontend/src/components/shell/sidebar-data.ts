/**
 * Sidebar information architecture — single source of truth for the
 * navigation tree. Mirrors `resources/views/admin/partials/sidebar-v2.blade.php`
 * exactly (same 8 groups, same labels, same order).
 *
 * `to` is a SPA route. `implemented: false` items render with a "Soon" pill
 * and route to /coming-soon — Sprint 1 only ships Dashboard + Leads.
 *
 * Sprint 2 follow-up: replace this static list with a server response from
 * `/api/me/navigation` so permissions + branch scoping become authoritative.
 */

import type { LucideIcon } from 'lucide-react';
import {
  LayoutDashboard,
  Stethoscope,
  Layers,
  Wallet,
  Warehouse,
  BarChart3,
  Users,
  Settings,
} from 'lucide-react';

export type NavLeaf = {
  label: string;
  to: string;
  implemented?: boolean;
};

export type NavSubsection = {
  type: 'subsection';
  label: string;
  items: NavLeaf[];
};

export type NavGroup = {
  key: string;
  label: string;
  icon: LucideIcon;
  items: (NavLeaf | NavSubsection)[];
};

export type NavItem =
  | { type: 'link'; label: string; to: string; icon: LucideIcon; implemented?: boolean }
  | { type: 'group'; group: NavGroup };

/** Type guard — TS can't narrow NavLeaf vs NavSubsection without a shared
 * discriminant on NavLeaf, so we test for the absence of `type`. */
export function isSubsection(item: NavLeaf | NavSubsection): item is NavSubsection {
  return (item as NavSubsection).type === 'subsection';
}

export const NAV: NavItem[] = [
  {
    type: 'link',
    label: 'Dashboard',
    to: '/',
    icon: LayoutDashboard,
    implemented: true,
  },
  {
    type: 'group',
    group: {
      key: 'clinic',
      label: 'Clinic',
      icon: Stethoscope,
      items: [
        { label: 'Patients', to: '/patients', implemented: true },
        { label: 'Leads', to: '/leads', implemented: true },
        { label: 'Consultancies', to: '/consultancies' },
        { label: 'Treatments', to: '/treatments' },
        { label: 'Scheduling Shifts', to: '/scheduling-shifts' },
        { label: 'Business Closed Periods', to: '/business-closures' },
      ],
    },
  },
  {
    type: 'group',
    group: {
      key: 'catalog',
      label: 'Catalog',
      icon: Layers,
      items: [
        { label: 'Services', to: '/services', implemented: true },
        { label: 'Bundles', to: '/bundles', implemented: true },
        { label: 'Packages', to: '/packages' },
        { label: 'Plans', to: '/plans', implemented: true },
        { label: 'Discounts', to: '/discounts', implemented: true },
        { label: 'Voucher Types', to: '/voucher-types' },
        { label: 'Vouchers', to: '/vouchers' },
        { label: 'Membership Types', to: '/membership-types' },
        { label: 'Memberships', to: '/memberships' },
      ],
    },
  },
  {
    type: 'group',
    group: {
      key: 'finance',
      label: 'Finance',
      icon: Wallet,
      items: [
        { label: 'Invoices', to: '/invoices' },
        { label: 'Refunds', to: '/refunds' },
        { label: 'Advances', to: '/advances' },
        { label: 'Payment Modes', to: '/payment-modes' },
        {
          type: 'subsection',
          label: 'Cash Flow',
          items: [
            { label: 'Dashboard', to: '/cashflow' },
            { label: 'Expenses', to: '/cashflow/expenses' },
            { label: 'Transfers', to: '/cashflow/transfers' },
            { label: 'Vendors', to: '/cashflow/vendors' },
            { label: 'Staff Advances', to: '/cashflow/staff' },
            { label: 'FDM View', to: '/cashflow/fdm' },
            { label: 'Reports', to: '/cashflow/reports' },
            { label: 'Settings', to: '/cashflow/settings' },
          ],
        },
      ],
    },
  },
  {
    type: 'group',
    group: {
      key: 'inventory',
      label: 'Inventory',
      icon: Warehouse,
      items: [
        { label: 'Brand', to: '/inventory/brands' },
        { label: 'Product', to: '/inventory/products' },
        { label: 'Transfer', to: '/inventory/transfers' },
        { label: 'Order', to: '/inventory/orders' },
      ],
    },
  },
  {
    type: 'group',
    group: {
      key: 'reports',
      label: 'Reports',
      icon: BarChart3,
      items: [
        { label: 'Management Dashboard', to: '/reports/management' },
        { label: 'Doctor Ratings', to: '/reports/feedbacks' },
        { label: 'General Sales Report', to: '/reports/general-sales' },
        { label: 'Tax Calculation Report', to: '/reports/tax' },
        { label: 'Operation Reports', to: '/reports/operations' },
        { label: 'Membership Reports', to: '/reports/memberships' },
        { label: 'Doctor Incentive Report', to: '/reports/doctor-incentive' },
        { label: 'Appointments Report', to: '/reports/appointments' },
        { label: 'CSR Dashboard', to: '/reports/csr' },
        { label: 'Non-Converted Customer Report', to: '/reports/non-converted' },
        { label: 'Conversion Report', to: '/reports/conversion' },
        { label: 'Activity Logs', to: '/reports/activity-logs' },
        { label: 'Staff Wise Arrival Report', to: '/reports/staff-arrival' },
        { label: 'Follow Up Report', to: '/reports/follow-up' },
        { label: 'Inventory Report', to: '/reports/inventory' },
        { label: 'Doctor Ratings Report', to: '/reports/feedback-detail' },
        { label: 'Future Treatments Report', to: '/reports/future-treatments' },
        { label: 'Doctors Upselling Report', to: '/reports/upselling' },
        { label: 'Consultants Revenue Report', to: '/reports/consultant-revenue' },
        { label: 'Doctor Revenue Report', to: '/reports/doctor-revenue' },
      ],
    },
  },
  {
    type: 'group',
    group: {
      key: 'people',
      label: 'People',
      icon: Users,
      items: [
        {
          type: 'subsection',
          label: 'HR',
          items: [
            { label: 'Dashboard', to: '/hr/dashboard' },
            { label: 'Employees', to: '/hr/employees' },
            { label: 'Departments', to: '/hr/departments' },
            { label: 'Designations', to: '/hr/designations' },
            { label: 'Leave Types', to: '/hr/leave-types' },
            { label: 'Leave Balances', to: '/hr/leave-balances' },
            { label: 'Leave Applications', to: '/hr/leave-applications' },
            { label: 'Leave Calendar', to: '/hr/leave-calendar' },
            { label: 'Recruitment', to: '/hr/recruitment' },
          ],
        },
        {
          type: 'subsection',
          label: 'Access',
          items: [
            { label: 'Application Users', to: '/users' },
            { label: 'Branch Assignments', to: '/users/branches' },
            { label: 'Roles', to: '/roles' },
            { label: 'Permissions', to: '/permissions' },
            { label: 'User Types', to: '/user-types' },
            { label: 'Doctors', to: '/doctors' },
            { label: 'Centre Targets', to: '/centre-targets' },
          ],
        },
      ],
    },
  },
  {
    type: 'group',
    group: {
      key: 'settings',
      label: 'Settings',
      icon: Settings,
      items: [
        {
          type: 'subsection',
          label: 'General',
          items: [
            { label: 'Global Settings', to: '/settings' },
            { label: 'Operator Settings', to: '/settings/operators' },
            { label: 'SMS Templates', to: '/settings/sms' },
            { label: 'Google Reviews', to: '/settings/google-reviews' },
          ],
        },
        {
          type: 'subsection',
          label: 'Locations',
          items: [
            { label: 'Regions', to: '/settings/regions' },
            { label: 'Cities', to: '/settings/cities' },
            { label: 'Towns', to: '/settings/towns' },
            { label: 'Centres', to: '/settings/locations' },
          ],
        },
        {
          type: 'subsection',
          label: 'Taxonomies',
          items: [
            { label: 'Lead Sources', to: '/settings/lead-sources', implemented: true },
            { label: 'Lead Statuses', to: '/settings/lead-statuses', implemented: true },
            { label: 'Appointment Statuses', to: '/settings/appointment-statuses' },
            { label: 'Machine Type', to: '/settings/machine-types' },
            { label: 'Resource', to: '/settings/resources' },
          ],
        },
        {
          type: 'subsection',
          label: 'Forms',
          items: [
            { label: 'Custom Forms', to: '/settings/custom-forms' },
            { label: 'Custom Form Feedbacks', to: '/settings/form-feedbacks' },
          ],
        },
      ],
    },
  },
];

/** Resolve which group key owns a given path so the sidebar auto-expands it. */
export function activeGroupForPath(pathname: string): string {
  for (const item of NAV) {
    if (item.type === 'group') {
      const matches = item.group.items.some((sub) => {
        if (isSubsection(sub)) {
          return sub.items.some((leaf) => pathname === leaf.to || pathname.startsWith(leaf.to + '/'));
        }
        return pathname === sub.to || pathname.startsWith(sub.to + '/');
      });
      if (matches) return item.group.key;
    }
  }
  return '';
}
