import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";
import { PhilippineProfileLocationField } from "./philippine-profile-location-fields";
import publicForms from "@/routes/forms";
import axios from "axios";
import { Head, useForm } from "@inertiajs/react";
import {
  AlertCircle,
  Check,
  ChevronLeft,
  ChevronRight,
  CircleHelp,
  ClipboardCheck,
  FileUp,
  IdCard,
  Loader2,
  LockKeyhole,
  Mail,
  RotateCcw,
  Send,
  ShieldCheck,
} from "lucide-react";
import { useMemo, useRef, useState, useEffect } from "react";
import { isFormFieldVisible } from "./form-visibility";

interface FormField {
  key: string;
  label: string;
  type: string;
  description: string | null;
  required: boolean;
  options: Record<string, string>;
  validation?: Record<string, string | number>;
  visibility: { field?: string; operator?: string; value?: string } | null;
  section?: string | null;
  presentation?: {
    control?: string;
    placeholder?: string;
    input_mode?: string;
    unit?: string;
    allow_custom?: boolean;
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
  profile_context?: {
    is_philippine?: boolean;
  };
  fields: FormField[];
}

interface Props {
  form: FormDefinition;
  preview?: boolean;
  invitation_token?: string;
  invitation?: { expires_at: string | null; student_name: string | null };
}

function filled(value: unknown): boolean {
  return (
    value !== undefined && value !== null && value !== "" && value !== false
  );
}

function resolvedInputMode(
  value: string | undefined,
): React.HTMLAttributes<HTMLInputElement>["inputMode"] {
  switch (value) {
    case "none":
    case "text":
    case "tel":
    case "url":
    case "email":
    case "numeric":
    case "decimal":
    case "search":
      return value;
    default:
      return undefined;
  }
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
    respondent_email: "",
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
  const [currentPage, setCurrentPage] = useState(0);
  const [pageError, setPageError] = useState<string | null>(null);
  const submissionErrorRef = useRef<HTMLDivElement>(null);
  const formLocked =
    requiresStudentVerification && !identityVerified && !identityUnverified;
  const isStudentProfileForm =
    form.settings?.template_key === "student_profile_completion";
  const usesPhilippineProfileLocationControls =
    isStudentProfileForm && form.profile_context?.is_philippine === true;
  const visibleFields = useMemo(
    () =>
      form.fields.filter((field) =>
        isFormFieldVisible(field, formState.data.answers),
      ),
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
  const activePageIndex = Math.min(
    currentPage,
    Math.max(sections.length - 1, 0),
  );
  const activePage = sections[activePageIndex];
  const activeSection = activePage?.[0] ?? "Questions";
  const activeFields = activePage?.[1] ?? [];
  const isLastPage = activePageIndex === sections.length - 1;
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

  useEffect(() => {
    setCurrentPage((current) =>
      Math.min(current, Math.max(sections.length - 1, 0)),
    );
  }, [sections.length]);

  function setAnswer(key: string, value: unknown): void {
    setPageError(null);
    formState.clearErrors(`answers.${key}`);
    formState.setData("answers", { ...formState.data.answers, [key]: value });
  }

  function handleSubmissionErrors(errors: Record<string, string>): void {
    const errorKeys = Object.keys(errors);
    const fieldError = errorKeys.find((key) => key.startsWith("answers."));

    if (fieldError) {
      const fieldKey = fieldError.slice("answers.".length);
      const sectionIndex = sections.findIndex(([, fields]) =>
        fields.some((field) => field.key === fieldKey),
      );

      if (sectionIndex >= 0) {
        setCurrentPage(sectionIndex);
      }

      const errorMessage = errors[fieldError] || "Please review the highlighted field before submitting.";
      setPageError(errorMessage);
      toast.error(errorMessage);

      requestAnimationFrame(() => {
        const fieldEl = document.getElementById(`answer-${fieldKey}`);
        if (fieldEl) {
          fieldEl.scrollIntoView({ behavior: "smooth", block: "center" });
          fieldEl.focus();
        } else {
          submissionErrorRef.current?.scrollIntoView({
            behavior: "smooth",
            block: "center",
          });
          submissionErrorRef.current?.focus();
        }
      });
      return;
    }

    if (errors.form) {
      setPageError(errors.form);
      toast.error(errors.form);
      requestAnimationFrame(() => {
        submissionErrorRef.current?.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        submissionErrorRef.current?.focus();
      });
      return;
    }

    if (errors.respondent_identifier || errors.respondent_email) {
      const idError = errors.respondent_identifier || errors.respondent_email;
      setIdentityError(idError);
      setPageError(idError);
      toast.error(idError);
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    const firstMsg = Object.values(errors)[0];
    if (firstMsg) {
      setPageError(firstMsg);
      toast.error(firstMsg);
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }

  function goToNextPage(event?: React.MouseEvent<HTMLButtonElement>): void {
    event?.preventDefault();
    event?.stopPropagation();
    const missingField = activeFields.find(
      (field) => field.required && !filled(formState.data.answers[field.key]),
    );
    if (missingField) {
      const msg = `Please answer “${missingField.label}” before continuing.`;
      setPageError(msg);
      toast.error(msg);
      return;
    }

    const invalidBirthDate = activeFields.find((field) => {
      const value = formState.data.answers[field.key];
      return (
        field.type === "date" &&
        field.validation?.before_or_equal === "today" &&
        typeof value === "string" &&
        value > new Date().toISOString().slice(0, 10)
      );
    });
    if (invalidBirthDate) {
      const msg = `“${invalidBirthDate.label}” cannot be in the future.`;
      setPageError(msg);
      toast.error(msg);
      return;
    }

    setCurrentPage((page) => Math.min(page + 1, sections.length - 1));
    setPageError(null);
    window.scrollTo({ top: 0 });
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
      const isUnmatched =
        axios.isAxiosError(error) && error.response?.status === 422;
      setIdentityVerified(false);
      setIdentityUnverified(isUnmatched && allowUnverifiedGuestResponse);
      formState.setData(
        "respondent_identity_unverified",
        isUnmatched && allowUnverifiedGuestResponse,
      );
      formState.setData("answers", {});
      const responseErrors = axios.isAxiosError<{
        errors?: Record<string, string[]>;
      }>(error)
        ? error.response?.data?.errors
        : undefined;
      setIdentityFallbackAvailable(isUnmatched);
      setIdentityError(
        isUnmatched && allowUnverifiedGuestResponse
          ? "No matching student record was found. You can still complete this form — it will be saved for manual review by school staff."
          : (responseErrors?.respondent_identifier?.[0] ??
              "We could not verify those details. Please check your Student ID and registered email."),
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
    if (preview) return;

    if (formLocked) {
      const msg = "Please verify your student record before submitting.";
      setPageError(msg);
      toast.error(msg);
      return;
    }

    for (let sIdx = 0; sIdx < sections.length; sIdx++) {
      const [, fields] = sections[sIdx];
      const missingField = fields.find(
        (field) => field.required && !filled(formState.data.answers[field.key]),
      );
      if (missingField) {
        setCurrentPage(sIdx);
        const msg = `Please answer “${missingField.label}” before submitting.`;
        setPageError(msg);
        toast.error(msg);
        requestAnimationFrame(() => {
          const el = document.getElementById(`answer-${missingField.key}`);
          if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "center" });
            el.focus();
          } else {
            window.scrollTo({ top: 0, behavior: "smooth" });
          }
        });
        return;
      }
    }

    for (let sIdx = 0; sIdx < sections.length; sIdx++) {
      const [, fields] = sections[sIdx];
      const invalidBirthDate = fields.find((field) => {
        const value = formState.data.answers[field.key];
        return (
          field.type === "date" &&
          field.validation?.before_or_equal === "today" &&
          typeof value === "string" &&
          value > new Date().toISOString().slice(0, 10)
        );
      });
      if (invalidBirthDate) {
        setCurrentPage(sIdx);
        const msg = `“${invalidBirthDate.label}” cannot be in the future.`;
        setPageError(msg);
        toast.error(msg);
        requestAnimationFrame(() => {
          const el = document.getElementById(`answer-${invalidBirthDate.key}`);
          if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "center" });
            el.focus();
          } else {
            window.scrollTo({ top: 0, behavior: "smooth" });
          }
        });
        return;
      }
    }

