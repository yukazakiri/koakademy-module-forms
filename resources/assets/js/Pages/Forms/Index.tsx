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
import formsRoutes from "@/routes/administrators/forms";
import publicForms from "@/routes/forms";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import {
  ClipboardList,
  ExternalLink,
  FilePlus2,
  FormInput,
  ShieldCheck,
} from "lucide-react";

interface FormSummary {
  id: string;
  title: string;
  slug: string;
  description: string | null;
  status: "draft" | "published" | "closed";
  access_mode: string;
  responses_count: number;
  closes_at: string | null;
}

interface Props {
  user: User;
  forms: FormSummary[];
}

const statusClass: Record<FormSummary["status"], string> = {
  draft: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
  published: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
  closed: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
};

const accessLabels: Record<string, string> = {
  authenticated: "Authenticated",
  guest_identifier: "Guest identifier",
  anonymous: "Anonymous",
  invitation: "Personal invitation",
};

export default function FormsIndex({ user, forms }: Props) {
  return (
    <AdminLayout user={user} title="Online Forms">
      <Head title="Online Forms" />

      <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-6">
        <header className="border-border/70 bg-card/80 relative overflow-hidden rounded-2xl border px-6 py-7 shadow-sm backdrop-blur-xl sm:px-8 sm:py-9">
          <div className="from-primary/15 pointer-events-none absolute -top-28 -right-16 size-72 rounded-full bg-gradient-to-br to-emerald-500/10 blur-3xl" />
          <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-3xl">
              <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-xl">
                <FormInput className="size-5" aria-hidden="true" />
              </div>
              <p className="text-muted-foreground mt-5 text-xs font-semibold tracking-[0.12em] uppercase">
                Collect what matters
              </p>
              <h1 className="text-foreground mt-2 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">
                Online Forms
              </h1>
              <p className="text-muted-foreground mt-3 max-w-2xl text-sm leading-6 sm:text-base">
                Build surveys, evaluations, and student information requests
                that stay connected to the right records.
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button asChild variant="outline" size="lg"><Link href={formsRoutes.templates.index.url()}><FormInput className="size-4" /> Templates</Link></Button>
              <Button asChild size="lg"><Link href={formsRoutes.create.url()}><FilePlus2 className="size-4" /> Create form</Link></Button>
            </div>
          </div>
        </header>

        <div className="grid gap-4 md:grid-cols-3">
          <Card className="border-border/70 bg-card/70">
            <CardContent className="flex items-center justify-between gap-4 p-5">
              <div>
                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  Total forms
                </p>
                <p className="mt-1 text-3xl font-semibold">{forms.length}</p>
              </div>
              <ClipboardList
                className="text-primary size-6"
                aria-hidden="true"
              />
            </CardContent>
          </Card>
          <Card className="border-border/70 bg-card/70">
            <CardContent className="flex items-center justify-between gap-4 p-5">
              <div>
                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  Published
                </p>
                <p className="mt-1 text-3xl font-semibold">
                  {forms.filter((form) => form.status === "published").length}
                </p>
              </div>
              <ShieldCheck
                className="size-6 text-emerald-600"
                aria-hidden="true"
              />
            </CardContent>
          </Card>
          <Card className="border-border/70 bg-card/70">
            <CardContent className="flex items-center justify-between gap-4 p-5">
              <div>
                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  Responses
                </p>
                <p className="mt-1 text-3xl font-semibold">
                  {forms.reduce(
                    (total, form) => total + form.responses_count,
                    0,
                  )}
                </p>
              </div>
              <ClipboardList
                className="size-6 text-sky-600"
                aria-hidden="true"
              />
            </CardContent>
          </Card>
        </div>

        {forms.length === 0 ? (
          <Card className="border-border/70 border-dashed">
            <CardContent className="flex flex-col items-center gap-3 px-6 py-16 text-center">
              <FormInput
                className="text-muted-foreground size-9"
                aria-hidden="true"
              />
              <div>
                <p className="font-medium">No forms yet</p>
                <p className="text-muted-foreground mt-1 text-sm">
                  Create your first reusable school form or survey.
                </p>
              </div>
              <Button asChild variant="outline">
                <Link href={formsRoutes.create.url()}>
                  Create your first form
                </Link>
              </Button>
            </CardContent>
          </Card>
        ) : (
          <section aria-label="Forms" className="grid gap-4 lg:grid-cols-2">
            {forms.map((form) => (
              <Card
                key={form.id}
                className="border-border/70 flex h-full flex-col"
              >
                <CardHeader className="flex flex-row items-start justify-between gap-4">
                  <div className="min-w-0">
                    <CardTitle className="truncate">{form.title}</CardTitle>
                    <CardDescription className="mt-1 line-clamp-2">
                      {form.description || "No description provided."}
                    </CardDescription>
                  </div>
                  <Badge className={statusClass[form.status]}>
                    {form.status}
                  </Badge>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col gap-5">
                  <div className="text-muted-foreground flex flex-wrap gap-x-5 gap-y-2 text-xs">
                    <span>
                      {accessLabels[form.access_mode] ?? form.access_mode}
                    </span>
                    <span>
                      {form.responses_count} response
                      {form.responses_count === 1 ? "" : "s"}
                    </span>
                    <span className="font-mono">/forms/{form.slug}</span>
                  </div>
                  <div className="mt-auto flex flex-wrap gap-2">
                    <Button asChild size="sm">
                      <Link href={formsRoutes.edit.url(form.id)}>
                        Edit form
                      </Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                      <Link href={formsRoutes.responses.index.url(form.id)}>
                        View responses
                      </Link>
                    </Button>
                    {form.access_mode === "invitation" && (
                      <Button asChild variant="outline" size="sm"><Link href={formsRoutes.invitations.index.url(form.id)}><ShieldCheck className="size-3.5" /> Invitations</Link></Button>
                    )}
                    {form.status === "published" && (
                      <Button asChild variant="ghost" size="sm">
                        <a
                          href={publicForms.show.url(form.slug)}
                          target="_blank"
                          rel="noreferrer"
                        >
                          Open form <ExternalLink className="size-3.5" />
                        </a>
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}
          </section>
        )}
      </div>
    </AdminLayout>
  );
}
