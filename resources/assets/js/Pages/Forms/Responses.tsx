import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
  permissions?: { responses_manage?: boolean };
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

function respondentLabel(response: Props["responses"][number]): string {
  return (
    response.respondent_email ||
    response.respondent_identifier ||
    (response.respondent_user_id
      ? `User ${response.respondent_user_id}`
      : "Anonymous respondent")
  );
}

export default function FormsResponses({
  user,
  form,
  responses,
  permissions,
}: Props) {
  const canManageResponses = permissions?.responses_manage === true;

  function apply(responseId: string, overwrite: boolean): void {
    router.post(
      formsRoutes.responses.apply.url({ form: form.id, response: responseId }),
      { overwrite },
      { preserveScroll: true },
    );
  }

  function createRecord(responseId: string): void {
    router.post(
      formsRoutes.responses.createRecord.url({
        form: form.id,
        response: responseId,
      }),
      {},
      { preserveScroll: true },
    );
  }

  function updateResponse(responseId: string, status: string): void {
    router.put(
      formsRoutes.responses.update.url({ form: form.id, response: responseId }),
      { status },
      { preserveScroll: true },
    );
  }

  function deleteResponse(responseId: string): void {
    if (!window.confirm("Delete this response permanently?")) {
      return;
    }

    router.delete(
      formsRoutes.responses.delete.url({ form: form.id, response: responseId }),
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
          <Card className="border-border/70 overflow-hidden">
            <CardHeader className="bg-muted/20 flex flex-col gap-2 border-b px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <div>
                <CardTitle className="text-base">
                  Response spreadsheet
                </CardTitle>
                <p className="text-muted-foreground mt-1 text-xs">
                  {responses.length} submission
                  {responses.length === 1 ? "" : "s"} · Scroll horizontally to
                  review every answer.
                </p>
              </div>
              <div className="text-muted-foreground flex items-center gap-2 text-xs">
                <span className="inline-flex items-center gap-1">
                  <ShieldAlert className="size-3" /> Sensitive fields are marked
                </span>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full min-w-[68rem] border-collapse text-sm">
                  <caption className="sr-only">
                    Submitted responses for {form.title}
                  </caption>
                  <thead>
                    <tr className="bg-muted/40 border-b text-left">
                      <th className="text-muted-foreground sticky left-0 z-20 min-w-56 border-r bg-muted/40 px-4 py-3 text-xs font-semibold tracking-wide uppercase">
                        Respondent
                      </th>
                      <th className="text-muted-foreground min-w-44 border-r px-4 py-3 text-xs font-semibold tracking-wide uppercase">
                        Submitted
                      </th>
                      <th className="text-muted-foreground min-w-28 border-r px-4 py-3 text-xs font-semibold tracking-wide uppercase">
                        Status
                      </th>
                      {form.fields.map((field) => (
                        <th
                          key={field.field_key}
                          className="text-muted-foreground min-w-48 max-w-72 border-r px-4 py-3 text-xs font-semibold tracking-wide uppercase"
                          title={field.label}
                        >
                          <span className="flex items-center gap-1">
                            <span className="truncate">{field.label}</span>
                            {field.is_sensitive && (
                              <ShieldAlert
                                className="size-3 shrink-0"
                                aria-label="Sensitive"
                              />
                            )}
                          </span>
                        </th>
                      ))}
                      <th className="text-muted-foreground min-w-64 px-4 py-3 text-xs font-semibold tracking-wide uppercase">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {responses.map((response, index) => {
                      const manualReview = needsManualReview(response);
                      const rowLabel = respondentLabel(response);

                      return (
                        <tr
                          key={response.id}
                          className={`border-b last:border-b-0 ${index % 2 === 1 ? "bg-muted/10" : "bg-background"}`}
                        >
                          <th className="text-foreground sticky left-0 z-10 min-w-56 border-r bg-inherit px-4 py-4 text-left align-top font-medium">
                            <div className="flex items-start gap-2">
                              <span className="max-w-48 break-words">
                                {rowLabel}
                              </span>
                              {response.status === "applied" && (
                                <CheckCircle2
                                  className="mt-0.5 size-4 shrink-0 text-emerald-600"
                                  aria-label="Applied"
                                />
                              )}
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                              Revision {response.latest_revision}
                            </p>
                          </th>
                          <td className="text-muted-foreground min-w-44 border-r px-4 py-4 align-top text-xs whitespace-nowrap">
                            {response.submitted_at
                              ? new Date(response.submitted_at).toLocaleString()
                              : "—"}
                          </td>
                          <td className="min-w-28 border-r px-4 py-4 align-top">
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
                          </td>
                          {form.fields.map((field) => (
                            <td
                              key={field.field_key}
                              className="max-w-72 border-r px-4 py-4 align-top break-words"
                            >
                              <span className="line-clamp-4">
                                {value(response.answers[field.field_key])}
                              </span>
                            </td>
                          ))}
                          <td className="min-w-64 px-4 py-4 align-top">
                            <div className="flex flex-wrap gap-2">
                              {response.status !== "applied" &&
                                !manualReview && (
                                  <>
                                    <Button
                                      variant="outline"
                                      size="sm"
                                      onClick={() => apply(response.id, false)}
                                    >
                                      Apply blanks
                                    </Button>
                                    <Button
                                      size="sm"
                                      onClick={() => apply(response.id, true)}
                                    >
                                      Overwrite
                                    </Button>
                                  </>
                                )}
                              {manualReview && canManageResponses && (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => createRecord(response.id)}
                                >
                                  Create student record
                                </Button>
                              )}
                              {canManageResponses &&
                                response.status !== "applied" && (
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                      updateResponse(response.id, "reviewed")
                                    }
                                  >
                                    Mark reviewed
                                  </Button>
                                )}
                              {canManageResponses && (
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="text-destructive hover:text-destructive"
                                  onClick={() => deleteResponse(response.id)}
                                >
                                  Delete
                                </Button>
                              )}
                              {manualReview && (
                                <span className="text-muted-foreground max-w-56 text-xs leading-5">
                                  Verify the submitted identity manually before
                                  updating a record.
                                </span>
                              )}
                              {response.links.length > 0 && (
                                <span className="text-muted-foreground w-full text-xs">
                                  {response.links
                                    .map(
                                      (link) =>
                                        `${link.model_key}: ${link.model_id ?? link.status}`,
                                    )
                                    .join(" · ")}
                                </span>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AdminLayout>
  );
}