    if (invitation_token) {
      formState.post(
        publicForms.invitation.submit.url({
          form: form.slug,
          token: invitation_token,
        }),
        {
          forceFormData: true,
          preserveScroll: true,
          preserveState: true,
          onError: handleSubmissionErrors,
        },
      );
      return;
    }
    formState.post(publicForms.submit.url(form.slug), {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onError: handleSubmissionErrors,
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

          {/* Progress bar card (shown only when unlocked) */}
          {!formLocked && (
            <div className="border-border/70 bg-card rounded-2xl border p-4 shadow-sm sm:p-6">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <Badge variant="outline" className="text-[11px] font-medium">
                      Step {activePageIndex + 1} of {sections.length}
                    </Badge>
                    <span className="text-muted-foreground text-xs">
                      {isStudentProfileForm ? "Profile completion" : "Form progress"}
                    </span>
                  </div>
                  <h2 className="mt-1 text-lg sm:text-xl font-bold tracking-tight text-balance">
                    {activeSection}
                  </h2>
                  <p className="text-muted-foreground mt-0.5 text-xs" aria-live="polite">
                    {activeFields.filter((field) => filled(formState.data.answers[field.key])).length}{" "}
                    of {activeFields.length} in this section answered
                    {activeFields.filter((field) => field.required && !filled(formState.data.answers[field.key])).length > 0
                      ? ` · ${activeFields.filter((field) => field.required && !filled(formState.data.answers[field.key])).length} required remaining`
                      : ""}
                  </p>
                </div>
                <div className="flex items-baseline gap-2 sm:flex-col sm:items-end sm:gap-0">
                  <span className="text-primary text-2xl font-bold tabular-nums">
                    {completion}%
                  </span>
                  <p className="text-muted-foreground text-xs">
                    {completedCount} of {visibleFields.length}{" "}
                    {isStudentProfileForm ? "fields" : "questions"}
                  </p>
                </div>
              </div>

              <Progress
                value={completion}
                className="mt-3.5 h-2"
                aria-label={`Form completion ${completion}%`}
              />

              {sections.length > 1 && (
                <nav aria-label="Form sections" className="mt-4">
                  <div className="-mx-4 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
                    <ol className="flex min-w-max gap-2 sm:min-w-0 sm:flex-wrap" role="list">
                      {sections.map(([section, fields], index) => {
                        const sectionAnswered = fields.filter((field) =>
                          filled(formState.data.answers[field.key]),
                        ).length;
                        const completed = index < activePageIndex;
                        const current = index === activePageIndex;
                        const upcoming = index > activePageIndex;
                        const stepClass = completed
                          ? "border-primary/40 bg-primary/10 text-primary hover:bg-primary/15"
                          : current
                            ? "border-primary bg-background text-foreground shadow-sm ring-1 ring-primary/30"
                            : "border-border/60 bg-muted/30 text-muted-foreground opacity-60";

                        return (
                          <li key={section} className="shrink-0">
                            <button
                              type="button"
                              className={`flex min-h-10 items-center gap-2 rounded-xl border px-3 py-1.5 text-left text-xs sm:text-sm font-medium transition-all active:scale-[0.97] ${stepClass}`}
                              aria-current={current ? "step" : undefined}
                              onClick={() => {
                                if (!upcoming) {
                                  setCurrentPage(index);
                                  setPageError(null);
                                }
                              }}
                              disabled={upcoming}
                            >
                              <span className="bg-card flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold shadow-xs">
                                {completed ? (
                                  <Check className="size-3.5 text-primary" aria-hidden="true" />
                                ) : (
                                  index + 1
                                )}
                              </span>
                              <span className="truncate">{section}</span>
                              <span className="text-muted-foreground text-[11px] font-normal">
                                ({sectionAnswered}/{fields.length})
                              </span>
                            </button>
                          </li>
                        );
                      })}
                    </ol>
                  </div>
                </nav>
              )}
            </div>
          )}

          <form
            onSubmit={submit}
            onKeyDown={(event) => {
              if (
                event.key === "Enter" &&
                !isLastPage &&
                (event.target as HTMLElement).tagName !== "TEXTAREA"
              ) {
                event.preventDefault();
                goToNextPage();
              }
            }}
            className="flex flex-col gap-5"
          >
            {(formState.errors.form || pageError) && (
              <div
                ref={submissionErrorRef}
                className="border-destructive/30 bg-destructive/5 text-destructive rounded-xl border p-4 text-sm font-medium shadow-sm"
                role="alert"
                aria-live="assertive"
                tabIndex={-1}
              >
                {formState.errors.form || pageError}
              </div>
            )}

            {/* Verification State Banner (Shown when verified) */}
            {requiresStudentVerification && identityVerified && (
              <div className="border-emerald-500/30 bg-emerald-500/10 text-emerald-950 dark:text-emerald-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-2xl border p-3.5 shadow-sm sm:px-5 sm:py-3.5">
                <div className="flex items-center gap-3 min-w-0">
                  <span className="bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex size-8 shrink-0 items-center justify-center rounded-full">
                    <ShieldCheck className="size-4" aria-hidden="true" />
                  </span>
                  <div className="min-w-0">
                    <p className="text-sm font-semibold truncate">Student record verified</p>
                    <p className="text-muted-foreground text-xs truncate">
                      {formState.data.respondent_identifier} · {formState.data.respondent_email}
                    </p>
                  </div>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-8 w-full sm:w-auto text-xs active:scale-[0.96] transition-transform border-emerald-500/30 hover:bg-emerald-500/10"
                  onClick={() => {
                    setIdentityVerified(false);
                    formState.setData("answers", {});
                  }}
                >
                  <RotateCcw className="mr-1.5 size-3" />
                  Use another record
                </Button>
              </div>
            )}

            {/* Manual Review Banner */}
            {requiresStudentVerification && identityUnverified && (
              <div className="border-amber-500/30 bg-amber-500/10 text-amber-950 dark:text-amber-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-2xl border p-3.5 shadow-sm sm:px-5 sm:py-3.5">
                <div className="flex items-center gap-3 min-w-0">
                  <span className="bg-amber-500/20 text-amber-600 dark:text-amber-400 flex size-8 shrink-0 items-center justify-center rounded-full">
                    <AlertCircle className="size-4" aria-hidden="true" />
                  </span>
                  <div className="min-w-0">
                    <p className="text-sm font-semibold truncate">Manual review mode</p>
                    <p className="text-muted-foreground text-xs truncate">
                      Responses will be saved for manual staff verification.
                    </p>
                  </div>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-8 w-full sm:w-auto text-xs active:scale-[0.96] transition-transform border-amber-500/30 hover:bg-amber-500/10"
                  onClick={() => {
                    setIdentityUnverified(false);
                    formState.setData("respondent_identity_unverified", false);
                    formState.setData("answers", {});
                  }}
                >
                  <RotateCcw className="mr-1.5 size-3" />
                  Try finding record again
                </Button>
              </div>
            )}

            {/* STEP 0: Verify Student Record (Shown ONLY before verification) */}
            {formLocked ? (
              <section className="border-border/70 bg-card relative overflow-hidden rounded-2xl border p-5 shadow-sm sm:p-8">
                <div className="flex flex-col gap-5 sm:gap-6">
                  <div className="flex items-start gap-3.5">
                    <div className="bg-primary/10 text-primary flex size-11 sm:size-12 shrink-0 items-center justify-center rounded-xl">
                      <IdCard className="size-5 sm:size-6" aria-hidden="true" />
                    </div>
                    <div className="min-w-0">
                      <Badge variant="outline" className="mb-1.5 text-xs">
                        Step 1 · Identity Verification
                      </Badge>
                      <h2 className="text-xl font-bold tracking-tight sm:text-2xl text-balance">
                        First, verify your student record
                      </h2>
                      <p className="text-muted-foreground mt-1 text-xs sm:text-sm leading-relaxed text-pretty">
                        Enter both details exactly as registered with the school. Matching records will automatically prefill your available profile fields.
                      </p>
                    </div>
                  </div>

                  <div className="grid gap-4 sm:gap-5">
                    <div className="space-y-1.5">
                      <label
                        className="text-sm font-medium flex items-center gap-2"
                        htmlFor="respondent-student-id"
                      >
                        <IdCard className="size-4 text-muted-foreground" />
                        Student ID <span className="text-destructive">*</span>
                      </label>
                      <Input
                        id="respondent-student-id"
                        className="h-11 text-base sm:text-sm"
                        value={formState.data.respondent_identifier}
                        onChange={(event) =>
                          setIdentity(
                            "respondent_identifier",
                            event.target.value,
                          )
                        }
                        placeholder="e.g. 2026-000123"
                        required
                        autoFocus
                      />
                      <p className="text-muted-foreground text-[11px] sm:text-xs">
                        Use the ID shown on your student card or registration record.
                      </p>
                    </div>

                    <div className="space-y-1.5">
                      <label
                        className="text-sm font-medium flex items-center gap-2"
                        htmlFor="respondent-email"
                      >
                        <Mail className="size-4 text-muted-foreground" />
                        Registered email <span className="text-destructive">*</span>
                      </label>
                      <Input
                        id="respondent-email"
                        type="email"
                        className="h-11 text-base sm:text-sm"
                        value={formState.data.respondent_email}
                        onChange={(event) =>
                          setIdentity("respondent_email", event.target.value)
                        }
                        placeholder="name@example.com"
                        required
                      />
                      <p className="text-muted-foreground text-[11px] sm:text-xs">
                        Use the email address currently saved in your student record.
                      </p>
                    </div>

                    {identityError && (
                      <div className="border-destructive/30 bg-destructive/5 text-destructive flex items-start gap-2.5 rounded-xl border p-3.5 text-xs sm:text-sm" role="alert">
                        <AlertCircle className="size-4 shrink-0 mt-0.5" />
                        <div>{identityError}</div>
                      </div>
                    )}

                    {identityError &&
                      identityFallbackAvailable &&
                      allowUnverifiedGuestResponse &&
                      !identityUnverified && (
                        <div className="border-amber-500/30 bg-amber-500/5 rounded-xl border p-4">
                          <p className="text-sm font-semibold text-amber-800 dark:text-amber-300">
                            Could not find a matching student record
                          </p>
                          <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                            You can still complete this form. It will be saved for school staff to review manually and will not update a student record automatically.
                          </p>
                          <Button
                            type="button"
                            variant="outline"
                            className="mt-3 w-full sm:w-auto h-10 text-xs active:scale-[0.96] transition-transform"
                            onClick={continueWithoutLookup}
                          >
                            Continue for manual review
                          </Button>
                        </div>
                      )}

                    <Button
                      type="button"
                      size="lg"
                      className="h-12 w-full text-base font-semibold active:scale-[0.96] transition-transform mt-1"
                      onClick={verifyStudent}
                      disabled={
                        identityLoading ||
                        !formState.data.respondent_identifier.trim() ||
                        !formState.data.respondent_email.trim()
                      }
                    >
                      {identityLoading ? (
                        <>
                          <Loader2 className="mr-2 size-5 animate-spin" />
                          Verifying student record…
                        </>
                      ) : (
                        <>
                          <ShieldCheck className="mr-2 size-5" />
                          Find my record
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              </section>
            ) : form.access_mode === "guest_identifier" && form.identity_type !== "student_id" ? (
              <section className="border-border/70 bg-card rounded-2xl border p-5 shadow-sm sm:p-6">
                <label
                  className="flex flex-col gap-2 text-sm font-medium"
                  htmlFor="respondent-identity"
                >
                  Email address
                  <Input
                    id="respondent-identity"
                    type="email"
                    className="h-11 text-base sm:text-sm"
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
              </section>
            ) : null}

            {/* Questions Section & Navigation (Shown only when unlocked) */}
            {!formLocked && (
              <>
                <fieldset className="contents">
                  <section key={activeSection} className="flex flex-col gap-4">
                    <div className="flex items-start gap-3 px-1">
                      <span className="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                        {activePageIndex + 1}
                      </span>
                      <div>
                        <p className="text-primary text-xs font-semibold tracking-[0.12em] uppercase">
                          Page {activePageIndex + 1} of {sections.length}
                        </p>
                        <h2 className="mt-0.5 text-xl font-bold tracking-tight text-balance">
                          {activeSection}
                        </h2>
                        {isStudentProfileForm &&
                          profileSectionDescriptions[activeSection] && (
                            <p className="text-muted-foreground mt-1 max-w-2xl text-xs sm:text-sm leading-relaxed text-pretty">
                              {profileSectionDescriptions[activeSection]}
                            </p>
                          )}
                      </div>
                    </div>

                    {activeFields.map((field, index) => {
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
                      const isPhilippineProfileLocationField =
                        usesPhilippineProfileLocationControls &&
                        [
                          "ethnicity",
                          "region_of_origin",
                          "province_of_origin",
                          "city_of_origin",
                        ].includes(field.key);
                      return (
                        <div
                          key={field.key}
                          className="border-border/70 bg-card rounded-2xl border p-4 shadow-sm transition-shadow hover:shadow-md sm:p-6"
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
                                  variant={field.required ? "outline" : "secondary"}
                                  className="text-[11px]"
                                >
                                  {field.required ? "Required" : "Optional"}
                                </Badge>
                              </div>
                              {description && (
                                <p className="text-muted-foreground mt-1.5 text-xs leading-relaxed text-pretty">
                                  {description}
                                </p>
                              )}
                              <div className="mt-3.5">
                                {isPhilippineProfileLocationField ? (
                                  <PhilippineProfileLocationField
                                    field={field}
                                    answers={formState.data.answers}
                                    onAnswersChange={(updates) => {
                                      setPageError(null);
                                      formState.setData("answers", {
                                        ...formState.data.answers,
                                        ...updates,
                                      });
                                    }}
                                  />
                                ) : field.type === "textarea" ? (
                                  <Textarea
                                    id={`answer-${field.key}`}
                                    className="text-base sm:text-sm"
                                    value={String(value ?? "")}
                                    onChange={(event) =>
                                      setAnswer(field.key, event.target.value)
                                    }
                                    placeholder={placeholder}
                                    required={field.required}
                                    rows={4}
                                  />
                                ) : field.type === "file" ? (
                                  <label className="border-input bg-background flex min-h-[96px] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-sm active:scale-[0.99] transition-transform">
                                    <FileUp
                                      className="text-muted-foreground size-5"
                                      aria-hidden="true"
                                    />
                                    <span className="text-xs font-medium">
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
                                    placeholder={placeholder ?? "Choose an option"}
                                    searchPlaceholder={`Search ${field.label.toLowerCase()}…`}
                                    emptyText="No matching options."
                                    allowCreate={
                                      field.presentation?.allow_custom === true
                                    }
                                    createLabel="Use"
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
                                          className={`border-border/70 hover:bg-muted/40 flex min-h-[44px] cursor-pointer items-center gap-3 rounded-xl border p-3.5 text-sm transition active:scale-[0.98] ${value === key ? "border-primary bg-primary/5 ring-1 ring-primary/30" : ""}`}
                                        >
                                          <input
                                            className="accent-primary size-4"
                                            type="radio"
                                            name={`answer-${field.key}`}
                                            value={key}
                                            checked={value === key}
                                            onChange={() =>
                                              setAnswer(field.key, key)
                                            }
                                            required={field.required}
                                          />
                                          <span className="font-medium text-xs sm:text-sm">{label}</span>
                                        </label>
                                      ),
                                    )}
                                  </div>
                                ) : isChoice ? (
                                  <select
                                    id={`answer-${field.key}`}
                                    className="border-input bg-background h-11 sm:h-10 w-full rounded-md border px-3 text-base sm:text-sm"
                                    value={String(value ?? "")}
                                    onChange={(event) =>
                                      setAnswer(field.key, event.target.value)
                                    }
                                    required={field.required}
                                  >
                                    <option value="">
                                      {placeholder ?? "Choose an option"}
                                    </option>
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
                                      className="h-11 sm:h-10 text-base sm:text-sm"
                                      inputMode={resolvedInputMode(
                                        field.presentation?.input_mode,
                                      )}
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
                                      className="h-11 sm:h-10 text-base sm:text-sm flex-1"
                                      inputMode={resolvedInputMode(
                                        field.presentation?.input_mode,
                                      )}
                                      value={String(value ?? "")}
                                      onChange={(event) =>
                                        setAnswer(field.key, event.target.value)
                                      }
                                      placeholder={placeholder}
                                      min={
                                        field.type === "number" ||
                                        field.type === "year"
                                          ? field.validation?.min
                                          : undefined
                                      }
                                      max={
                                        field.type === "date" &&
                                        field.validation?.before_or_equal ===
                                          "today"
                                          ? new Date().toISOString().slice(0, 10)
                                          : field.type === "number" ||
                                              field.type === "year"
                                            ? field.validation?.max
                                            : undefined
                                      }
                                      required={field.required}
                                    />
                                    {field.presentation?.unit && (
                                      <span className="text-muted-foreground text-sm font-medium">
                                        {field.presentation.unit}
                                      </span>
                                    )}
                                  </div>
                                )}
                                {formState.errors[`answers.${field.key}`] && (
                                  <p
                                    className="text-destructive mt-2 text-xs font-medium flex items-center gap-1.5"
                                    role="alert"
                                  >
                                    <AlertCircle className="size-3.5 shrink-0" />
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
                </fieldset>

                {/* Bottom Navigation */}
                <div className="border-border/70 bg-card flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 rounded-2xl border p-4 sm:p-5 shadow-sm">
                  <p className="text-muted-foreground text-xs text-center sm:text-left">
                    <Check className="mr-1 inline size-3.5 text-emerald-600" />
                    {preview
                      ? "Preview only. Responses cannot be submitted here."
                      : identityUnverified
                        ? "Saved securely for manual review."
                        : "Responses are encrypted and protected."}
                  </p>
                  <div className="flex items-center gap-3">
                    {activePageIndex > 0 && (
                      <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        className="h-11 sm:h-10 flex-1 sm:flex-initial active:scale-[0.96] transition-transform"
                        onClick={() => {
                          setCurrentPage((page) => Math.max(page - 1, 0));
                          setPageError(null);
                        }}
                        disabled={formState.processing}
                      >
                        <ChevronLeft className="size-4 mr-1" /> Back
                      </Button>
                    )}
                    {isLastPage ? (
                      <Button
                        type="submit"
                        size="lg"
                        className="h-11 sm:h-10 flex-1 sm:flex-initial text-base sm:text-sm font-semibold active:scale-[0.96] transition-transform shadow-sm"
                        disabled={formState.processing || preview}
                      >
                        {preview
                          ? "Preview only"
                          : formState.processing
                            ? "Submitting…"
                            : "Submit response"}
                        {!preview &&
                          (formState.processing ? (
                            <Loader2 className="size-4 animate-spin ml-1" />
                          ) : (
                            <Send className="size-4 ml-1" />
                          ))}
                      </Button>
                    ) : (
                      <Button
                        type="button"
                        size="lg"
                        className="h-11 sm:h-10 flex-1 sm:flex-initial text-base sm:text-sm font-semibold active:scale-[0.96] transition-transform"
                        onClick={(event) => {
                          event.preventDefault();
                          event.stopPropagation();
                          goToNextPage(event);
                        }}
                        disabled={preview}
                      >
                        Continue
                        <ChevronRight className="size-4 ml-1" />
                      </Button>
                    )}
                  </div>
                </div>
              </>
            )}
          </form>
        </div>
      </main>
    </>
  );
}
