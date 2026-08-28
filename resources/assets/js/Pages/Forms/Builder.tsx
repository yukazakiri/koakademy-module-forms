import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Field, FieldDescription, FieldGroup } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import formsRoutes from "@/routes/administrators/forms";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
  ArrowDown,
  ArrowUp,
  Check,
  ClipboardList,
  Eye,
  GripVertical,
  Lightbulb,
  Plus,
  Save,
  Settings2,
  Trash2,
  X,
} from "lucide-react";
import { useMemo, useState, type FormEvent, type HTMLAttributes } from "react";
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
  presentation: {
    control?: string;
    placeholder?: string;
    input_mode?: string;
    suggestion_source?: string;
    suggestion_limit?: number;
    unit?: string;
  };
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
  settings: {
    allow_resubmit: boolean;
    allow_unverified_guest_response?: boolean;
    confirmation_message?: string;
    mapping_mode?: string;
    invitation_expiry_days?: number;
  };
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

const typeDescriptions: Record<string, string> = {
  text: "A single line for names, places, or short answers.",
  textarea: "A larger space for explanations or longer responses.",
  email: "Validates that the answer looks like an email address.",
  phone: "Optimized for phone numbers on mobile devices.",
  number: "Accepts numeric values, such as height or weight.",
  year: "Accepts a four-digit year.",
  date: "A calendar date selected by the respondent.",
  select: "A single answer chosen from your list of options.",
  radio: "Shows every choice at once for a single answer.",
  checkbox: "Lets respondents choose more than one answer.",
  yes_no: "A simple yes or no decision.",
  file: "Allows a respondent to upload a file.",
  rating: "A score or scale response.",
};

const choiceTypes = ["select", "radio", "checkbox"];

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
    presentation: {
      control: "auto",
      input_mode: "text",
      suggestion_source: "none",
    },
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
      invitation_expiry_days: Number(
        form?.settings?.invitation_expiry_days ?? 30,
      ),
    },
    fields: form?.fields?.length
      ? (form.fields as FormField[])
      : [blankField(0)],
  };
}

function optionKey(label: string, options: Record<string, string>): string {
  const base =
    label
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_|_$/g, "") || "option";
  let key = base;
  let suffix = 2;
  while (Object.prototype.hasOwnProperty.call(options, key)) {
    key = `${base}_${suffix}`;
    suffix += 1;
  }
  return key;
}

function optionItems(options: Record<string, string>): ComboboxOption[] {
  return Object.entries(options).map(([value, label]) => ({
    value,
    label,
    searchText: `${label} ${value}`,
  }));
}

interface OptionEditorProps {
  field: FormField;
  onAdd: (value: string) => void;
  onRename: (key: string, label: string) => void;
  onRemove: (key: string) => void;
}

function OptionEditor({ field, onAdd, onRename, onRemove }: OptionEditorProps) {
  const options = Object.entries(field.options ?? {});

  return (
    <div className="bg-muted/20 border-border/70 space-y-4 rounded-xl border p-4 sm:p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <CardTitle className="text-sm">Answer choices</CardTitle>
            <Badge variant="secondary">{options.length}</Badge>
          </div>
          <p className="text-muted-foreground mt-1 text-xs leading-5">
            Type a choice below and press Enter. Existing choices can be
            searched, renamed, or removed at any time.
          </p>
        </div>
        <Badge variant="outline">Student-facing list</Badge>
      </div>

      <Combobox
        options={optionItems(field.options ?? {})}
        value=""
        onValueChange={onAdd}
        placeholder="Add or search for a choice…"
        searchPlaceholder="Type a new choice or search existing ones…"
        emptyText="Type a value to add a new choice."
        allowCreate
        createLabel="Add choice"
      />

      {options.length === 0 ? (
        <div className="border-border/70 bg-background/70 text-muted-foreground flex items-center gap-3 rounded-lg border border-dashed p-4 text-sm">
          <Plus className="size-4" />
          Add at least two choices so respondents know what they can select.
        </div>
      ) : (
        <div className="space-y-2">
          {options.map(([key, label], optionIndex) => (
            <div
              key={key}
              className="border-border/70 bg-background flex items-center gap-3 rounded-lg border p-2"
            >
              <span className="bg-muted text-muted-foreground flex size-7 shrink-0 items-center justify-center rounded-md text-xs font-semibold">
                {optionIndex + 1}
              </span>
              <Input
                value={label}
                onChange={(event) => onRename(key, event.target.value)}
                aria-label={`Choice ${optionIndex + 1}`}
                placeholder={`Choice ${optionIndex + 1}`}
                className="border-0 bg-transparent shadow-none focus-visible:ring-0"
              />
              <code className="text-muted-foreground hidden shrink-0 text-[11px] sm:block">
                {key}
              </code>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                onClick={() => onRemove(key)}
                aria-label={`Remove ${label || `choice ${optionIndex + 1}`}`}
              >
                <X className="text-muted-foreground size-4" />
              </Button>
            </div>
          ))}
        </div>
      )}

      <p className="text-muted-foreground text-xs leading-5">
        The value key is kept stable for saved responses while the display label
        remains easy to edit.
      </p>
    </div>
  );
}

