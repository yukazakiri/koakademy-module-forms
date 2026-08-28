import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import { Textarea } from "@/components/ui/textarea";
import publicForms from "@/routes/forms";
import axios from "axios";
import { Head, useForm } from "@inertiajs/react";
import {
  Check,
  ChevronRight,
  CircleHelp,
  ClipboardCheck,
  FileUp,
  LockKeyhole,
  Send,
  ShieldCheck,
} from "lucide-react";
import { useMemo, useState } from "react";

interface FormField {
  key: string;
  label: string;
  type: string;
  description: string | null;
  required: boolean;
  options: Record<string, string>;
  visibility: { field?: string; operator?: string; value?: string } | null;
  section?: string | null;
  presentation?: {
    control?: string;
    placeholder?: string;
    input_mode?: string;
    unit?: string;
  };
  suggestions?: string[];
}

interface FormDefinition {
  id: string;
  slug: string;
  title: string;
  description: string | null;
  access_mode: string;
  identity_type: string | null;
  settings?: {
    allow_unverified_guest_response?: boolean;
    template_key?: string;
  };
  fields: FormField[];
}

interface Props {
  form: FormDefinition;
  authenticated: boolean;
  user: { name?: string; email?: string } | null;
  preview?: boolean;
  invitation_token?: string;
  invitation?: { expires_at: string | null; student_name: string | null };
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

function filled(value: unknown): boolean {
  return (
    value !== undefined && value !== null && value !== "" && value !== false
  );
}

const profileSectionDescriptions: Record<string, string> = {
  Identity:
    "Check your name and personal details carefully so they match your official records.",
  Contact:
    "Use contact details that you check regularly so the school can reach you.",
  Personal: "Provide the personal information requested by the registrar.",
  Address:
    "Write your complete address, including barangay, city, and province.",
  "Origin and Equity":
    "Answer these questions according to how you identify and what applies to you.",
  "Emergency Contact":
    "Choose someone the school can reach quickly if an emergency occurs.",
  "Parent and Guardian":
    "Enter the current details for your parent or guardian.",
  Education: "List your previous schools and graduation details accurately.",
  "Scholarship and Employment":
    "Select the options that best describe your current situation.",
};

const profileFieldDescriptions: Record<string, string> = {
  first_name:
    "Use your official first name as it appears in your school records.",
  middle_name:
    "Enter your complete middle name, or leave this blank if you do not have one.",
  last_name:
    "Use your official family name as it appears in your school records.",
  birth_date:
    "Enter the date shown on your birth certificate or school record.",
  email:
    "Use an email address that you check regularly for school communication.",
  phone: "Enter a phone number where the school can reach you.",
  current_address:
    "Include your house or unit, street, barangay, city, and province.",
  permanent_address:
    "Enter your permanent home address if it is different from your current address.",
  birthplace:
    "Enter the city or municipality and province where you were born.",
  nationality: "Enter your citizenship or nationality, for example Filipino.",
  religion: "Enter the religion you identify with, if you wish to provide it.",
  emergency_contact_name:
    "Enter the name of someone the school may contact in an emergency.",
  emergency_contact_phone: "Enter the emergency contact’s active phone number.",
  emergency_contact_relationship: "Describe how this person is related to you.",
  father_name:
    "Enter your father’s complete name as it should appear in school records.",
  mother_name:
    "Enter your mother’s complete name as it should appear in school records.",
  guardian_name: "Enter your guardian’s complete name, if applicable.",
  family_address: "Enter the complete address where your family lives.",
  scholarship_details:
    "Add the scholarship name or details that will help the registrar verify it.",
  employment_status:
    "Choose the option that best describes your current work or study status.",
};

function profilePlaceholder(field: FormField): string | undefined {
  if (field.presentation?.placeholder) return field.presentation.placeholder;

  const label = field.label.toLowerCase();
  if (field.type === "date") return "YYYY-MM-DD";
  if (field.type === "email") return "name@example.com";
  if (field.type === "phone") return "e.g. 0912 345 6789";
  if (field.type === "number") return "Enter a number";
  if (field.type === "year") return "YYYY";
  if (label.includes("address"))
    return "House no., street, barangay, city, province";

  return `Enter ${label}`;
}

export default function PublicFormShow({
  form,
  user,
  preview = false,
  invitation_token,
  invitation,
}: Props) {
  const formState = useForm<{
    respondent_email: string;
    respondent_identifier: string;
    respondent_identity_unverified: boolean;
    answers: Record<string, unknown>;
  }>({
    respondent_email: user?.email ?? "",
    respondent_identifier: "",
    respondent_identity_unverified: false,
    answers: {},
  });
  const requiresStudentVerification =
    form.access_mode === "guest_identifier" &&
    form.identity_type === "student_id" &&
    !preview;
  const allowUnverifiedGuestResponse =
    requiresStudentVerification &&
    Boolean(form.settings?.allow_unverified_guest_response);
  const [identityVerified, setIdentityVerified] = useState(
    !requiresStudentVerification,
  );
  const [identityUnverified, setIdentityUnverified] = useState(false);
  const [identityLoading, setIdentityLoading] = useState(false);
  const [identityError, setIdentityError] = useState<string | null>(null);
  const [identityFallbackAvailable, setIdentityFallbackAvailable] =
    useState(false);
  const formLocked =
    requiresStudentVerification && !identityVerified && !identityUnverified;
  const isStudentProfileForm =
    form.settings?.template_key === "student_profile_completion";
  const visibleFields = useMemo(
    () => form.fields.filter((field) => visible(field, formState.data.answers)),
    [form.fields, formState.data.answers],
  );
  const sections = useMemo(() => {
    const grouped = new Map<string, FormField[]>();
    visibleFields.forEach((field) => {
      const name = field.section || "Questions";
      grouped.set(name, [...(grouped.get(name) ?? []), field]);
    });
    return [...grouped.entries()];
  }, [visibleFields]);
  const completion = visibleFields.length
    ? Math.round(
        (visibleFields.filter((field) =>
          filled(formState.data.answers[field.key]),
        ).length /
          visibleFields.length) *
          100,
      )
    : 0;
  const completedCount = visibleFields.filter((field) =>
    filled(formState.data.answers[field.key]),
  ).length;
  const fieldNumbers = useMemo(
    () => new Map(visibleFields.map((field, index) => [field.key, index + 1])),
    [visibleFields],
  );

  function setAnswer(key: string, value: unknown): void {
    formState.setData("answers", { ...formState.data.answers, [key]: value });
  }

  function setIdentity(
    key: "respondent_identifier" | "respondent_email",
    value: string,
  ): void {
    formState.setData(key, value);
    setIdentityError(null);
    setIdentityFallbackAvailable(false);

    if (identityVerified) {
      setIdentityVerified(false);
      formState.setData("answers", {});
    }

    if (identityUnverified) {
      setIdentityUnverified(false);
      formState.setData("respondent_identity_unverified", false);
      formState.setData("answers", {});
    }
  }

  async function verifyStudent(): Promise<void> {
    setIdentityError(null);
    setIdentityFallbackAvailable(false);
    setIdentityUnverified(false);
    formState.setData("respondent_identity_unverified", false);
    setIdentityLoading(true);

    try {
      const response = await axios.post<{
        matched: boolean;
        answers: Record<string, unknown>;
      }>(publicForms.identify.url(form.slug), {
        respondent_identifier: formState.data.respondent_identifier,
        respondent_email: formState.data.respondent_email,
      });

      formState.setData("answers", response.data.answers ?? {});
      setIdentityVerified(response.data.matched === true);
    } catch (error) {
      setIdentityVerified(false);
      setIdentityUnverified(false);
      formState.setData("respondent_identity_unverified", false);
      formState.setData("answers", {});
      const responseErrors = axios.isAxiosError<{
        errors?: Record<string, string[]>;
      }>(error)
        ? error.response?.data?.errors
        : undefined;
      setIdentityFallbackAvailable(
        axios.isAxiosError(error) && error.response?.status === 422,
      );
      setIdentityError(
        responseErrors?.respondent_identifier?.[0] ??
          "We could not verify those details. Please check your Student ID and registered email.",
      );
    } finally {
      setIdentityLoading(false);
    }
  }

  function continueWithoutLookup(): void {
    setIdentityError(null);
    setIdentityFallbackAvailable(false);
    setIdentityVerified(false);
    setIdentityUnverified(true);
    formState.setData("respondent_identity_unverified", true);
    formState.setData("answers", {});
  }

  function submit(event: React.FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    if (preview || formLocked) return;

    if (invitation_token) {
      formState.post(
        publicForms.invitation.submit.url({
          form: form.slug,
          token: invitation_token,
        }),
        { forceFormData: true, preserveScroll: true },
      );
      return;
    }
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
              <div className="flex flex-wrap items-center gap-2">
                <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl">
                  {isStudentProfileForm ? (
                    <ClipboardCheck className="size-5" aria-hidden="true" />
                  ) : (
                    <LockKeyhole className="size-5" aria-hidden="true" />
                  )}
                </div>
                <Badge variant="secondary">
                  {isStudentProfileForm
                    ? "Student profile update"
                    : "Secure response"}
                </Badge>
              </div>
              <p className="text-muted-foreground mt-6 text-xs font-semibold tracking-[0.12em] uppercase">
                {preview
                  ? "Administrator preview"
                  : invitation
                    ? "Personal profile update"
                    : "Secure response"}
              </p>
              <h1 className="mt-2 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">
                {form.title}
              </h1>
              {invitation?.student_name && (
                <p className="text-muted-foreground mt-2 text-sm">
                  For {invitation.student_name}
                </p>
              )}
              {form.description && (
                <p className="text-muted-foreground mt-3 text-sm leading-6 sm:text-base">
                  {form.description}
                </p>
              )}
              {isStudentProfileForm && (
                <div className="border-primary/20 bg-primary/5 mt-6 grid gap-3 rounded-xl border p-4 sm:grid-cols-3">
                  <div className="flex gap-3">
                    <CircleHelp
                      className="text-primary mt-0.5 size-4 shrink-0"
                      aria-hidden="true"
                    />
                    <p className="text-muted-foreground text-xs leading-5">
                      Complete only the information the school still needs.
                    </p>
                  </div>
                  <div className="flex gap-3">
                    <ShieldCheck
                      className="text-primary mt-0.5 size-4 shrink-0"
                      aria-hidden="true"
                    />
                    <p className="text-muted-foreground text-xs leading-5">
                      Use details that match your official records.
                    </p>
                  </div>
                  <div className="flex gap-3">
                    <ClipboardCheck
                      className="text-primary mt-0.5 size-4 shrink-0"
                      aria-hidden="true"
                    />
                    <p className="text-muted-foreground text-xs leading-5">
                      Review your answers before submitting.
                    </p>
                  </div>
                </div>
              )}
              {invitation?.expires_at && (
                <p className="text-muted-foreground mt-5 text-xs">
                  This private link expires{" "}
                  {new Date(invitation.expires_at).toLocaleDateString()} and can
                  be used once.
                </p>
              )}
            </div>
          </header>

          <div className="border-border/70 bg-card rounded-xl border p-4 shadow-sm sm:p-5">
            <div className="flex items-center justify-between gap-4 text-sm">
              <div>
                <span className="font-medium">
                  {isStudentProfileForm
                    ? "Profile completion"
                    : "Your progress"}
                </span>
                <p className="text-muted-foreground mt-1 text-xs">
                  {completedCount} of {visibleFields.length}{" "}
                  {isStudentProfileForm ? "fields" : "questions"} answered
                </p>
              </div>
              <span className="text-primary text-lg font-semibold">
                {completion}%
              </span>
            </div>
            <Progress
              value={completion}
              className="mt-3"
              aria-label={`Form completion ${completion}%`}
            />
          </div>

          <form onSubmit={submit} className="flex flex-col gap-5">
            {form.access_mode === "guest_identifier" && (
              <section className="border-primary/20 bg-card rounded-xl border p-5 shadow-sm sm:p-6">
                {form.identity_type === "student_id" ? (
                  <div className="flex flex-col gap-4">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-full text-xs font-semibold">
                          0
                        </span>
                        <p className="text-sm font-semibold">
                          First, verify your student record
                        </p>
                      </div>
                      <p className="text-muted-foreground mt-2 pl-9 text-xs leading-5">
                        Enter both details exactly as registered with the
                        school. Matching records will automatically prefill the
                        available profile fields.
                      </p>
                    </div>
                    <label
                      className="flex flex-col gap-2 text-sm font-medium"
                      htmlFor="respondent-student-id"
                    >
                      Student ID
                      <Input
                        id="respondent-student-id"
                        value={formState.data.respondent_identifier}
                        onChange={(event) =>
                          setIdentity(
                            "respondent_identifier",
                            event.target.value,
                          )
                        }
                        placeholder="e.g. 2024-000123"
                        required
                      />
                      <span className="text-muted-foreground text-xs font-normal">
                        Use the ID shown on your student card or school record.
                      </span>
                    </label>
                    <label
                      className="flex flex-col gap-2 text-sm font-medium"
                      htmlFor="respondent-email"
                    >
                      Registered email
                      <Input
                        id="respondent-email"
                        type="email"
                        value={formState.data.respondent_email}
                        onChange={(event) =>
                          setIdentity("respondent_email", event.target.value)
                        }
                        placeholder="name@example.com"
                        required
                      />
                      <span className="text-muted-foreground text-xs font-normal">
                        Use the email address currently saved in your student
                        record.
                      </span>
                    </label>
                    <div className="flex flex-wrap items-center gap-3">
                      <Button
                        type="button"
                        variant="secondary"
                        onClick={verifyStudent}
                        disabled={
                          identityLoading ||
                          !formState.data.respondent_identifier ||
                          !formState.data.respondent_email
                        }
                      >
                        {identityLoading
                          ? "Verifying…"
                          : identityVerified
                            ? "Record verified"
                            : "Find my record"}
                      </Button>
                      {identityVerified && (
                        <span className="text-xs text-emerald-600">
                          Student record verified. You can now review the
                          prefilled fields.
                        </span>
                      )}
                    </div>
                    {identityError && (
                      <p className="text-destructive text-xs" role="alert">
                        {identityError}
                      </p>
                    )}
                    {identityError &&
                      identityFallbackAvailable &&
                      allowUnverifiedGuestResponse &&
                      !identityUnverified && (
                        <div className="border-amber-500/30 bg-amber-500/5 rounded-lg border p-4">
                          <p className="text-sm font-medium">
                            Could not find a matching student record
                          </p>
                          <p className="text-muted-foreground mt-1 text-xs leading-5">
                            You can still complete this form. It will be saved
                            for school staff to review manually and will not
                            update a student record automatically.
                          </p>
                          <Button
                            type="button"
                            variant="outline"
                            className="mt-3"
                            onClick={continueWithoutLookup}
                          >
                            Continue for manual review
                          </Button>
                        </div>
                      )}
                    {identityUnverified && (
                      <div className="border-amber-500/30 bg-amber-500/5 rounded-lg border p-4">
                        <p className="text-sm font-medium">
                          Manual review selected
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs leading-5">
                          Complete the form and submit it. Staff will verify and
                          process your details manually; no student record will
                          be updated automatically.
                        </p>
                      </div>
                    )}
                  </div>
                ) : (
                  <label
                    className="flex flex-col gap-2 text-sm font-medium"
                    htmlFor="respondent-identity"
                  >
                    Email address
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
                  </label>
                )}
              </section>
            )}

            <fieldset disabled={formLocked} className="contents">
              {sections.map(([section, fields], sectionIndex) => (
                <section key={section} className="flex flex-col gap-4">
                  <div className="flex items-start gap-3 px-1">
                    <span className="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                      {sectionIndex + 1}
                    </span>
                    <div>
                      <p className="text-primary text-xs font-semibold tracking-[0.12em] uppercase">
                        Section {sectionIndex + 1}
                      </p>
                      <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em]">
                        {section}
                      </h2>
                      {isStudentProfileForm &&
                        profileSectionDescriptions[section] && (
                          <p className="text-muted-foreground mt-1 max-w-2xl text-sm leading-6">
                            {profileSectionDescriptions[section]}
                          </p>
                        )}
                    </div>
                  </div>
                  {fields.map((field, index) => {
                    const value = formState.data.answers[field.key];
                    const control = field.presentation?.control;
                    const listId = `suggestions-${field.key}`;
                    const description =
                      field.description ??
                      (isStudentProfileForm
                        ? (profileFieldDescriptions[field.key] ??
                          "Enter the information as it should appear in your school record.")
                        : null);
                    const placeholder = profilePlaceholder(field);
                    const inputType =
                      field.type === "number" || field.type === "year"
                        ? "number"
                        : field.type === "date"
                          ? "date"
                          : field.type === "email"
                            ? "email"
                            : field.type === "phone"
                              ? "tel"
                              : "text";
                    const isChoice = ["select", "radio", "yes_no"].includes(
                      field.type,
                    );
                    const choiceOptions: ComboboxOption[] = Object.entries(
                      field.options ?? {},
                    ).map(([optionValue, optionLabel]) => ({
                      value: optionValue,
                      label: optionLabel,
                    }));
                    return (
                      <div
                        key={field.key}
                        className="border-border/70 bg-card rounded-xl border p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6"
                      >
                        <div className="flex items-start gap-3">
                          <span className="bg-muted text-muted-foreground mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                            {fieldNumbers.get(field.key) ?? index + 1}
                          </span>
                          <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                              <label
                                className="text-sm font-semibold"
                                htmlFor={`answer-${field.key}`}
                              >
                                {field.label}
                              </label>
                              <Badge
                                variant={
                                  field.required ? "outline" : "secondary"
                                }
                              >
                                {field.required ? "Required" : "Optional"}
                              </Badge>
                            </div>
                            {description && (
                              <p className="text-muted-foreground mt-2 text-xs leading-5">
                                {description}
                              </p>
                            )}
                            <div className="mt-4">
                              {field.type === "textarea" ? (
                                <Textarea
                                  id={`answer-${field.key}`}
                                  value={String(value ?? "")}
                                  onChange={(event) =>
                                    setAnswer(field.key, event.target.value)
                                  }
                                  placeholder={placeholder}
                                  required={field.required}
                                  rows={5}
                                />
                              ) : field.type === "file" ? (
                                <label className="border-input bg-background flex h-24 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed text-sm">
                                  <FileUp
                                    className="text-muted-foreground size-5"
                                    aria-hidden="true"
                                  />
                                  <span>
                                    {value instanceof File
                                      ? value.name
                                      : "Choose a file"}
                                  </span>
                                  <Input
                                    id={`answer-${field.key}`}
                                    className="sr-only"
                                    type="file"
                                    onChange={(event) =>
                                      setAnswer(
                                        field.key,
                                        event.target.files?.[0] ?? null,
                                      )
                                    }
                                    required={field.required}
                                  />
                                </label>
                              ) : isChoice && control === "combobox" ? (
                                <Combobox
                                  options={choiceOptions}
                                  value={typeof value === "string" ? value : ""}
                                  onValueChange={(selectedValue) =>
                                    setAnswer(field.key, selectedValue)
                                  }
                                  placeholder={
                                    placeholder ?? "Choose an option"
                                  }
                                  searchPlaceholder={`Search ${field.label.toLowerCase()}…`}
                                  emptyText="No matching options."
                                  required={field.required}
                                />
                              ) : isChoice &&
                                (control === "radio_cards" ||
                                  field.type === "radio" ||
                                  field.type === "yes_no") ? (
                                <div
                                  className="grid gap-2 sm:grid-cols-2"
                                  role="radiogroup"
                                  aria-label={field.label}
                                >
                                  {Object.entries(field.options).map(
                                    ([key, label]) => (
                                      <label
                                        key={key}
                                        className={`border-border/70 hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm transition ${value === key ? "border-primary bg-primary/5" : ""}`}
                                      >
                                        <input
                                          className="accent-primary"
                                          type="radio"
                                          name={`answer-${field.key}`}
                                          value={key}
                                          checked={value === key}
                                          onChange={() =>
                                            setAnswer(field.key, key)
                                          }
                                          required={field.required}
                                        />
                                        {label}
                                      </label>
                                    ),
                                  )}
                                </div>
                              ) : isChoice ? (
                                <select
                                  id={`answer-${field.key}`}
                                  className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                  value={String(value ?? "")}
                                  onChange={(event) =>
                                    setAnswer(field.key, event.target.value)
                                  }
                                  required={field.required}
                                >
                                  <option value="">Choose an option</option>
                                  {Object.entries(field.options).map(
                                    ([key, label]) => (
                                      <option key={key} value={key}>
                                        {label}
                                      </option>
                                    ),
                                  )}
                                </select>
                              ) : control === "combobox" ? (
                                <>
                                  <Input
                                    id={`answer-${field.key}`}
                                    type={inputType}
                                    inputMode={
                                      field.presentation
                                        ?.input_mode as React.HTMLAttributes<HTMLInputElement>["inputMode"]
                                    }
                                    list={listId}
                                    value={String(value ?? "")}
                                    onChange={(event) =>
                                      setAnswer(field.key, event.target.value)
                                    }
                                    placeholder={
                                      placeholder ?? "Start typing to search"
                                    }
                                    required={field.required}
                                  />
                                  <datalist id={listId}>
                                    {(field.suggestions ?? []).map(
                                      (suggestion) => (
                                        <option
                                          key={suggestion}
                                          value={suggestion}
                                        />
                                      ),
                                    )}
                                  </datalist>
                                </>
                              ) : (
                                <div className="flex items-center gap-2">
                                  <Input
                                    id={`answer-${field.key}`}
                                    type={inputType}
                                    inputMode={
                                      field.presentation
                                        ?.input_mode as React.HTMLAttributes<HTMLInputElement>["inputMode"]
                                    }
                                    value={String(value ?? "")}
                                    onChange={(event) =>
                                      setAnswer(field.key, event.target.value)
                                    }
                                    placeholder={placeholder}
                                    required={field.required}
                                  />
                                  {field.presentation?.unit && (
                                    <span className="text-muted-foreground text-sm">
                                      {field.presentation.unit}
                                    </span>
                                  )}
                                </div>
                              )}
                              {formState.errors[`answers.${field.key}`] && (
                                <p
                                  className="text-destructive mt-2 text-xs"
                                  role="alert"
                                >
                                  {formState.errors[`answers.${field.key}`]}
                                </p>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </section>
              ))}
            </fieldset>

            {formState.errors.form && (
              <p className="text-destructive text-sm" role="alert">
                {formState.errors.form}
              </p>
            )}
            <div className="border-border/70 bg-card flex flex-col gap-4 rounded-xl border p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
              <p className="text-muted-foreground text-xs">
                <Check className="mr-1 inline size-3.5" />
                {preview
                  ? "Preview only. Responses cannot be submitted here."
                  : identityUnverified
                    ? "Saved securely for manual review."
                    : "Responses are encrypted and protected."}
              </p>
              <Button
                type="submit"
                size="lg"
                disabled={formState.processing || preview || formLocked}
              >
                {preview
                  ? "Preview only"
                  : formState.processing
                    ? "Submitting…"
                    : "Submit response"}
                {!preview &&
                  (formState.processing ? (
                    <ChevronRight className="size-4" />
                  ) : (
                    <Send className="size-4" />
                  ))}
              </Button>
            </div>
          </form>
        </div>
      </main>
    </>
  );
}
