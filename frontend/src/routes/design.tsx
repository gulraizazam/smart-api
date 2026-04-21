import { useState } from 'react';
import {
  AlertTriangle,
  Check,
  Copy,
  Info,
  Loader2,
  Plus,
  Settings,
  Trash2,
  X,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Select } from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

const TOKENS = {
  brand: [
    { name: 'brand-navy', hex: '#0F1629' },
    { name: 'brand-navy-2', hex: '#0B1121' },
    { name: 'brand-blue', hex: '#2563EB' },
    { name: 'brand-blue-hover', hex: '#1D4ED8' },
    { name: 'brand-blue-soft', hex: '#EFF6FF' },
  ],
  accent: [
    { name: 'accent-cyan', hex: '#06B6D4' },
    { name: 'accent-cyan-hover', hex: '#0891B2' },
    { name: 'accent-cyan-soft', hex: '#ECFEFF' },
  ],
  surface: [
    { name: 'surface', hex: '#F8FAFC' },
    { name: 'elevated', hex: '#FFFFFF' },
    { name: 'hairline', hex: '#E2E8F0' },
    { name: 'hairline-strong', hex: '#CBD5E1' },
  ],
  text: [
    { name: 'fg', hex: '#0F172A' },
    { name: 'fg-muted', hex: '#475569' },
    { name: 'fg-subtle', hex: '#94A3B8' },
  ],
  semantic: [
    { name: 'success', hex: '#10B981' },
    { name: 'warning', hex: '#F59E0B' },
    { name: 'danger', hex: '#EF4444' },
    { name: 'info', hex: '#06B6D4' },
  ],
};

