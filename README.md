# KoAkademy Online Forms Module

Reusable online forms for surveys, evaluations, student information updates, and other school workflows.

## Installation

Install it in a KoAkademy application with Composer, then rebuild the application image:

```bash
composer require koakademy/forms
php artisan migrate --force
php artisan optimize:clear
```

Enable the module from **Administrators → Marketplace**. The module is intentionally disabled for fresh installations until an administrator enables it. Existing installations keep their current module status during upgrades.

The module uses the host application's Vite/Inertia build. A production deployment must run the normal KoAkademy frontend build after Composer installs the package.

## Features

- Authenticated, guest email/student ID, or anonymous responses.
- Text, long text, email, number, date, select, radio, checkbox, yes/no, file, and rating fields.
- Response revision history, duplicate-response policy, close dates, CSV export, and protected uploads.
- Review-before-apply workflow for model mappings.
- Sensitive answer and mapping metadata captured in an audit trail.

## KoAkademy model mappings

The KoAkademy adapter exposes an allowlisted Student profile catalog. A form administrator can map answers to approved student paths such as origins, equity flags, contact details, parent/guardian information, and education information.

Mappings are never accepted directly from arbitrary request paths. Answers are stored first, matched to the authenticated user or configured guest identity, and only update a record after an administrator explicitly applies the response. By default, applying a response fills blank fields only; overwriting existing values is a separate action.

Other host applications can integrate their own records by binding `Modules\\Forms\\Contracts\\FormsModelRegistry` and `Modules\\Forms\\Contracts\\FormsTenantResolver` in a service provider.

## License

AGPL-3.0-or-later
