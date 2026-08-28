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
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import formsRoutes from "@/routes/administrators/forms";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
  ArrowDown,
  ArrowUp,
  Eye,
  GripVertical,
  Plus,
  Save,
  Trash2,
} from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

interface FormField {
  field_key: string;
  label: string;
  type: string;
  description: string | null;
  section: string | null;
  required: boolean;
  options: Record<string, string>;
  validation: Record<string, string | number>;
  visibility: { field?: string; operator?: string; value?: string } | null;
  presentation: { control?: string; placeholder?: string; input_mode?: string; suggestion_source?: string; suggestion_limit?: number; unit?: string };
  behavior: { missing_only?: boolean };
  mapping: { model?: string; path?: string } | null;
  is_sensitive: boolean;
}

interface FormData {
  title: string;
  slug: string;
  description: string;
  access_mode: string;
  identity_type: string;
  closes_at: string;
  settings: { allow_resubmit: boolean; allow_unverified_guest_response?: boolean; confirmation_message?: string; mapping_mode?: string; invitation_expiry_days?: number };
  fields: FormField[];
}

interface Props {
  user: User;
  form: (Partial<FormData> & { id?: string; status?: string }) | null;
  supported_types: string[];
  models: { key: string; label: string }[];
  model_fields: Record<
    string,
    { key: string; label: string; write_paths?: string[] }[]
  >;
}

const typeLabels: Record<string, string> = {
  text: "Short text",
  textarea: "Long text",
  email: "Email",
  phone: "Phone",
  number: "Number",
  year: "Year",
  date: "Date",
  select: "Dropdown",
  radio: "Single choice",
  checkbox: "Multiple choice",
  yes_no: "Yes / No",
  file: "File upload",
  rating: "Rating / scale",
};

function blankField(position: number): FormField {
  return {
    field_key: `question_${position + 1}`,
    label: `Question ${position + 1}`,
    type: "text",
    description: null,
    section: null,
    required: false,
    options: {},
    validation: {},
    visibility: null,
    mapping: null,
    is_sensitive: false,
    presentation: { control: "auto", input_mode: "text", suggestion_source: "none" },
    behavior: { missing_only: false },
  };
}

function initialData(form: Props["form"]): FormData {
  return {
    title: form?.title ?? "",
    slug: form?.slug ?? "",
    description: form?.description ?? "",
    access_mode: form?.access_mode ?? "authenticated",
    identity_type: form?.identity_type ?? "email",
    closes_at: form?.closes_at ? form.closes_at.slice(0, 16) : "",
    settings: {
      allow_resubmit: Boolean(form?.settings?.allow_resubmit),
      allow_unverified_guest_response:
        form?.settings?.allow_unverified_guest_response ??
        (form?.access_mode === "guest_identifier" &&
          form?.identity_type === "student_id"),
      confirmation_message: form?.settings?.confirmation_message ?? "",
      mapping_mode: form?.settings?.mapping_mode ?? "review",
      invitation_expiry_days: Number(form?.settings?.invitation_expiry_days ?? 30),
    },
    fields: form?.fields?.length
      ? (form.fields as FormField[])
      : [blankField(0)],
  };
}

