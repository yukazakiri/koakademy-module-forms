# Changelog

All notable changes to `koakademy/forms` are documented here.

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
