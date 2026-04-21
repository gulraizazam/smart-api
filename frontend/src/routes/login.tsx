import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Eye, EyeOff, Loader2, Sparkles } from 'lucide-react';
import { useAuth } from '@/lib/auth';
import { ApiError } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const schema = z.object({
  email: z.string().email('Enter a valid email'),
  password: z.string().min(1, 'Password is required'),
});

type FormValues = z.infer<typeof schema>;

export default function LoginPage() {
  const { login, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [showPwd, setShowPwd] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const from = (location.state as { from?: { pathname?: string } } | null)?.from?.pathname ?? '/';

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
    setError,
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  });

  if (isAuthenticated) return <Navigate to={from} replace />;

  const onSubmit = async (values: FormValues) => {
    setServerError(null);
    try {
      await login(values);
      navigate(from, { replace: true });
    } catch (err) {
      if (err instanceof ApiError) {
        const fields = err.fieldErrors;
        let surfaced = false;
        for (const [name, messages] of Object.entries(fields)) {
          if (name === 'email' || name === 'password') {
            setError(name, { message: messages[0] });
            surfaced = true;
          }
        }
        if (!surfaced) setServerError(err.message);
      } else {
        setServerError('Network error. Try again.');
      }
    }
  };

  return (
    <div className="grid h-full grid-cols-1 lg:grid-cols-2">
      {/* Brand panel — desktop only */}
      <aside className="relative hidden overflow-hidden bg-brand-gradient text-fg-on-dark lg:flex lg:flex-col lg:justify-between lg:p-10">
        <div className="flex items-center gap-2">
          <div className="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-brand-blue to-accent-cyan text-sm font-bold text-white">
            C
          </div>
          <span className="text-base font-semibold tracking-tight">Cutera Aesthetics</span>
        </div>

        <div className="space-y-5">
          <div className="inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-accent-cyan ring-1 ring-inset ring-white/10">
            <Sparkles className="size-3" /> New experience
          </div>
          <h1 className="text-3xl font-semibold leading-tight tracking-tight">
            A faster, fresher<br />admin for your clinics.
          </h1>
          <p className="max-w-md text-[14px] leading-relaxed text-white/65">
            Same data, same permissions — rebuilt as a modern, API-driven
            interface so your team can move at the speed of the day.
          </p>
        </div>

        <div className="text-[12px] text-white/40">
          © {new Date().getFullYear()} Cutera Aesthetics — internal use only.
        </div>

        {/* Decorative gradient orb */}
        <div
          aria-hidden
          className="pointer-events-none absolute -right-32 -top-32 size-96 rounded-full bg-accent-cyan/20 blur-3xl"
        />
      </aside>

      {/* Form panel */}
      <main className="flex items-center justify-center px-6 py-10 sm:px-10">
        <div className="w-full max-w-sm">
          <div className="mb-8 lg:hidden">
            <div className="flex items-center gap-2">
              <div className="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-brand-blue to-accent-cyan text-sm font-bold text-white">
                C
              </div>
              <span className="text-base font-semibold tracking-tight">Cutera</span>
            </div>
          </div>

          <h2 className="text-xl font-semibold tracking-tight">Welcome back</h2>
          <p className="mt-1 text-[13px] text-fg-muted">Sign in to your Cutera admin account.</p>

          <form className="mt-8 space-y-4" onSubmit={handleSubmit(onSubmit)} noValidate>
            <div className="space-y-1.5">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                inputMode="email"
                autoComplete="username"
                aria-invalid={!!errors.email}
                aria-describedby={errors.email ? 'email-err' : undefined}
                {...register('email')}
              />
              {errors.email && (
                <p id="email-err" role="alert" className="text-[12px] text-danger">
                  {errors.email.message}
                </p>
              )}
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="password">Password</Label>
                <a
                  href="/password/reset"
                  className="text-[12px] font-medium text-brand-blue hover:underline"
                >
                  Forgot password?
                </a>
              </div>
              <div className="relative">
                <Input
                  id="password"
                  type={showPwd ? 'text' : 'password'}
                  autoComplete="current-password"
                  className="pr-10"
                  aria-invalid={!!errors.password}
                  aria-describedby={errors.password ? 'pwd-err' : undefined}
                  {...register('password')}
                />
                <button
                  type="button"
                  onClick={() => setShowPwd((v) => !v)}
                  aria-label={showPwd ? 'Hide password' : 'Show password'}
                  className="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-fg-subtle hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
                >
                  {showPwd ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </button>
              </div>
              {errors.password && (
                <p id="pwd-err" role="alert" className="text-[12px] text-danger">
                  {errors.password.message}
                </p>
              )}
            </div>

            {serverError && (
              <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[13px] text-danger ring-1 ring-inset ring-danger/20">
                {serverError}
              </div>
            )}

            <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="size-4 animate-spin" />}
              {isSubmitting ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>

          <p className="mt-8 text-center text-[12px] text-fg-subtle">
            Trouble signing in? Contact your administrator.
          </p>
        </div>
      </main>
    </div>
  );
}