export default function DesignSystemPage() {
  return (
    <div className="mx-auto max-w-[1100px] space-y-10 px-4 py-6 sm:px-6 lg:px-8">
      {/* Intro */}
      <div>
        <div className="inline-flex items-center gap-2 rounded-full bg-accent-cyan-soft px-2.5 py-1 text-[11px] font-medium text-accent-cyan-hover ring-1 ring-inset ring-accent-cyan/20">
          Cutera UI v2 — design system
        </div>
        <h1 className="mt-3 text-2xl font-semibold tracking-tight">Design system reference</h1>
        <p className="mt-1 max-w-2xl text-[13.5px] text-fg-muted">
          Single source of truth for the visual language. Every page across the new admin
          uses the components and tokens shown here. Changes here propagate everywhere —
          prefer extending the system over one-off styles.
        </p>
      </div>

      {/* Colors */}
      <Section title="Colors" desc="Brand navy + brand blue + the new fresh cyan accent. Pair with neutral slate surfaces and semantic colors for state.">
        {Object.entries(TOKENS).map(([group, items]) => (
          <div key={group} className="mt-5 first:mt-0">
            <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">
              {group}
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
              {items.map((t) => (
                <Swatch key={t.name} name={t.name} hex={t.hex} />
              ))}
            </div>
          </div>
        ))}
      </Section>

      {/* Typography */}
      <Section title="Typography" desc="Inter (variable). Headings tracking-tight; body 13–15px; tabular numerals on KPIs and tables.">
        <div className="space-y-3">
          <TypeRow size="3xl" weight="font-semibold tracking-tight" sample="The quick brown fox jumps." />
          <TypeRow size="2xl" weight="font-semibold tracking-tight" sample="The quick brown fox jumps." />
          <TypeRow size="xl" weight="font-semibold tracking-tight" sample="The quick brown fox jumps." />
          <TypeRow size="lg" weight="font-medium" sample="The quick brown fox jumps over the lazy dog." />
          <TypeRow size="base" weight="font-normal" sample="The quick brown fox jumps over the lazy dog." />
          <TypeRow size="sm" weight="font-normal" sample="The quick brown fox jumps over the lazy dog." />
          <TypeRow size="xs" weight="font-medium uppercase tracking-wide" sample="THE QUICK BROWN FOX" />
        </div>
      </Section>

      {/* Buttons */}
      <Section title="Buttons" desc="Three tiers: primary, secondary, ghost. Plus accent (cyan), danger, link. Sizes: sm / md / lg / icon. Mobile baseline is 44px tappable area.">
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <SubBlock title="Variants">
            <div className="flex flex-wrap gap-2">
              <Button>Primary</Button>
              <Button variant="secondary">Secondary</Button>
              <Button variant="accent">Accent</Button>
              <Button variant="ghost">Ghost</Button>
              <Button variant="danger">Danger</Button>
              <Button variant="link">Link</Button>
            </div>
          </SubBlock>
          <SubBlock title="Sizes">
            <div className="flex flex-wrap items-center gap-2">
              <Button size="sm">Small</Button>
              <Button size="md">Medium</Button>
              <Button size="lg">Large</Button>
              <Button size="icon" aria-label="Settings"><Settings /></Button>
            </div>
          </SubBlock>
          <SubBlock title="With icons">
            <div className="flex flex-wrap gap-2">
              <Button><Plus /> Add new</Button>
              <Button variant="secondary"><Trash2 /> Delete</Button>
              <Button variant="accent"><Check /> Confirm</Button>
            </div>
          </SubBlock>
          <SubBlock title="States">
            <div className="flex flex-wrap gap-2">
              <Button disabled>Disabled</Button>
              <Button><Loader2 className="animate-spin" /> Loading</Button>
            </div>
          </SubBlock>
        </div>
      </Section>

      {/* Forms */}
      <Section title="Form controls" desc="Single-column on mobile, native inputs styled to match. Visible labels, no placeholder-only.">
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <SubBlock title="Text input">
            <div className="space-y-1.5">
              <Label htmlFor="ds-name">Full name</Label>
              <Input id="ds-name" placeholder="Jane Doe" />
            </div>
          </SubBlock>
          <SubBlock title="Disabled">
            <div className="space-y-1.5">
              <Label htmlFor="ds-disabled">Email</Label>
              <Input id="ds-disabled" defaultValue="locked@cutera.com" disabled />
            </div>
          </SubBlock>
          <SubBlock title="Select">
            <div className="space-y-1.5">
              <Label htmlFor="ds-select">Centre</Label>
              <Select id="ds-select" defaultValue="">
                <option value="" disabled>Select a centre</option>
                <option>Karachi — Clifton</option>
                <option>Lahore — DHA</option>
                <option>Islamabad — F-7</option>
              </Select>
            </div>
          </SubBlock>
          <SubBlock title="With error">
            <div className="space-y-1.5">
              <Label htmlFor="ds-err">Phone</Label>
              <Input id="ds-err" defaultValue="abcd" aria-invalid />
              <p role="alert" className="text-[12px] text-danger">Enter a valid phone number.</p>
            </div>
          </SubBlock>
        </div>
      </Section>

      {/* Badges */}
      <Section title="Badges & status" desc="Pill-shaped status indicators. Use the dot prefix for status semantics; outline for tags.">
        <div className="flex flex-wrap gap-2">
          <Badge dot>Neutral</Badge>
          <Badge variant="brand" dot>Brand</Badge>
          <Badge variant="accent" dot>Accent</Badge>
          <Badge variant="success" dot>Converted</Badge>
          <Badge variant="warning" dot>Follow-up</Badge>
          <Badge variant="danger" dot>Junk</Badge>
          <Badge variant="outline">Tag</Badge>
        </div>
      </Section>

      {/* Cards */}
      <Section title="Cards" desc="Soft elevation + 1px hairline border + xl radius. Header / Content / Footer slots.">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Card title</CardTitle>
              <CardDescription>Brief one-line description below the title.</CardDescription>
            </CardHeader>
            <CardContent className="text-[13px] text-fg-muted">
              Card body content. Keep dense but not crowded — leave room for the eye.
            </CardContent>
            <CardFooter className="justify-end gap-2">
              <Button variant="ghost" size="sm">Cancel</Button>
              <Button size="sm">Save</Button>
            </CardFooter>
          </Card>

          <Card className="overflow-hidden">
            <div className="bg-gradient-to-br from-brand-navy to-brand-navy-2 p-5 text-white">
              <div className="text-[11px] font-medium uppercase tracking-wide text-accent-cyan">Promoted</div>
              <h3 className="mt-1 text-base font-semibold">Brand-headed card</h3>
              <p className="mt-1 text-[12.5px] text-white/65">A header band uses the brand surface for emphasis.</p>
            </div>
            <CardContent>
              <p className="text-[13px] text-fg-muted">
                Body is on the standard elevated surface. Only the header carries brand colour to keep readability high.
              </p>
            </CardContent>
          </Card>
        </div>
      </Section>

      {/* Tabs */}
      <Section title="Tabs" desc="Pill-style tabs in a tinted track. Active tab elevates with a hairline ring + xs shadow.">
        <Tabs defaultValue="overview">
          <TabsList>
            <TabsTrigger value="overview">Overview</TabsTrigger>
            <TabsTrigger value="activity">Activity</TabsTrigger>
            <TabsTrigger value="settings">Settings</TabsTrigger>
          </TabsList>
          <TabsContent value="overview">
            <Card><CardContent className="text-[13px] text-fg-muted">Overview pane.</CardContent></Card>
          </TabsContent>
          <TabsContent value="activity">
            <Card><CardContent className="text-[13px] text-fg-muted">Activity pane.</CardContent></Card>
          </TabsContent>
          <TabsContent value="settings">
            <Card><CardContent className="text-[13px] text-fg-muted">Settings pane.</CardContent></Card>
          </TabsContent>
        </Tabs>
      </Section>

      {/* Avatars + skeletons */}
      <Section title="Avatars & skeletons">
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          <SubBlock title="Avatars">
            <div className="flex items-center gap-3">
              <Avatar><AvatarFallback>JD</AvatarFallback></Avatar>
              <Avatar className="size-10"><AvatarFallback>SK</AvatarFallback></Avatar>
              <Avatar className="size-12"><AvatarFallback>AM</AvatarFallback></Avatar>
              <Avatar className="size-14"><AvatarFallback>RT</AvatarFallback></Avatar>
            </div>
          </SubBlock>
          <SubBlock title="Skeleton">
            <div className="space-y-2">
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-2/3" />
            </div>
          </SubBlock>
        </div>
      </Section>

      {/* Table */}
      <Section title="Tables" desc="Sticky header, hairline row dividers, hover tint, no zebra. Tables collapse to mobile cards under 768px.">
        <Card className="overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="border-b border-hairline">
                <TableHead>Patient</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Last visit</TableHead>
                <TableHead className="text-end">Spend</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {[
                ['Aisha Khan', 'success', 'Active', '2d ago', 'PKR 145,000'],
                ['Rohan Mehta', 'warning', 'Follow-up', '1w ago', 'PKR 32,000'],
                ['Sara Ali', 'brand', 'New', 'today', 'PKR 8,500'],
              ].map(([name, tone, status, when, spend], i) => (
                <TableRow key={i}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <Avatar className="size-8"><AvatarFallback>{(name as string).split(' ').map((n) => n[0]).join('')}</AvatarFallback></Avatar>
                      <span className="text-[13px] font-medium">{name}</span>
                    </div>
                  </TableCell>
                  <TableCell><Badge variant={tone as 'success' | 'warning' | 'brand'} dot>{status}</Badge></TableCell>
                  <TableCell className="text-[12.5px] text-fg-muted">{when}</TableCell>
                  <TableCell className="nums-tabular text-end">{spend}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Card>
      </Section>

      {/* Dialog */}
      <Section title="Dialog & dropdown">
        <div className="flex flex-wrap gap-3">
          <DemoDialog />
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="secondary">Open menu</Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuLabel>Actions</DropdownMenuLabel>
              <DropdownMenuItem>View details</DropdownMenuItem>
              <DropdownMenuItem>Edit</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem destructive>Delete</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </Section>

      {/* Inline alerts */}
      <Section title="Inline alerts" desc="For form-level or page-level messages. Use sparingly; prefer toasts for transient feedback.">
        <div className="space-y-2">
          <Alert tone="info" icon={<Info className="size-4" />} title="Heads up" body="A scheduled job will run tonight to recalculate revenue." />
          <Alert tone="success" icon={<Check className="size-4" />} title="Saved" body="Your changes have been saved." />
          <Alert tone="warning" icon={<AlertTriangle className="size-4" />} title="Almost full" body="Storage is at 92%. Free up space soon." />
          <Alert tone="danger" icon={<X className="size-4" />} title="Couldn’t save" body="Check the highlighted fields and try again." />
        </div>
      </Section>
    </div>
  );
}

/* ───────────────────────────────────────────────────────────────────────── */

function Section({
  title,
  desc,
  children,
}: { title: string; desc?: string; children: React.ReactNode }) {
  return (
    <section>
      <div className="mb-4">
        <h2 className="text-base font-semibold tracking-tight">{title}</h2>
        {desc && <p className="mt-0.5 text-[13px] text-fg-muted">{desc}</p>}
      </div>
      {children}
    </section>
  );
}

function SubBlock({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">{title}</div>
      <Card><CardContent>{children}</CardContent></Card>
    </div>
  );
}

function Swatch({ name, hex }: { name: string; hex: string }) {
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try { await navigator.clipboard.writeText(hex); setCopied(true); setTimeout(() => setCopied(false), 1200); }
    catch { /* ignore */ }
  };
  return (
    <button
      type="button"
      onClick={copy}
      className="group relative overflow-hidden rounded-xl bg-elevated text-start ring-1 ring-inset ring-hairline transition-shadow hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
    >
      <div className="h-16 w-full" style={{ backgroundColor: hex }} />
      <div className="flex items-center justify-between gap-2 px-3 py-2">
        <div className="min-w-0">
          <div className="truncate text-[12px] font-medium">{name}</div>
          <div className="font-mono text-[11px] text-fg-subtle">{hex}</div>
        </div>
        {copied ? <Check className="size-3.5 text-success" /> : <Copy className="size-3.5 text-fg-subtle opacity-0 transition-opacity group-hover:opacity-100" />}
      </div>
    </button>
  );
}

function TypeRow({ size, weight, sample }: { size: string; weight: string; sample: string }) {
  return (
    <div className="grid grid-cols-[80px_1fr] items-center gap-4">
      <div className="text-[11px] font-mono text-fg-subtle">{size}</div>
      <div className={`text-${size} ${weight}`}>{sample}</div>
    </div>
  );
}

function DemoDialog() {
  return (
    <Dialog>
      <DialogTrigger asChild><Button>Open dialog</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Confirm action</DialogTitle>
          <DialogDescription>This is a sample dialog showing the standard layout, focus trap, and overlay.</DialogDescription>
        </DialogHeader>
        <p className="text-[13px] text-fg-muted">
          Dialogs use Radix primitives for full keyboard / screen-reader support out of the box.
        </p>
        <DialogFooter>
          <Button variant="secondary" size="sm">Cancel</Button>
          <Button size="sm">Confirm</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Alert({
  tone,
  icon,
  title,
  body,
}: { tone: 'info' | 'success' | 'warning' | 'danger'; icon: React.ReactNode; title: string; body: string }) {
  const cls = {
    info: 'bg-info-soft text-info ring-info/20',
    success: 'bg-success-soft text-success ring-success/20',
    warning: 'bg-warning-soft text-warning ring-warning/20',
    danger: 'bg-danger-soft text-danger ring-danger/20',
  }[tone];
  return (
    <div className={`flex items-start gap-3 rounded-lg px-3 py-2.5 ring-1 ring-inset ${cls}`}>
      <div className="mt-0.5">{icon}</div>
      <div className="flex-1">
        <div className="text-[13px] font-medium">{title}</div>
        <div className="mt-0.5 text-[12.5px] opacity-80">{body}</div>
      </div>
    </div>
  );
}