export default function FormsBuilder({
  user,
  form,
  supported_types,
  models,
  model_fields,
}: Props) {
  const data = useMemo(() => initialData(form), [form]);
  const [fields, setFields] = useState<FormField[]>(data.fields);
  const [isPublishing, setIsPublishing] = useState(false);
  const formState = useForm<FormData>({ ...data, fields });
  const isEditing = Boolean(form?.id);
  const requiredCount = fields.filter((field) => field.required).length;
  const choiceCount = fields.filter((field) =>
    choiceTypes.includes(field.type),
  ).length;

  function updateField(index: number, patch: Partial<FormField>): void {
    setFields((current) =>
      current.map((field, fieldIndex) =>
        fieldIndex === index ? { ...field, ...patch } : field,
      ),
    );
  }

  function updatePresentation(
    index: number,
    patch: FormField["presentation"],
  ): void {
    setFields((current) =>
      current.map((field, fieldIndex) =>
        fieldIndex === index
          ? { ...field, presentation: { ...field.presentation, ...patch } }
          : field,
      ),
    );
  }

  function updateSettings(patch: Partial<FormData["settings"]>): void {
    formState.setData("settings", { ...formState.data.settings, ...patch });
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

  function changeFieldType(index: number, type: string): void {
    const field = fields[index];
    if (!field) return;

    const isChoice = choiceTypes.includes(type);
    const options =
      isChoice && Object.keys(field.options ?? {}).length === 0
        ? { option_1: "Option 1", option_2: "Option 2" }
        : field.options;
    const currentControl = field.presentation?.control ?? "auto";
    const control = isChoice
      ? currentControl === "auto"
        ? type === "select"
          ? "select"
          : "radio_cards"
        : currentControl
      : ["input", "combobox"].includes(currentControl)
        ? currentControl
        : "auto";

    updateField(index, {
      type,
      options,
      presentation: { ...field.presentation, control },
    });
  }

  function addOption(index: number, value: string): void {
    const label = value.trim();
    if (!label) return;

    setFields((current) =>
      current.map((field, fieldIndex) => {
        if (fieldIndex !== index) return field;
        const existing = Object.entries(field.options ?? {}).some(
          ([key, existingLabel]) =>
            key.toLowerCase() === label.toLowerCase() ||
            existingLabel.toLowerCase() === label.toLowerCase(),
        );
        if (existing) return field;
        return {
          ...field,
          options: {
            ...field.options,
            [optionKey(label, field.options ?? {})]: label,
          },
        };
      }),
    );
  }

  function renameOption(index: number, key: string, label: string): void {
    setFields((current) =>
      current.map((field, fieldIndex) =>
        fieldIndex === index
          ? { ...field, options: { ...field.options, [key]: label } }
          : field,
      ),
    );
  }

  function removeOption(index: number, key: string): void {
    setFields((current) =>
      current.map((field, fieldIndex) => {
        if (fieldIndex !== index) return field;
        const options = { ...field.options };
        delete options[key];
        return { ...field, options };
      }),
    );
  }

  function submit(event: FormEvent<HTMLFormElement>): void {
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
    router.post(
      formsRoutes.publish.url(form.id),
      {},
      {
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
      },
    );
  }

  const statusLabel = form?.status === "published" ? "Published" : "Draft";

  return (
    <AdminLayout user={user} title={isEditing ? "Edit Form" : "Create Form"}>
      <Head title={isEditing ? `Edit ${data.title}` : "Create Online Form"} />
      <form
        onSubmit={submit}
        className="mx-auto flex w-full max-w-[96rem] flex-col gap-6"
      >
        <header className="border-border/70 bg-card relative overflow-hidden rounded-2xl border p-6 shadow-sm sm:p-8">
          <div className="from-primary/10 pointer-events-none absolute -top-28 -right-20 size-72 rounded-full bg-gradient-to-br to-emerald-400/10 blur-3xl" />
          <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">Forms editor</Badge>
                <Badge
                  variant={statusLabel === "Published" ? "default" : "outline"}
                >
                  {statusLabel}
                </Badge>
                {isEditing && (
                  <span className="text-muted-foreground text-xs">
                    Changes are saved manually
                  </span>
                )}
              </div>
              <h1 className="mt-4 text-3xl font-semibold tracking-[-0.045em] sm:text-4xl">
                {isEditing
                  ? data.title || "Untitled form"
                  : "Create a new form"}
              </h1>
              <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">
                Build a clear response flow with familiar controls. Add helpful
                context, then preview exactly what students will see.
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
          </div>
        </header>

        <Tabs defaultValue="questions" className="w-full">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <TabsList
              variant="underline"
              className="w-full justify-start sm:w-auto"
            >
              <TabsTrigger value="questions" className="gap-2">
                <ClipboardList className="size-4" />
                Questions
                <Badge
                  variant="secondary"
                  className="h-5 min-w-5 justify-center px-1.5"
                >
                  {fields.length}
                </Badge>
              </TabsTrigger>
              <TabsTrigger value="settings" className="gap-2">
                <Settings2 className="size-4" /> Settings
              </TabsTrigger>
            </TabsList>
            <div className="text-muted-foreground flex items-center gap-4 text-xs">
              <span>
                <strong className="text-foreground">{requiredCount}</strong>{" "}
                required
              </span>
              <Separator orientation="vertical" className="h-4" />
              <span>
                <strong className="text-foreground">{choiceCount}</strong>{" "}
                choice questions
              </span>
            </div>
          </div>

          <TabsContent value="questions" className="mt-6">
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_19rem]">
              <div className="flex min-w-0 flex-col gap-5">
                <Card className="border-primary/20 bg-primary/[0.035] border-dashed shadow-none">
                  <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div className="flex items-start gap-3">
                      <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg">
                        <Lightbulb className="size-4" />
                      </div>
                      <div>
                        <p className="text-sm font-semibold">
                          Make every question easy to answer
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs leading-5">
                          Use help text for context, placeholders for examples,
                          and dropdown choices when the answer should be
                          standardized.
                        </p>
                      </div>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={addField}
                      className="shrink-0"
                    >
                      <Plus className="size-4" /> Add question
                    </Button>
                  </CardContent>
                </Card>

                {fields.map((field, index) => {
                  const fieldType = typeLabels[field.type] ?? field.type;
                  const typeOptions = supported_types.map((type) => ({
                    value: type,
                    label: typeLabels[type] ?? type,
                    description: typeDescriptions[type],
                  }));
                  const mappingOptions = models.map((model) => ({
                    value: model.key,
                    label: model.label,
                    searchText: `${model.label} ${model.key}`,
                  }));
                  const approvedFieldOptions = (
                    model_fields[field.mapping?.model ?? ""] ?? []
                  ).flatMap((modelField) =>
                    (modelField.write_paths ?? []).map((path) => ({
                      value: path,
                      label: `${modelField.label} · ${path}`,
                      searchText: `${modelField.label} ${path}`,
                    })),
                  );
                  const isChoice = choiceTypes.includes(field.type);
                  const inputMode = field.presentation
                    ?.input_mode as HTMLAttributes<HTMLInputElement>["inputMode"];

                  return (
                    <Card
                      key={`${field.field_key}-${index}`}
                      className="border-border/70 overflow-hidden shadow-sm transition-shadow hover:shadow-md"
                    >
                      <div className="bg-primary h-1 w-full" />
                      <CardHeader className="flex flex-row items-start justify-between gap-4 pb-4">
                        <div className="flex min-w-0 items-start gap-3">
                          <div className="text-muted-foreground flex items-center gap-2 pt-1">
                            <GripVertical
                              className="size-4"
                              aria-hidden="true"
                            />
                            <span className="bg-muted flex size-8 items-center justify-center rounded-lg text-sm font-semibold">
                              {index + 1}
                            </span>
                          </div>
                          <div className="min-w-0">
                            <CardTitle className="truncate text-base">
                              {field.label || `Question ${index + 1}`}
                            </CardTitle>
                            <CardDescription className="mt-1 flex flex-wrap items-center gap-2">
                              <span>{fieldType}</span>
                              {field.required && (
                                <Badge variant="outline">Required</Badge>
                              )}
                            </CardDescription>
                          </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => moveField(index, -1)}
                            disabled={index === 0}
                            aria-label="Move question up"
                          >
                            <ArrowUp className="size-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => moveField(index, 1)}
                            disabled={index === fields.length - 1}
                            aria-label="Move question down"
                          >
                            <ArrowDown className="size-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => removeField(index)}
                            disabled={fields.length === 1}
                            aria-label="Remove question"
                          >
                            <Trash2 className="text-destructive size-4" />
                          </Button>
                        </div>
                      </CardHeader>

                      <CardContent className="space-y-6">
                        <FieldGroup className="grid gap-5 md:grid-cols-2">
                          <Field>
                            <Label htmlFor={`field-label-${index}`}>
                              Question
                            </Label>
                            <Input
                              id={`field-label-${index}`}
                              value={field.label}
                              onChange={(event) =>
                                updateField(index, {
                                  label: event.target.value,
                                })
                              }
                              placeholder="What should the student answer?"
                              required
                            />
                            <FieldDescription>
                              Write the question exactly as students should
                              understand it.
                            </FieldDescription>
                          </Field>
                          <Field>
                            <Label htmlFor={`field-type-${index}`}>
                              Answer type
                            </Label>
                            <Combobox
                              options={typeOptions}
                              value={field.type}
                              onValueChange={(value) =>
                                changeFieldType(index, value)
                              }
                              placeholder="Choose an answer type…"
                              searchPlaceholder="Search answer types…"
                              emptyText="No answer type found."
                            />
                            <FieldDescription>
                              {typeDescriptions[field.type] ??
                                "Choose how students will respond."}
                            </FieldDescription>
                          </Field>
                          <Field>
                            <Label htmlFor={`field-section-${index}`}>
                              Section
                            </Label>
                            <Input
                              id={`field-section-${index}`}
                              value={field.section ?? ""}
                              onChange={(event) =>
                                updateField(index, {
                                  section: event.target.value,
                                })
                              }
                              placeholder="Personal details"
                            />
                            <FieldDescription>
                              Group related questions under a shared heading.
                            </FieldDescription>
                          </Field>
                          <Field>
                            <Label htmlFor={`field-key-${index}`}>
                              Internal key
                            </Label>
                            <Input
                              id={`field-key-${index}`}
                              value={field.field_key}
                              onChange={(event) =>
                                updateField(index, {
                                  field_key: event.target.value,
                                })
                              }
                              placeholder="student_address"
                              required
                            />
                            <FieldDescription>
                              Stable identifier used for mappings and saved
                              responses.
                            </FieldDescription>
                          </Field>
                        </FieldGroup>

                        <Field>
                          <Label htmlFor={`field-description-${index}`}>
                            Help text
                          </Label>
                          <Textarea
                            id={`field-description-${index}`}
                            value={field.description ?? ""}
                            onChange={(event) =>
                              updateField(index, {
                                description: event.target.value,
                              })
                            }
                            placeholder="Explain what belongs in this answer or give a useful example."
                            rows={3}
                          />
                          <FieldDescription>
                            This appears below the question for students. Keep
                            it short and specific.
                          </FieldDescription>
                          {formState.errors[`fields.${index}.description`] && (
                            <p className="text-destructive text-xs">
                              {formState.errors[`fields.${index}.description`]}
                            </p>
                          )}
                        </Field>

                        {isChoice && (
                          <OptionEditor
                            field={field}
                            onAdd={(value) => addOption(index, value)}
                            onRename={(key, label) =>
                              renameOption(index, key, label)
                            }
                            onRemove={(key) => removeOption(index, key)}
                          />
                        )}
                        {field.type === "yes_no" && (
                          <div className="bg-muted/20 border-border/70 text-muted-foreground flex items-center gap-3 rounded-xl border p-4 text-sm">
                            <Check className="text-primary size-4" /> Yes and No
                            are provided automatically for this question type.
                          </div>
                        )}

                        <div className="bg-muted/20 border-border/70 rounded-xl border p-4 sm:p-5">
                          <div className="mb-4">
                            <p className="text-sm font-semibold">
                              Display and behavior
                            </p>
                            <p className="text-muted-foreground mt-1 text-xs leading-5">
                              Control how the answer appears and guide students
                              while they type.
                            </p>
                          </div>
                          <FieldGroup className="grid gap-5 md:grid-cols-2">
                            <Field>
                              <Label htmlFor={`field-control-${index}`}>
                                Student control
                              </Label>
                              <Select
                                value={field.presentation?.control ?? "auto"}
                                onValueChange={(value) =>
                                  updatePresentation(index, { control: value })
                                }
                              >
                                <SelectTrigger id={`field-control-${index}`}>
                                  <SelectValue placeholder="Automatic" />
                                </SelectTrigger>
                                <SelectContent>
                                  <SelectItem value="auto">
                                    Automatic
                                  </SelectItem>
                                  <SelectItem value="input">
                                    Text input
                                  </SelectItem>
                                  {isChoice && (
                                    <SelectItem value="select">
                                      Dropdown
                                    </SelectItem>
                                  )}
                                  {isChoice && (
                                    <SelectItem value="radio_cards">
                                      Choice cards
                                    </SelectItem>
                                  )}
                                  <SelectItem value="combobox">
                                    Searchable combobox
                                  </SelectItem>
                                </SelectContent>
                              </Select>
                              <FieldDescription>
                                Use Searchable combobox when students need to
                                find an item quickly.
                              </FieldDescription>
                            </Field>
                            <Field>
                              <Label htmlFor={`field-placeholder-${index}`}>
                                Placeholder
                              </Label>
                              <Input
                                id={`field-placeholder-${index}`}
                                value={field.presentation?.placeholder ?? ""}
                                onChange={(event) =>
                                  updatePresentation(index, {
                                    placeholder: event.target.value,
                                  })
                                }
                                placeholder="e.g. Quezon City, Metro Manila"
                              />
                              <FieldDescription>
                                Example text shown before a student enters an
                                answer.
                              </FieldDescription>
                            </Field>
                            <Field>
                              <Label htmlFor={`field-input-mode-${index}`}>
                                Mobile keyboard
                              </Label>
                              <Select
                                value={field.presentation?.input_mode ?? "text"}
                                onValueChange={(value) =>
                                  updatePresentation(index, {
                                    input_mode: value,
                                  })
                                }
                              >
                                <SelectTrigger id={`field-input-mode-${index}`}>
                                  <SelectValue placeholder="Text" />
                                </SelectTrigger>
                                <SelectContent>
                                  <SelectItem value="text">Text</SelectItem>
                                  <SelectItem value="tel">Phone</SelectItem>
                                  <SelectItem value="numeric">
                                    Numbers
                                  </SelectItem>
                                  <SelectItem value="decimal">
                                    Decimal
                                  </SelectItem>
                                  <SelectItem value="email">Email</SelectItem>
                                </SelectContent>
                              </Select>
                            </Field>
                            <Field>
                              <Label htmlFor={`field-unit-${index}`}>
                                Unit{" "}
                                <span className="text-muted-foreground font-normal">
                                  (optional)
                                </span>
                              </Label>
                              <Input
                                id={`field-unit-${index}`}
                                value={field.presentation?.unit ?? ""}
                                onChange={(event) =>
                                  updatePresentation(index, {
                                    unit: event.target.value,
                                  })
                                }
                                placeholder="cm, kg, or %"
                              />
                            </Field>
                            {field.presentation?.control === "combobox" &&
                              !isChoice && (
                                <Field>
                                  <Label htmlFor={`field-suggestions-${index}`}>
                                    Suggestion source
                                  </Label>
                                  <Select
                                    value={
                                      field.presentation?.suggestion_source ??
                                      "none"
                                    }
                                    onValueChange={(value) =>
                                      updatePresentation(index, {
                                        suggestion_source: value,
                                      })
                                    }
                                  >
                                    <SelectTrigger
                                      id={`field-suggestions-${index}`}
                                    >
                                      <SelectValue placeholder="No suggestions" />
                                    </SelectTrigger>
                                    <SelectContent>
                                      <SelectItem value="none">
                                        No suggestions
                                      </SelectItem>
                                      <SelectItem value="static">
                                        Saved suggestions
                                      </SelectItem>
                                      <SelectItem value="record_values">
                                        Values from linked records
                                      </SelectItem>
                                    </SelectContent>
                                  </Select>
                                  <FieldDescription>
                                    Suggestions help respondents find a value;
                                    they do not limit the answer.
                                  </FieldDescription>
                                </Field>
                              )}
                          </FieldGroup>
                          <div className="mt-5 grid gap-3 border-t pt-4 sm:grid-cols-2">
                            <label className="border-border/70 bg-background hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors">
                              <Switch
                                checked={field.required}
                                onCheckedChange={(checked) =>
                                  updateField(index, { required: checked })
                                }
                              />
                              <span>
                                <span className="block text-sm font-medium">
                                  Required question
                                </span>
                                <span className="text-muted-foreground mt-0.5 block text-xs">
                                  Students must answer before submitting.
                                </span>
                              </span>
                            </label>
                            <label className="border-border/70 bg-background hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors">
                              <Switch
                                checked={field.is_sensitive}
                                onCheckedChange={(checked) =>
                                  updateField(index, { is_sensitive: checked })
                                }
                              />
                              <span>
                                <span className="block text-sm font-medium">
                                  Sensitive answer
                                </span>
                                <span className="text-muted-foreground mt-0.5 block text-xs">
                                  Protect this answer in review and exports.
                                </span>
                              </span>
                            </label>
                            <label className="border-border/70 bg-background hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors sm:col-span-2">
                              <Switch
                                checked={Boolean(field.behavior?.missing_only)}
                                onCheckedChange={(checked) =>
                                  updateField(index, {
                                    behavior: {
                                      ...field.behavior,
                                      missing_only: checked,
                                    },
                                  })
                                }
                              />
                              <span>
                                <span className="block text-sm font-medium">
                                  Show only when the linked record is blank
                                </span>
                                <span className="text-muted-foreground mt-0.5 block text-xs">
                                  Useful for profile completion forms that
                                  should not ask for known information again.
                                </span>
                              </span>
                            </label>
                          </div>
                        </div>

                        {models.length > 0 && (
                          <div className="border-border/70 bg-muted/20 rounded-xl border p-4 sm:p-5">
                            <div className="mb-4">
                              <p className="text-sm font-semibold">
                                Record mapping
                              </p>
                              <p className="text-muted-foreground mt-1 text-xs leading-5">
                                Optional. Connect the answer to an approved
                                field on a linked record.
                              </p>
                            </div>
                            <FieldGroup className="grid gap-5 md:grid-cols-2">
                              <Field>
                                <Label>Record model</Label>
                                <Combobox
                                  options={mappingOptions}
                                  value={field.mapping?.model ?? ""}
                                  onValueChange={(value) =>
                                    updateField(index, {
                                      mapping: value ? { model: value } : null,
                                    })
                                  }
                                  placeholder="No record mapping"
                                  searchPlaceholder="Search record models…"
                                  emptyText="No model found."
                                />
                              </Field>
                              <Field>
                                <Label>Approved field</Label>
                                <Combobox
                                  options={approvedFieldOptions}
                                  value={field.mapping?.path ?? ""}
                                  onValueChange={(value) =>
                                    updateField(index, {
                                      mapping: field.mapping?.model
                                        ? {
                                            model: field.mapping.model,
                                            path: value,
                                          }
                                        : null,
                                    })
                                  }
                                  placeholder={
                                    field.mapping?.model
                                      ? "Choose an approved field"
                                      : "Choose a model first"
                                  }
                                  searchPlaceholder="Search approved fields…"
                                  emptyText="No approved field found."
                                  disabled={!field.mapping?.model}
                                />
                              </Field>
                            </FieldGroup>
                          </div>
                        )}

                        {formState.errors[`fields.${index}.label`] && (
                          <p className="text-destructive text-xs">
                            {formState.errors[`fields.${index}.label`]}
                          </p>
                        )}
                      </CardContent>
                    </Card>
                  );
                })}

                <Button
                  type="button"
                  variant="outline"
                  onClick={addField}
                  className="self-start"
                >
                  <Plus className="size-4" /> Add another question
                </Button>
              </div>

              <aside className="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
                <Card className="border-border/70 bg-muted/20">
                  <CardHeader>
                    <CardTitle className="text-base">Editor guide</CardTitle>
                    <CardDescription>
                      A quick checklist while you build.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4 text-sm">
                    {[
                      "Give each question one clear job.",
                      "Use help text to explain unfamiliar terms.",
                      "Use a dropdown when answers must stay consistent.",
                      "Preview before publishing to check the student flow.",
                    ].map((tip) => (
                      <div key={tip} className="flex gap-3">
                        <div className="bg-primary/10 text-primary mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full">
                          <Check className="size-3" />
                        </div>
                        <p className="text-muted-foreground leading-5">{tip}</p>
                      </div>
                    ))}
                  </CardContent>
                </Card>
                {isEditing && form?.status === "published" && (
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                      window.open(formsRoutes.preview.url(form.id), "_blank")
                    }
                  >
                    <Eye className="size-4" /> Preview form
                  </Button>
                )}
                {isEditing && form?.id && (
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      const name = window.prompt(
                        "Save this form as a template",
                        data.title,
                      );
                      if (name?.trim())
                        router.post(
                          formsRoutes.templates.save.url(form.id),
                          { name },
                          { preserveScroll: true },
                        );
                    }}
                  >
                    Save as template
                  </Button>
                )}
              </aside>
            </div>
          </TabsContent>

          <TabsContent value="settings" className="mt-6">
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_19rem]">
              <div className="flex flex-col gap-5">
                <Card className="border-border/70">
                  <CardHeader>
                    <CardTitle>Form details</CardTitle>
                    <CardDescription>
                      Set the identity and purpose of this form.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <FieldGroup className="grid gap-5 md:grid-cols-2">
                      <Field className="md:col-span-2">
                        <Label htmlFor="form-title">Form title</Label>
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
                      </Field>
                      <Field>
                        <Label htmlFor="form-slug">Public slug</Label>
                        <Input
                          id="form-slug"
                          value={formState.data.slug}
                          onChange={(event) =>
                            formState.setData("slug", event.target.value)
                          }
                          placeholder="student-information"
                        />
                        <FieldDescription>
                          Used in links for forms that allow public access.
                        </FieldDescription>
                      </Field>
                      <Field>
                        <Label htmlFor="form-closes">Closes at</Label>
                        <Input
                          id="form-closes"
                          type="datetime-local"
                          value={formState.data.closes_at}
                          onChange={(event) =>
                            formState.setData("closes_at", event.target.value)
                          }
                        />
                      </Field>
                      <Field className="md:col-span-2">
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
                        <FieldDescription>
                          This appears at the top of the published form.
                        </FieldDescription>
                      </Field>
                    </FieldGroup>
                  </CardContent>
                </Card>

                <Card className="border-border/70">
                  <CardHeader>
                    <CardTitle>Response access</CardTitle>
                    <CardDescription>
                      Decide who can respond and how their identity is checked.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <FieldGroup className="grid gap-5 md:grid-cols-2">
                      <Field>
                        <Label>Who can respond?</Label>
                        <Select
                          value={formState.data.access_mode}
                          onValueChange={(value) =>
                            formState.setData("access_mode", value)
                          }
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Choose access" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="authenticated">
                              Authenticated users
                            </SelectItem>
                            <SelectItem value="guest_identifier">
                              Guests with verified email or ID
                            </SelectItem>
                            <SelectItem value="anonymous">
                              Anyone anonymously
                            </SelectItem>
                            <SelectItem value="invitation">
                              Personal email invitations
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </Field>
                      {formState.data.access_mode === "guest_identifier" && (
                        <Field>
                          <Label>Guest verification</Label>
                          <Select
                            value={formState.data.identity_type}
                            onValueChange={(value) => {
                              formState.setData("identity_type", value);
                              if (value !== "student_id")
                                updateSettings({
                                  allow_unverified_guest_response: false,
                                });
                            }}
                          >
                            <SelectTrigger>
                              <SelectValue placeholder="Choose verification" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="email">
                                Email address
                              </SelectItem>
                              <SelectItem value="student_id">
                                Student ID + registered email
                              </SelectItem>
                            </SelectContent>
                          </Select>
                          <FieldDescription>
                            Students must verify these details before mapped
                            fields are prefilled.
                          </FieldDescription>
                        </Field>
                      )}
                      {formState.data.access_mode === "invitation" && (
                        <Field>
                          <Label htmlFor="invitation-expiry">
                            Invitation expiry
                          </Label>
                          <div className="flex items-center gap-2">
                            <Input
                              id="invitation-expiry"
                              type="number"
                              min={1}
                              max={90}
                              value={
                                formState.data.settings
                                  .invitation_expiry_days ?? 30
                              }
                              onChange={(event) =>
                                updateSettings({
                                  invitation_expiry_days: Number(
                                    event.target.value,
                                  ),
                                })
                              }
                            />
                            <span className="text-muted-foreground text-sm">
                              days
                            </span>
                          </div>
                        </Field>
                      )}
                      {formState.data.access_mode === "guest_identifier" &&
                        formState.data.identity_type === "student_id" && (
                          <label className="border-border/70 bg-muted/20 hover:bg-muted/40 flex cursor-pointer items-start gap-3 rounded-lg border p-4 md:col-span-2">
                            <Checkbox
                              checked={Boolean(
                                formState.data.settings
                                  .allow_unverified_guest_response,
                              )}
                              onCheckedChange={(checked) =>
                                updateSettings({
                                  allow_unverified_guest_response:
                                    checked === true,
                                })
                              }
                            />
                            <span>
                              <span className="block text-sm font-medium">
                                Allow unmatched responses for manual review
                              </span>
                              <span className="text-muted-foreground mt-1 block text-xs leading-5">
                                If no student record matches, save the response
                                without linking or updating a record.
                              </span>
                            </span>
                          </label>
                        )}
                    </FieldGroup>
                  </CardContent>
                </Card>

                <Card className="border-border/70">
                  <CardHeader>
                    <CardTitle>Response behavior</CardTitle>
                    <CardDescription>
                      Choose what happens after a student submits.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <FieldGroup className="grid gap-5 md:grid-cols-2">
                      <Field>
                        <Label>Linked record behavior</Label>
                        <Select
                          value={
                            formState.data.settings.mapping_mode ?? "review"
                          }
                          onValueChange={(value) =>
                            updateSettings({ mapping_mode: value })
                          }
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Choose mapping behavior" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="review">
                              Review before applying
                            </SelectItem>
                            <SelectItem value="auto_fill_empty">
                              Apply blank fields immediately
                            </SelectItem>
                          </SelectContent>
                        </Select>
                        <FieldDescription>
                          Review is safest; automatic mode only fills empty
                          linked fields.
                        </FieldDescription>
                      </Field>
                      <label className="border-border/70 bg-muted/20 hover:bg-muted/40 flex cursor-pointer items-start gap-3 rounded-lg border p-4">
                        <Switch
                          checked={formState.data.settings.allow_resubmit}
                          onCheckedChange={(checked) =>
                            updateSettings({ allow_resubmit: checked })
                          }
                        />
                        <span>
                          <span className="block text-sm font-medium">
                            Allow resubmission
                          </span>
                          <span className="text-muted-foreground mt-1 block text-xs leading-5">
                            Let the same identity submit a new revision.
                          </span>
                        </span>
                      </label>
                      <Field className="md:col-span-2">
                        <Label htmlFor="confirmation-message">
                          Confirmation message
                        </Label>
                        <Textarea
                          id="confirmation-message"
                          value={
                            formState.data.settings.confirmation_message ?? ""
                          }
                          onChange={(event) =>
                            updateSettings({
                              confirmation_message: event.target.value,
                            })
                          }
                          placeholder="Your response has been recorded."
                          rows={3}
                        />
                      </Field>
                    </FieldGroup>
                  </CardContent>
                </Card>
              </div>
              <aside className="xl:sticky xl:top-6 xl:self-start">
                <Card className="border-border/70 bg-muted/20">
                  <CardHeader>
                    <CardTitle className="text-base">
                      Settings at a glance
                    </CardTitle>
                    <CardDescription>
                      These choices shape the response journey.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3 text-sm">
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-muted-foreground">Access</span>
                      <Badge variant="outline">
                        {formState.data.access_mode.replace("_", " ")}
                      </Badge>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-muted-foreground">Questions</span>
                      <span className="font-medium">{fields.length}</span>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-muted-foreground">Required</span>
                      <span className="font-medium">{requiredCount}</span>
                    </div>
                    <Separator />
                    <p className="text-muted-foreground text-xs leading-5">
                      Save your changes before switching tabs or leaving this
                      page.
                    </p>
                  </CardContent>
                </Card>
              </aside>
            </div>
          </TabsContent>
        </Tabs>
      </form>
    </AdminLayout>
  );
}
