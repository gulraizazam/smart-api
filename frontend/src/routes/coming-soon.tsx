import { Link, useSearchParams } from 'react-router';
import { ArrowLeft, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function ComingSoonPage() {
  const [params] = useSearchParams();
  const from = params.get('from') ?? 'this page';
  const label = from.replace(/^\//, '').replace(/-/g, ' ');

  return (
    <div className="mx-auto max-w-xl px-5 py-16">
      <Card>
        <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
          <div className="flex size-12 items-center justify-center rounded-full bg-accent-cyan-soft text-accent-cyan-hover">
            <Sparkles className="size-5" />
          </div>
          <div>
            <h1 className="text-base font-semibold tracking-tight">Coming soon</h1>
            <p className="mt-1 text-[13px] text-fg-muted">
              <span className="font-medium text-fg">{label}</span> isn’t in the new SPA yet —
              it ships in a later sprint. The legacy admin still serves it at the original URL.
            </p>
          </div>
          <Button asChild variant="secondary" size="sm">
            <Link to="/"><ArrowLeft className="size-3.5" /> Back to dashboard</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
