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
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Download, ShieldAlert } from "lucide-react";

interface Props {
  user: User;
  form: {
    id: string;
    title: string;
    fields: { field_key: string; label: string; is_sensitive: boolean }[];
  };
  responses: {
    id: string;
    status: string;
    respondent_user_id: string | null;
    respondent_email: string | null;
    respondent_identifier: string | null;
    submitted_at: string | null;
    latest_revision: number;
    answers: Record<string, unknown>;
    links: {
      model_key: string;
      model_id: string | null;
      status: string;
      error_message: string | null;
    }[];
  }[];
}

function value(value: unknown): string {
  if (Array.isArray(value)) return value.join(", ");
  if (value && typeof value === "object") return "Uploaded file";
  return value === null || value === undefined || value === ""
    ? "—"
    : String(value);
}

function needsManualReview(response: Props["responses"][number]): boolean {
  return response.links.some(
    (link) => link.status === "unmatched" || link.model_id === null,
  );
}

export default function FormsResponses({ user, form, responses }: Props) {
  function apply(responseId: string, overwrite: boolean): void {
    router.post(
      formsRoutes.responses.apply.url({ form: form.id, response: responseId }),
      { overwrite },
      { preserveScroll: true },
    );
  }

  return (
    <AdminLayout user={user} title={`${form.title} responses`}>
      <Head title={`${form.title} • Responses`} />
      <div className="mx-auto flex w-full max-w-[95rem] flex-col gap-6">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <Button asChild variant="ghost" size="sm" className="mb-3 -ml-3">
              <Link href={formsRoutes.index.url()}>
                <ArrowLeft className="size-4" /> All forms
              </Link>
            </Button>
            <p className="text-muted-foreground text-xs font-semibold tracking-[0.12em] uppercase">
              Response review
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-[-0.04em]">
              {form.title}
            </h1>
            <p className="text-muted-foreground mt-2 text-sm">
              Review submissions before approved answers update linked records.
            </p>
          </div>
          <Button asChild variant="outline">
            <a href={formsRoutes.responses.export.url(form.id)}>
              <Download className="size-4" /> Export CSV
            </a>
          </Button>
        </header>

        {responses.length === 0 ? (
          <Card className="border-border/70 border-dashed">
            <CardContent className="text-muted-foreground px-6 py-16 text-center text-sm">
              No responses have been submitted yet.
            </CardContent>
          </Card>
        ) : (
          <div className="flex flex-col gap-4">
            {responses.map((response) => {
              const manualReview = needsManualReview(response);

              return (
                <Card
                  key={response.id}
                  className="border-border/70 overflow-hidden"
                >
                <CardHeader className="bg-muted/20 flex flex-col gap-3 border-b sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                      {response.respondent_email ||
                        response.respondent_identifier ||
                        (response.respondent_user_id
                          ? `User ${response.respondent_user_id}`
                          : "Anonymous respondent")}
                      {response.status === "applied" && (
                        <CheckCircle2
                          className="size-4 text-emerald-600"
                          aria-label="Applied"
                        />
                      )}
                    </CardTitle>
                    <CardDescription className="mt-1">
                      Submitted{" "}
                      {response.submitted_at
                        ? new Date(response.submitted_at).toLocaleString()
                        : "—"}{" "}
                      · Revision {response.latest_revision}
                    </CardDescription>
                  </div>
                  <Badge
                    variant={
                      response.status === "applied"
                        ? "default"
                        : manualReview
                          ? "destructive"
                          : "outline"
                    }
                  >
                    {manualReview ? "manual review" : response.status}
                  </Badge>
                </CardHeader>
                <CardContent className="space-y-5 p-5">
                  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {form.fields.map((field) => (
                      <div
                        key={field.field_key}
                        className="border-border/70 rounded-lg border p-3"
                      >
                        <p className="text-muted-foreground flex items-center gap-1 text-xs font-medium">
                          {field.label}{" "}
                          {field.is_sensitive && (
                            <ShieldAlert
                              className="size-3"
                              aria-label="Sensitive"
                            />
                          )}
                        </p>
                        <p className="mt-1 text-sm break-words">
                          {value(response.answers[field.field_key])}
                        </p>
                      </div>
                    ))}
                  </div>
                  {response.links.length > 0 && (
                    <div className="border-border/70 bg-muted/20 rounded-lg border p-4 text-sm">
                      <p className="font-medium">Record links</p>
                      <div className="text-muted-foreground mt-2 flex flex-wrap gap-3 text-xs">
                        {response.links.map((link) => (
                          <span key={link.model_key}>
                            {link.model_key}: {link.model_id ?? link.status}
                          </span>
                        ))}
                    </div>
                      {manualReview && (
                        <p className="mt-3 text-xs text-amber-700">
                          No matching student record was found. Review the submitted Student ID and email manually before updating any record.
                        </p>
                      )}
                    </div>
                  )}
                  {response.status !== "applied" && !manualReview && (
                    <div className="flex flex-wrap justify-end gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => apply(response.id, false)}
                      >
                        Apply blank fields only
                      </Button>
                      <Button
                        size="sm"
                        onClick={() => apply(response.id, true)}
                      >
                        Apply and overwrite
                      </Button>
                    </div>
                  )}
                </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
