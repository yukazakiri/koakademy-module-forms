# Changelog

All notable changes to `koakademy/forms` are documented here.

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
