import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider, createBrowserRouter, Navigate } from 'react-router';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from '@/lib/query-client';
import { AuthProvider } from '@/lib/auth';
import { RequireAuth } from '@/lib/require-auth';
import { Shell } from '@/components/shell/shell';
import LoginPage from '@/routes/login';
import DashboardPage from '@/routes/dashboard';
import LeadsPage from '@/routes/leads';
import PatientsPage from '@/routes/patients';
import PatientDetailPage from '@/routes/patient-detail';
import LeadSourcesPage from '@/routes/lead-sources';
import LeadStatusesPage from '@/routes/lead-statuses';
import ServicesPage from '@/routes/services';
import BundlesPage from '@/routes/bundles';
import DiscountsPage from '@/routes/discounts';
import PlansPage from '@/routes/plans';
import PlanLogPage from '@/routes/plan-log';
import DesignSystemPage from '@/routes/design';
import ComingSoonPage from '@/routes/coming-soon';
import './styles/globals.css';

// Router basename mirrors Vite's BASE_URL. Dev → '/', production → '/admin-v2'
// so visiting http://host/admin-v2/leads routes correctly without prefix
// duplication. import.meta.env.BASE_URL always ends with a slash.
const routerBase = import.meta.env.BASE_URL.replace(/\/$/, '') || '/';

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    element: (
      <RequireAuth>
        <Shell />
      </RequireAuth>
    ),
    children: [
      { index: true, element: <DashboardPage /> },
      { path: 'leads', element: <LeadsPage /> },
      { path: 'patients', element: <PatientsPage /> },
      { path: 'patients/:id', element: <PatientDetailPage /> },
      { path: 'settings/lead-sources', element: <LeadSourcesPage /> },
      { path: 'settings/lead-statuses', element: <LeadStatusesPage /> },
      { path: 'services', element: <ServicesPage /> },
      { path: 'bundles', element: <BundlesPage /> },
      { path: 'discounts', element: <DiscountsPage /> },
      { path: 'plans', element: <PlansPage /> },
      { path: 'plans/log/:id', element: <PlanLogPage /> },
      { path: '_design', element: <DesignSystemPage /> },
      { path: 'coming-soon', element: <ComingSoonPage /> },
      // Catch all unimplemented sidebar destinations and route them
      // to the same friendly stub so users never hit a hard 404.
      { path: '*', element: <ComingSoonPage /> },
    ],
  },
  { path: '*', element: <Navigate to="/" replace /> },
], { basename: routerBase });

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </QueryClientProvider>
  </React.StrictMode>,
);
