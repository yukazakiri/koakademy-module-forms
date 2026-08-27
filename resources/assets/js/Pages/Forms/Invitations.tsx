import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import formsRoutes from "@/routes/administrators/forms";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Mail, Send, ShieldCheck, Users } from "lucide-react";
import { useMemo, useState } from "react";

interface Candidate {
  model_id: string;
  email: string;
  name: string | null;
  student_number: string | number | null;
}

interface Props {
  user: User;
  form: { id: string; title: string; status: string; access_mode: string };
  candidates: Candidate[];
  permissions?: { send?: boolean };
}

export default function FormsInvitations({ user, form, candidates, permissions }: Props) {
  const [selected, setSelected] = useState<string[]>(() => candidates.map((candidate) => candidate.model_id));
  const allSelected = selected.length === candidates.length && candidates.length > 0;
  const selectedCount = useMemo(() => selected.length, [selected]);

  function toggle(modelId: string): void {
    setSelected((current) => current.includes(modelId) ? current.filter((id) => id !== modelId) : [...current, modelId]);
  }

  function send(): void {
    if (selected.length === 0) return;
    router.post(formsRoutes.invitations.send.url(form.id), { model_ids: selected }, { preserveScroll: true });
  }

  return (
    <AdminLayout user={user} title={`Invitations · ${form.title}`}>
      <Head title={`Invitations · ${form.title}`} />
      <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-6">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <Button asChild variant="ghost" size="sm" className="mb-3 -ml-3"><Link href={formsRoutes.index.url()}><ArrowLeft className="size-4" /> All forms</Link></Button>
            <p className="text-muted-foreground text-xs font-semibold tracking-[0.12em] uppercase">Record-bound delivery</p>
            <h1 className="mt-2 text-3xl font-semibold tracking-[-0.04em]">Send invitations</h1>
            <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">Choose incomplete records and explicitly queue a private, one-time link for each verified email address.</p>
          </div>
          {permissions?.send && <Button size="lg" onClick={send} disabled={selectedCount === 0}><Send className="size-4" /> Queue {selectedCount} invitation{selectedCount === 1 ? "" : "s"}</Button>}
        </header>

        <div className="grid gap-4 md:grid-cols-3">
          <Card className="border-border/70"><CardContent className="flex items-center justify-between p-5"><div><p className="text-muted-foreground text-xs font-semibold uppercase">Eligible</p><p className="mt-1 text-3xl font-semibold">{candidates.length}</p></div><Users className="text-primary size-6" aria-hidden="true" /></CardContent></Card>
          <Card className="border-border/70"><CardContent className="flex items-center justify-between p-5"><div><p className="text-muted-foreground text-xs font-semibold uppercase">Selected</p><p className="mt-1 text-3xl font-semibold">{selectedCount}</p></div><Mail className="size-6 text-sky-600" aria-hidden="true" /></CardContent></Card>
          <Card className="border-border/70"><CardContent className="flex items-center justify-between p-5"><div><p className="text-muted-foreground text-xs font-semibold uppercase">Protection</p><p className="mt-1 text-sm font-medium">30-day one-time links</p></div><ShieldCheck className="size-6 text-emerald-600" aria-hidden="true" /></CardContent></Card>
        </div>

        <Card className="border-border/70 overflow-hidden">
          <CardHeader className="bg-muted/20 flex flex-col gap-3 border-b sm:flex-row sm:items-center sm:justify-between"><div><CardTitle className="text-base">{form.title}</CardTitle><CardDescription className="mt-1">{form.status === "published" ? "Only published forms can receive responses." : "Publish this form before sending invitations."}</CardDescription></div><Badge variant={form.status === "published" ? "default" : "outline"}>{form.status}</Badge></CardHeader>
          <CardContent className="p-0">
            {candidates.length === 0 ? <div className="text-muted-foreground px-6 py-16 text-center text-sm">No eligible students with both a verified email and missing mapped fields were found.</div> : <>
              <div className="border-b px-5 py-3"><label className="flex cursor-pointer items-center gap-3 text-sm font-medium"><input type="checkbox" checked={allSelected} onChange={() => setSelected(allSelected ? [] : candidates.map((candidate) => candidate.model_id))} /> Select all eligible students</label></div>
              <div className="divide-border divide-y">{candidates.map((candidate) => <label key={candidate.model_id} className="hover:bg-muted/20 flex cursor-pointer items-center gap-4 px-5 py-4 transition"><input type="checkbox" checked={selected.includes(candidate.model_id)} onChange={() => toggle(candidate.model_id)} /><span className="min-w-0 flex-1"><span className="block truncate text-sm font-medium">{candidate.name || "Student record"}</span><span className="text-muted-foreground mt-1 block truncate text-xs">{candidate.email}{candidate.student_number ? ` · ID ${candidate.student_number}` : ""}</span></span><Badge variant="outline">Incomplete</Badge></label>)}</div>
            </>}
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
}
