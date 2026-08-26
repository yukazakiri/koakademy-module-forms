import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import publicForms from "@/routes/forms";
import { Head, useForm } from "@inertiajs/react";
import { Check, FileUp, LockKeyhole, Send } from "lucide-react";
import { useMemo } from "react";

interface FormField {
  key: string;
  label: string;
  type: string;
  description: string | null;
  required: boolean;
  options: Record<string, string>;
  visibility: { field?: string; operator?: string; value?: string } | null;
}

interface Props {
  form: {
    id: string;
    slug: string;
    title: string;
    description: string | null;
    access_mode: string;
    identity_type: string | null;
    fields: FormField[];
  };
  authenticated: boolean;
  user: { name?: string; email?: string } | null;
}

function visible(field: FormField, answers: Record<string, unknown>): boolean {
  if (!field.visibility?.field) return true;
  const actual = answers[field.visibility.field];
  const expected = field.visibility.value;
  if (field.visibility.operator === "not_equals") return actual !== expected;
  if (field.visibility.operator === "contains")
    return Array.isArray(actual) && actual.includes(expected);
  return actual === expected;
}

export default function PublicFormShow({ form, authenticated, user }: Props) {
  const formState = useForm<{
    respondent_email: string;
    respondent_identifier: string;
    answers: Record<string, unknown>;
  }>({
    respondent_email: user?.email ?? "",
    respondent_identifier: "",
    answers: {},
  });
  const visibleFields = useMemo(
    () => form.fields.filter((field) => visible(field, formState.data.answers)),
    [form.fields, formState.data.answers],
  );

  function setAnswer(key: string, value: unknown): void {
    formState.setData("answers", { ...formState.data.answers, [key]: value });
  }

  function submit(event: React.FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    formState.post(publicForms.submit.url(form.slug), {
      forceFormData: true,
      preserveScroll: true,
    });
  }

  return (
    <>
      <Head title={form.title} />
      <main className="bg-muted/30 min-h-screen px-4 py-8 sm:px-6 sm:py-14">
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
          <header className="border-border/70 bg-card relative overflow-hidden rounded-2xl border p-6 shadow-sm sm:p-9">
            <div className="from-primary/15 pointer-events-none absolute -top-28 -right-16 size-64 rounded-full bg-gradient-to-br to-emerald-500/10 blur-3xl" />
            <div className="relative">
              <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl">
                <LockKeyhole className="size-5" aria-hidden="true" />
              </div>
              <h1 className="mt-6 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">
                {form.title}
              </h1>
              {form.description && (
                <p className="text-muted-foreground mt-3 text-sm leading-6 sm:text-base">
                  {form.description}
                </p>
              )}
              <p className="text-muted-foreground mt-5 text-xs">
                {form.access_mode === "authenticated"
                  ? "Your response will be connected to your account."
                  : "Please provide accurate information before submitting."}
              </p>
            </div>
          </header>

          <form onSubmit={submit} className="flex flex-col gap-4">
            {form.access_mode === "guest_identifier" && (
              <section className="border-border/70 bg-card rounded-xl border p-5 shadow-sm">
                <label
                  className="flex flex-col gap-2 text-sm font-medium"
                  htmlFor="respondent-identity"
                >
                  {form.identity_type === "student_id"
                    ? "Student ID"
                    : "Email address"}
                  {form.identity_type === "student_id" ? (
                    <Input
                      id="respondent-identity"
                      value={formState.data.respondent_identifier}
                      onChange={(event) =>
                        formState.setData(
                          "respondent_identifier",
                          event.target.value,
                        )
                      }
                      required
                    />
                  ) : (
                    <Input
                      id="respondent-identity"
                      type="email"
                      value={formState.data.respondent_email}
                      onChange={(event) =>
                        formState.setData(
                          "respondent_email",
                          event.target.value,
                        )
                      }
                      required
                    />
                  )}
                </label>
              </section>
            )}

            {visibleFields.map((field, index) => (
              <section
                key={field.key}
                className="border-border/70 bg-card rounded-xl border p-5 shadow-sm sm:p-6"
              >
                <div className="flex items-start gap-3">
                  <span className="bg-primary/10 text-primary mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                    {index + 1}
                  </span>
                  <div className="min-w-0 flex-1">
                    <label
                      className="text-sm font-semibold"
                      htmlFor={`answer-${field.key}`}
                    >
                      {field.label}
                      {field.required && (
                        <span
                          className="text-destructive ml-1"
                          aria-label="required"
                        >
                          *
                        </span>
                      )}
                    </label>
                    {field.description && (
                      <p className="text-muted-foreground mt-1 text-xs leading-5">
                        {field.description}
                      </p>
                    )}
                    <div className="mt-4">
                      {field.type === "textarea" ? (
                        <Textarea
                          id={`answer-${field.key}`}
                          value={String(
                            formState.data.answers[field.key] ?? "",
                          )}
                          onChange={(event) =>
                            setAnswer(field.key, event.target.value)
                          }
                          required={field.required}
                          rows={5}
                        />
                      ) : field.type === "file" ? (
                        <Input
                          id={`answer-${field.key}`}
                          type="file"
                          onChange={(event) =>
                            setAnswer(
                              field.key,
                              event.target.files?.[0] ?? null,
                            )
                          }
                          required={field.required}
                        />
                      ) : field.type === "select" ? (
                        <select
                          id={`answer-${field.key}`}
                          className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                          value={String(
                            formState.data.answers[field.key] ?? "",
                          )}
                          onChange={(event) =>
                            setAnswer(field.key, event.target.value)
                          }
                          required={field.required}
                        >
                          <option value="">Choose an option</option>
                          {Object.entries(field.options).map(([key, label]) => (
                            <option key={key} value={key}>
                              {label}
                            </option>
                          ))}
                        </select>
                      ) : field.type === "radio" || field.type === "yes_no" ? (
                        <div className="grid gap-2 sm:grid-cols-2">
                          {Object.entries(field.options).map(([key, label]) => (
                            <label
                              key={key}
                              className="border-border/70 hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm"
                            >
                              <input
                                type="radio"
                                name={`answer-${field.key}`}
                                value={key}
                                checked={
                                  formState.data.answers[field.key] === key
                                }
                                onChange={() => setAnswer(field.key, key)}
                                required={field.required}
                              />
                              {label}
                            </label>
                          ))}
                        </div>
                      ) : field.type === "checkbox" ? (
                        <div className="grid gap-2 sm:grid-cols-2">
                          {Object.entries(field.options).map(([key, label]) => {
                            const values = Array.isArray(
                              formState.data.answers[field.key],
                            )
                              ? (formState.data.answers[field.key] as string[])
                              : [];
                            return (
                              <label
                                key={key}
                                className="border-border/70 hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm"
                              >
                                <input
                                  type="checkbox"
                                  value={key}
                                  checked={values.includes(key)}
                                  onChange={(event) =>
                                    setAnswer(
                                      field.key,
                                      event.target.checked
                                        ? [...values, key]
                                        : values.filter(
                                            (value) => value !== key,
                                          ),
                                    )
                                  }
                                />
                                {label}
                              </label>
                            );
                          })}
                        </div>
                      ) : field.type === "rating" ? (
                        <div className="flex flex-wrap gap-2">
                          {Array.from(
                            { length: 5 },
                            (_, value) => value + 1,
                          ).map((score) => (
                            <label
                              key={score}
                              className={`flex size-10 cursor-pointer items-center justify-center rounded-full border text-sm ${formState.data.answers[field.key] === score ? "bg-primary text-primary-foreground border-primary" : "border-border/70"}`}
                            >
                              <input
                                className="sr-only"
                                type="radio"
                                name={`answer-${field.key}`}
                                value={score}
                                checked={
                                  formState.data.answers[field.key] === score
                                }
                                onChange={() => setAnswer(field.key, score)}
                                required={field.required}
                              />
                              <span>{score}</span>
                            </label>
                          ))}
                        </div>
                      ) : (
                        <Input
                          id={`answer-${field.key}`}
                          type={
                            field.type === "number"
                              ? "number"
                              : field.type === "date"
                                ? "date"
                                : field.type === "email"
                                  ? "email"
                                  : "text"
                          }
                          value={String(
                            formState.data.answers[field.key] ?? "",
                          )}
                          onChange={(event) =>
                            setAnswer(field.key, event.target.value)
                          }
                          required={field.required}
                        />
                      )}
                      {formState.errors[`answers.${field.key}`] && (
                        <p className="text-destructive mt-2 text-xs">
                          {formState.errors[`answers.${field.key}`]}
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              </section>
            ))}

            {Object.keys(formState.errors).length > 0 &&
              formState.errors.form && (
                <p className="text-destructive text-sm" role="alert">
                  {formState.errors.form}
                </p>
              )}
            <div className="flex items-center justify-between gap-4 pt-2">
              <p className="text-muted-foreground text-xs">
                <Check className="mr-1 inline size-3.5" /> Responses are
                protected and reviewed according to school policy.
              </p>
              <Button type="submit" size="lg" disabled={formState.processing}>
                <Send className="size-4" />
                {formState.processing ? "Submitting…" : "Submit response"}
              </Button>
            </div>
          </form>
        </div>
      </main>
    </>
  );
}
