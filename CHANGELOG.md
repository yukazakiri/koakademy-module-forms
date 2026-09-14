# Changelog

All notable changes to `koakademy/forms` are documented here.

## 1.10.9 - 2026-09-14

- Added automatic student ID generation and age computation when creating student records from reviewed form responses.
- Handled non-numeric and duplicate student identifiers gracefully during record creation.
- Preserved non-numeric identifiers in the LRN field if available.

## 1.10.8 - 2026-09-14

- Prevented Enter key from triggering premature form submission on multi-step forms before the final page.
- Automatically unlocked unmatched guest verification forms for manual review when no student record matches.
- Relaxed optional parent, guardian, and education field requirements on student profile forms.
- Added response status updates, deletion, and reviewed-response student record creation.

## 1.10.7 - 2026-09-14

- Align the module manifest with the merged multi-step form navigation fix.

## 1.10.6 - 2026-09-14

- Align the module manifest with the merged form-navigation and response-management implementation.

## 1.10.4 - 2026-09-14

- Prevented multi-step section Continue buttons from submitting the entire form.
- Kept unmatched Student ID and email submissions available for manual review.

## 1.10.3 - 2026-09-14

- Added response CRUD and reviewed-response student record creation.

## 1.10.2 - 2026-09-14

- Fixed form submissions failing with a 500 TypeError when an authenticated user object has an integer ID.
- Cast student ID queries safely to text so non-numeric or dashed identifier lookups on PostgreSQL do not trigger invalid syntax errors.

## 1.10.0 - 2026-09-13

- Offered authenticated portal users their linked student profile on public guest Student ID and registered email forms.
- Required an explicit choice before prefilling the profile or continuing with manual record lookup.
- Added an administrator setting to disable authenticated guest profile prefill.

## 1.9.0 - 2026-09-13

- Replaced the response-card layout with a spreadsheet-style review table.
- Kept respondent, submission time, status, answers, record links, and apply actions visible by row.
- Added sticky respondent and header columns with horizontal scrolling for wide forms.

## 1.8.0 - 2026-09-12

- Added Philippine-only student profile controls based on the form's owning school country.
- Added a searchable, creatable Philippine ethnicity selector that preserves self-described values.
- Added cascading Philippine region, province, and city or municipality selectors, including directly administered localities.
- Upgraded existing Philippine student profile forms and saved templates without changing non-Philippine forms.

## 1.7.0 - 2026-09-12

- Updated the built-in Student Profile Completion form with gender choices for Male, Female, Other, and Prefer not to say.
- Added automatic migration of existing profile forms and saved templates for origin, all supported equity flags, and one shared annual parent or household income range.
- Retired parent-specific income questions from new profile forms while preserving historical mappings and responses for review and application.
- Made retired fields invisible to new public submissions and invitation targeting without deleting their stored definitions.
- Made shared income mappings explicitly annual and clear stale parent-specific income values when applied.

## 1.6.0 - 2026-09-11

- Added bulk clipboard paste for choice and dropdown fields: paste one option per line, tab-delimited spreadsheet content, or comma-separated lists.
- Trimmed blank entries, skipped duplicate keys and labels case-insensitively, preserved existing option keys, and enforced the 100-choice limit with explicit feedback.
- Normalized option key collision handling to detect case-insensitive duplicates when adding new choices.

## 1.5.0 - 2026-09-08

- Improved the form builder option editor with direct text input and an "+ Add another choice" button for choice and dropdown fields.
- Recommended and configured dropdown select controls with standard options on supported student profile template fields (civil status, nationality, region of origin, religion, disability type, emergency contact relationship, and guardian relationship).
- Added database migration to upgrade saved student profile completion forms to dropdown select fields.

## 1.4.0 - 2026-09-06

- Emitted student profile income brackets as selects from configured brackets.
- Redesigned public form section progress as stepped flow.
- Made profile contacts optional and standardized income ranges.
- Supported hiding mobile navigation on standalone forms.

## 1.3.0 - 2026-08-28

- Added smart profile field controls and section-based student form pages.

## 1.2.1 - 2026-08-28

- Hydrated built-in profile help text and placeholders in the edit payload so
  existing forms can customize the guidance shown to students.
- Revamped the form editor with a Questions/Settings workflow and shadcn
  controls for answer types, access settings, mappings, toggles, and layout.
- Added a creatable answer-choice combobox for dropdown, single-choice, and
  multiple-choice questions.
- Rendered choice questions with a searchable combobox when that presentation
  is selected.

## 1.2.0 - 2026-08-28

- Fixed the form builder save request so edited forms persist correctly.
- Added Shadcn/Sonner success and error notifications for save and publish actions.
- Redesigned Student Profile Completion responses with clearer sections, progress,
  field guidance, and student-friendly placeholders.
- Removed social-media fields from the built-in Student Profile Completion form.

## 1.1.1 - 2026-08-27

- Fixed `ArgumentCountError` when resolving the student field suggestion and
  invitation target providers by injecting their `FormsModelRegistry` and
  `FormsTenantResolver` dependencies from the container.

## 1.1.0 - 2026-08-27

- Added built-in and tenant-scoped reusable form templates.
- Added conditional missing-only Student Profile Completion forms.
- Added phone, year, radio-card, select, and searchable-combobox presentation metadata.
- Added safe normalized record-value suggestions for approved student profile fields.
- Added hashed, encrypted, one-time, 30-day record-bound email invitations with queued delivery.
- Added blank-only automatic mapping with record locking and applied/skipped audit metadata.
- Added optional host contracts for invitation targets, suggestions, and row locking.
