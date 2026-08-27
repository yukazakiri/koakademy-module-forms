import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import formsRoutes from "@/routes/administrators/forms";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { Copy, FilePlus2, LayoutTemplate, Sparkles, Trash2 } from "lucide-react";
import { useState } from "react";

interface Template {
  id?: string;
  key: string;
  name: string;
  description: string | null;
  model_key: string | null;
  field_count: number;
  system: boolean;
}

interface Props {
  user: User;
  templates: Template[];
  permissions?: {
    "templates.create"?: boolean;
    "templates.update"?: boolean;
    "templates.delete"?: boolean;
  };
}

export default function FormsTemplates({ user, templates, permissions }: Props) {
  const [drafts, setDrafts] = useState<Record<string, { name: string; description: string }>>({});

  function draft(template: Template) {
    return drafts[template.key] ?? { name: template.name, description: template.description ?? "" };
  }

  function updateDraft(template: Template, patch: Partial<{ name: string; description: string }>): void {
    setDrafts((current) => ({ ...current, [template.key]: { ...draft(template), ...patch } }));
  }

  function update(template: Template): void {
    if (!template.id) return;
    const current = draft(template);
    router.put(formsRoutes.templates.update.url(template.id), {
      name: current.name,
      description: current.description,
    }, { preserveScroll: true });
  }

  function duplicate(template: Template): void {
    const name = window.prompt("Name this copy", `${template.name} copy`);
    if (!name?.trim()) return;
    router.post(formsRoutes.templates.duplicate.url(template.id ?? template.key), { name }, { preserveScroll: true });
  }

  function remove(template: Template): void {
    if (!template.id || !window.confirm(`Delete “${template.name}”?`)) return;
    router.delete(formsRoutes.templates.delete.url(template.id), { preserveScroll: true });
  }

  return (
    <AdminLayout user={user} title="Form Templates">
      <Head title="Form Templates" />
      <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-6">
        <header className="border-border/70 bg-card relative overflow-hidden rounded-2xl border p-6 shadow-sm sm:p-9">
          <div className="from-primary/15 pointer-events-none absolute -top-24 -right-10 size-64 rounded-full bg-gradient-to-br to-emerald-500/10 blur-3xl" />
          <div className="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl"><LayoutTemplate className="size-5" aria-hidden="true" /></div>
              <p className="text-muted-foreground mt-5 text-xs font-semibold tracking-[0.12em] uppercase">Reusable starting points</p>
              <h1 className="mt-2 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">Form templates</h1>
              <p className="text-muted-foreground mt-3 max-w-2xl text-sm leading-6 sm:text-base">Start with a trusted profile workflow or preserve a form configuration your team uses repeatedly.</p>
            </div>
            <Button asChild variant="outline"><Link href={formsRoutes.index.url()}><FilePlus2 className="size-4" /> Back to forms</Link></Button>
          </div>
        </header>

        <section className="grid gap-4 lg:grid-cols-2" aria-label="Available templates">
          {templates.map((template) => {
            const current = draft(template);
            return (
              <Card key={template.key} className="border-border/70 flex h-full flex-col">
                <CardHeader>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex gap-3"><div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-xl">{template.system ? <Sparkles className="text-primary size-5" aria-hidden="true" /> : <LayoutTemplate className="text-muted-foreground size-5" aria-hidden="true" />}</div><div><CardTitle>{template.name}</CardTitle><CardDescription className="mt-1">{template.description || "No description provided."}</CardDescription></div></div>
                    <Badge variant={template.system ? "default" : "outline"}>{template.system ? "Built in" : "Custom"}</Badge>
                  </div>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col gap-5">
                  <div className="text-muted-foreground flex flex-wrap gap-x-5 gap-y-2 text-xs"><span>{template.field_count} field{template.field_count === 1 ? "" : "s"}</span>{template.model_key && <span>Linked to {template.model_key}</span>}</div>
                  {!template.system && permissions?.["templates.update"] && (
                    <div className="grid gap-3 border-t pt-4">
                      <Input aria-label={`${template.name} name`} value={current.name} onChange={(event) => updateDraft(template, { name: event.target.value })} />
                      <Textarea aria-label={`${template.name} description`} value={current.description} onChange={(event) => updateDraft(template, { description: event.target.value })} rows={2} />
                      <Button variant="outline" size="sm" onClick={() => update(template)}>Save template details</Button>
                    </div>
                  )}
                  <div className="mt-auto flex flex-wrap gap-2">
                    {permissions?.["templates.create"] !== false && <Button onClick={() => router.post(formsRoutes.templates.use.url(template.key), {}, { preserveScroll: true })}><FilePlus2 className="size-4" /> Use template</Button>}
                    {template.id && permissions?.["templates.create"] && <Button variant="outline" onClick={() => duplicate(template)}><Copy className="size-4" /> Duplicate</Button>}
                    {template.id && permissions?.["templates.delete"] && <Button variant="ghost" size="icon" onClick={() => remove(template)} aria-label={`Delete ${template.name}`}><Trash2 className="text-destructive size-4" /></Button>}
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </section>
      </div>
    </AdminLayout>
  );
}
