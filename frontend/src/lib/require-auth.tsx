import { Navigate, useLocation } from 'react-router';
import type { ReactNode } from 'react';
import { useAuth } from './auth';

export function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated, isBooting } = useAuth();
  const location = useLocation();

  if (isBooting) {
    return (
      <div className="flex h-full items-center justify-center text-fg-muted text-sm">
        Loading…
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <>{children}</>;
}