export default function FormsBuilder({
  user,
  form,
  supported_types,
  models,
  model_fields,
}: Props) {
  const [fields, setFields] = useState<FormField[]>(initialData(form).fields);
  const data = useMemo(() => initialData(form), [form]);
  const formState = useForm<FormData>({ ...data, fields });
  const isEditing = Boolean(form?.id);
  const [isPublishing, setIsPublishing] = useState(false);

  function updateField(index: number, patch: Partial<FormField>): void {
    setFields((current) =>
      current.map((field, fieldIndex) =>
        fieldIndex === index ? { ...field, ...patch } : field,
      ),
    );
  }

  function removeField(index: number): void {
    setFields((current) =>
      current.filter((_, fieldIndex) => fieldIndex !== index),
    );
  }

  function moveField(index: number, direction: -1 | 1): void {
    const next = index + direction;
    if (next < 0 || next >= fields.length) return;
    setFields((current) => {
      const result = [...current];
      [result[index], result[next]] = [result[next], result[index]];
      return result;
    });
  }

  function addField(): void {
    setFields((current) => [...current, blankField(current.length)]);
  }

  function submit(event: React.FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    const payload = { ...formState.data, fields };
    if (isEditing && form?.id) {
      formState.transform(() => payload);
      formState.put(formsRoutes.update.url(form.id), {
        preserveScroll: true,
        onSuccess: () =>
          toast.success("Form saved", {
            description: "Your changes have been saved.",
          }),
        onError: () =>
          toast.error("Unable to save form", {
            description: "Please review the highlighted fields and try again.",
          }),
      });
      return;
    }
    formState.transform(() => payload);
    formState.post(formsRoutes.store.url(), {
      preserveScroll: true,
      onSuccess: () =>
        toast.success("Form saved", {
          description: "Your form is ready for its next steps.",
        }),
      onError: () =>
        toast.error("Unable to save form", {
          description: "Please review the highlighted fields and try again.",
        }),
    });
  }

  function publish(): void {
    if (!form?.id || isPublishing) return;

    setIsPublishing(true);
    router.post(formsRoutes.publish.url(form.id), {}, {
      preserveScroll: true,
      onSuccess: () =>
        toast.success("Form published", {
          description: "The form is now ready to receive responses.",
        }),
      onError: () =>
        toast.error("Unable to publish form", {
          description: "Please review the form and try again.",
        }),
      onFinish: () => setIsPublishing(false),
    });
  }

  return (
    <AdminLayout user={user} title={isEditing ? "Edit Form" : "Create Form"}>
      <Head title={isEditing ? `Edit ${data.title}` : "Create Online Form"} />
      <form
        onSubmit={submit}
        className="mx-auto flex w-full max-w-[90rem] flex-col gap-6"
      >
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="text-muted-foreground text-xs font-semibold tracking-[0.12em] uppercase">
              Form builder
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-[-0.04em]">
              {isEditing ? "Shape the experience" : "Create a new form"}
            </h1>
              <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">
              Keep questions clear, map only approved fields, and choose whether
              linked answers are reviewed or fill blanks automatically.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" asChild>
              <Link href={formsRoutes.index.url()}>Cancel</Link>
            </Button>
            {isEditing && form?.status !== "published" && (
              <Button
                type="button"
                variant="secondary"
                onClick={publish}
                disabled={isPublishing}
              >
                {isPublishing ? "Publishing…" : "Publish"}
              </Button>
            )}
            <Button type="submit" disabled={formState.processing}>
              <Save className="size-4" />
              {formState.processing ? "Saving…" : "Save form"}
            </Button>
          </div>
        </header>

        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="flex flex-col gap-5">
            <Card className="border-border/70">
              <CardHeader>
                <CardTitle>Form details</CardTitle>
                <CardDescription>
                  Set the public title, access mode, and response behavior.
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-5 sm:grid-cols-2">
                <div className="space-y-2 sm:col-span-2">
                  <Label htmlFor="form-title">Title</Label>
                  <Input
                    id="form-title"
                    value={formState.data.title}
                    onChange={(event) =>
                      formState.setData("title", event.target.value)
                    }
                    placeholder="Student information update"
                    required
                  />
                  {formState.errors.title && (
                    <p className="text-destructive text-xs">
                      {formState.errors.title}
                    </p>
                  )}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="form-slug">Public slug</Label>
                  <Input
                    id="form-slug"
                    value={formState.data.slug}
                    onChange={(event) =>
                      formState.setData("slug", event.target.value)
                    }
                    placeholder="student-information"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="form-closes">Closes at</Label>
                  <Input
                    id="form-closes"
                    type="datetime-local"
                    value={formState.data.closes_at}
                    onChange={(event) =>
                      formState.setData("closes_at", event.target.value)
                    }
                  />
                </div>
                <div className="space-y-2 sm:col-span-2">
                  <Label htmlFor="form-description">Description</Label>
                  <Textarea
                    id="form-description"
                    value={formState.data.description}
                    onChange={(event) =>
                      formState.setData("description", event.target.value)
                    }
                    placeholder="Explain why respondents should complete this form."
                    rows={4}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="access-mode">Who can respond?</Label>
                  <select
                    id="access-mode"
                    className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                    value={formState.data.access_mode}
                    onChange={(event) =>
                      formState.setData("access_mode", event.target.value)
                    }
                  >
                    <option value="authenticated">Authenticated users</option>
                    <option value="guest_identifier">
                      Guests with verified email or ID
                    </option>
                    <option value="anonymous">Anyone anonymously</option>
                    <option value="invitation">Personal email invitations</option>
                  </select>
                </div>
                {formState.data.access_mode === "guest_identifier" && (
                  <div className="space-y-2">
                    <Label htmlFor="identity-type">Guest verification</Label>
                    <select
                      id="identity-type"
                      className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                      value={formState.data.identity_type}
                      onChange={(event) => {
                        const identityType = event.target.value;
                        formState.setData("identity_type", identityType);
                        if (identityType !== "student_id") {
                          formState.setData("settings", {
                            ...formState.data.settings,
                            allow_unverified_guest_response: false,
                          });
                        }
                      }}
                    >
                      <option value="email">Email address</option>
                      <option value="student_id">Student ID + registered email</option>
                    </select>
                    {formState.data.identity_type === "student_id" && (
                      <div className="space-y-3">
                        <p className="text-muted-foreground text-xs">
                          Guests must verify both values before mapped student
                          fields are prefilled.
                        </p>
                        <label className="text-muted-foreground flex items-start gap-2 text-sm">
                          <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={Boolean(
                              formState.data.settings
                                .allow_unverified_guest_response,
                            )}
                            onChange={(event) =>
                              formState.setData("settings", {
                                ...formState.data.settings,
                                allow_unverified_guest_response:
                                  event.target.checked,
                              })
                            }
                          />
                          <span>
                            <span className="text-foreground block font-medium">
                              Allow unmatched responses for manual review
                            </span>
                            <span className="mt-1 block text-xs">
                              If no student record matches, save the response
                              without linking or updating a record.
                            </span>
                          </span>
                        </label>
                      </div>
                    )}
                  </div>
                )}
                {formState.data.access_mode === "invitation" && (
                  <div className="space-y-2">
                    <Label htmlFor="invitation-expiry">Invitation expiry (days)</Label>
                    <Input id="invitation-expiry" type="number" min={1} max={90} value={formState.data.settings.invitation_expiry_days ?? 30} onChange={(event) => formState.setData("settings", { ...formState.data.settings, invitation_expiry_days: Number(event.target.value) })} />
                  </div>
                )}
                <label className="text-muted-foreground flex items-center gap-2 text-sm sm:col-span-2">
                  <input
                    type="checkbox"
                    checked={formState.data.settings.allow_resubmit}
                    onChange={(event) =>
                      formState.setData("settings", {
                        ...formState.data.settings,
                        allow_resubmit: event.target.checked,
                      })
                    }
                  />
                  Allow an identity to submit a new revision
                </label>
                <div className="space-y-2 sm:col-span-2">
                  <Label htmlFor="confirmation-message">
                    Confirmation message
                  </Label>
                  <Input
                    id="confirmation-message"
                    value={formState.data.settings.confirmation_message ?? ""}
                    onChange={(event) =>
                      formState.setData("settings", {
                        ...formState.data.settings,
                        confirmation_message: event.target.value,
                      })
                    }
                    placeholder="Your response has been recorded."
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mapping-mode">Linked record behavior</Label>
                  <select id="mapping-mode" className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm" value={formState.data.settings.mapping_mode ?? "review"} onChange={(event) => formState.setData("settings", { ...formState.data.settings, mapping_mode: event.target.value })}>
                    <option value="review">Review before applying</option>
                    <option value="auto_fill_empty">Apply blank fields immediately</option>
                  </select>
                </div>
              </CardContent>
            </Card>

            <div className="flex items-center justify-between gap-4">
              <div>
                <h2 className="text-xl font-semibold">Questions</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                  Drag-and-drop ordering is represented by the arrows for
                  keyboard-friendly editing.
                </p>
              </div>
              <Button type="button" variant="outline" onClick={addField}>
                <Plus className="size-4" /> Add question
              </Button>
            </div>

            {fields.map((field, index) => (
              <Card
                key={`${field.field_key}-${index}`}
                className="border-border/70"
              >
                <CardHeader className="flex flex-row items-start justify-between gap-4 pb-4">
                  <div className="flex min-w-0 items-start gap-3">
                    <GripVertical
                      className="text-muted-foreground mt-2 size-4 shrink-0"
                      aria-hidden="true"
                    />
                    <div>
                      <CardTitle className="text-base">
                        Question {index + 1}
                      </CardTitle>
                      <CardDescription>
                        {typeLabels[field.type] ?? field.type}
                      </CardDescription>
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => moveField(index, -1)}
                      disabled={index === 0}
                      aria-label="Move question up"
                    >
                      <ArrowUp className="size-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => moveField(index, 1)}
                      disabled={index === fields.length - 1}
                      aria-label="Move question down"
                    >
                      <ArrowDown className="size-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => removeField(index)}
                      disabled={fields.length === 1}
                      aria-label="Remove question"
                    >
                      <Trash2 className="text-destructive size-4" />
                    </Button>
                  </div>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor={`field-label-${index}`}>
                      Question label
                    </Label>
                    <Input
                      id={`field-label-${index}`}
                      value={field.label}
                      onChange={(event) =>
                        updateField(index, { label: event.target.value })
                      }
                      required
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor={`field-section-${index}`}>Section</Label>
                    <Input id={`field-section-${index}`} value={field.section ?? ""} onChange={(event) => updateField(index, { section: event.target.value })} placeholder="Personal details" />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor={`field-type-${index}`}>Answer type</Label>
                    <select
                      id={`field-type-${index}`}
                      className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                      value={field.type}
                      onChange={(event) =>
                        updateField(index, { type: event.target.value })
                      }
                    >
                      {supported_types.map((type) => (
                        <option key={type} value={type}>
                          {typeLabels[type] ?? type}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor={`field-key-${index}`}>Stable key</Label>
                    <Input
                      id={`field-key-${index}`}
                      value={field.field_key}
                      onChange={(event) =>
                        updateField(index, { field_key: event.target.value })
                      }
                      required
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor={`field-description-${index}`}>
                      Help text
                    </Label>
                    <Input
                      id={`field-description-${index}`}
                      value={field.description ?? ""}
                      onChange={(event) =>
                        updateField(index, { description: event.target.value })
                      }
                      placeholder="Optional guidance"
                    />
                  </div>
                  {["select", "radio", "checkbox", "yes_no"].includes(
                    field.type,
                  ) && (
                    <div className="space-y-2 sm:col-span-2">
                      <Label htmlFor={`field-options-${index}`}>Options</Label>
                      <Input
                        id={`field-options-${index}`}
                        value={Object.entries(field.options ?? {})
                          .map(([key, label]) => `${key}|${label}`)
                          .join(", ")}
                        onChange={(event) => {
                          const options = Object.fromEntries(
                            event.target.value
                              .split(",")
                              .map((option) => option.trim())
                              .filter(Boolean)
                              .map((option) => {
                                const [key, ...label] = option.split("|");
                                return [
                                  key.trim(),
                                  (label.join("|") || key).trim(),
                                ];
                              }),
                          );
                          updateField(index, { options });
                        }}
                        placeholder="male|Male, female|Female"
                      />
                    </div>
                  )}
                  <div className="flex flex-wrap items-center gap-5 sm:col-span-2">
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={field.required}
                        onChange={(event) =>
                          updateField(index, { required: event.target.checked })
                        }
                      />{" "}
                      Required
                    </label>
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={field.is_sensitive}
                        onChange={(event) =>
                          updateField(index, {
                            is_sensitive: event.target.checked,
                          })
                        }
                      />{" "}
                      Sensitive answer
                    </label>
                  </div>
                  {models.length > 0 && (
                    <div className="border-border/70 bg-muted/20 grid gap-4 rounded-lg border p-4 sm:col-span-2 sm:grid-cols-2">
                      <div className="sm:col-span-2">
                        <p className="text-sm font-medium">Record mapping</p>
                        <p className="text-muted-foreground mt-1 text-xs">
                          Optional. Mapped answers are reviewed before they
                          update records.
                        </p>
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor={`field-model-${index}`}>Model</Label>
                        <select
                          id={`field-model-${index}`}
                          className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                          value={field.mapping?.model ?? ""}
                          onChange={(event) =>
                            updateField(index, {
                              mapping: event.target.value
                                ? { model: event.target.value }
                                : null,
                            })
                          }
                        >
                          <option value="">No mapping</option>
                          {models.map((model) => (
                            <option key={model.key} value={model.key}>
                              {model.label}
                            </option>
                          ))}
                        </select>
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor={`field-path-${index}`}>
                          Approved field
                        </Label>
                        <select
                          id={`field-path-${index}`}
                          className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                          value={field.mapping?.path ?? ""}
                          onChange={(event) =>
                            updateField(index, {
                              mapping: field.mapping?.model
                                ? {
                                    model: field.mapping.model,
                                    path: event.target.value,
                                  }
                                : null,
                            })
                          }
                          disabled={!field.mapping?.model}
                        >
                          <option value="">Choose an approved field</option>
                          {(
                            model_fields[field.mapping?.model ?? ""] ?? []
                          ).flatMap((modelField) =>
                            (modelField.write_paths ?? []).map((path) => (
                              <option
                                key={`${modelField.key}-${path}`}
                                value={path}
                              >
                                {modelField.label} · {path}
                              </option>
                            )),
                          )}
                        </select>
                      </div>
                    </div>
                  )}
                  <div className="border-border/70 bg-muted/20 grid gap-4 rounded-lg border p-4 sm:col-span-2 sm:grid-cols-2">
                    <div className="space-y-2"><Label htmlFor={`field-control-${index}`}>Presentation</Label><select id={`field-control-${index}`} className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm" value={field.presentation?.control ?? "auto"} onChange={(event) => updateField(index, { presentation: { ...field.presentation, control: event.target.value } })}><option value="auto">Automatic</option><option value="input">Text input</option><option value="select">Select</option><option value="radio_cards">Radio cards</option><option value="combobox">Searchable suggestions</option></select></div>
                    <div className="space-y-2"><Label htmlFor={`field-placeholder-${index}`}>Placeholder</Label><Input id={`field-placeholder-${index}`} value={field.presentation?.placeholder ?? ""} onChange={(event) => updateField(index, { presentation: { ...field.presentation, placeholder: event.target.value } })} placeholder="Start typing…" /></div>
                    <div className="space-y-2"><Label htmlFor={`field-input-mode-${index}`}>Mobile keyboard</Label><select id={`field-input-mode-${index}`} className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm" value={field.presentation?.input_mode ?? "text"} onChange={(event) => updateField(index, { presentation: { ...field.presentation, input_mode: event.target.value } })}><option value="text">Text</option><option value="tel">Phone</option><option value="numeric">Numbers</option><option value="decimal">Decimal</option><option value="email">Email</option></select></div>
                    <div className="space-y-2"><Label htmlFor={`field-unit-${index}`}>Unit (optional)</Label><Input id={`field-unit-${index}`} value={field.presentation?.unit ?? ""} onChange={(event) => updateField(index, { presentation: { ...field.presentation, unit: event.target.value } })} placeholder="cm, kg" /></div>
                    <label className="text-muted-foreground flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" checked={Boolean(field.behavior?.missing_only)} onChange={(event) => updateField(index, { behavior: { ...field.behavior, missing_only: event.target.checked } })} /> Show only when the linked record is blank</label>
                  </div>
                  {formState.errors[`fields.${index}.label`] && (
                    <p className="text-destructive text-xs sm:col-span-2">
                      {formState.errors[`fields.${index}.label`]}
                    </p>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>

          <aside className="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
            <Card className="border-border/70 bg-muted/20">
              <CardHeader>
                <CardTitle className="text-base">
                  Publishing checklist
                </CardTitle>
                <CardDescription>
                  Before sharing the public link.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <p className="flex gap-2">
                  <Badge variant="outline">1</Badge> Confirm every question has
                  a clear label.
                </p>
                <p className="flex gap-2">
                  <Badge variant="outline">2</Badge> Mark personal data as
                  sensitive.
                </p>
                <p className="flex gap-2">
                  <Badge variant="outline">3</Badge> Verify mapped fields and
                  review policy.
                </p>
                <p className="flex gap-2">
                  <Badge variant="outline">4</Badge> Publish only when the form
                  is ready.
                </p>
              </CardContent>
            </Card>
            {isEditing && form?.status === "published" && (
                <Button
                type="button"
                variant="outline"
                onClick={() => window.open(formsRoutes.preview.url(form.id), "_blank")}
              >
                <Eye className="size-4" /> Preview form
              </Button>
            )}
            {isEditing && form?.id && (
              <Button type="button" variant="outline" onClick={() => { const name = window.prompt("Save this form as a template", data.title); if (name?.trim()) router.post(formsRoutes.templates.save.url(form.id), { name }, { preserveScroll: true }); }}>
                Save as template
              </Button>
            )}
          </aside>
        </div>
      </form>
    </AdminLayout>
  );
}
