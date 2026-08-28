# KoAkademy Online Forms Module

Reusable online forms for surveys, evaluations, student information updates,
and other school workflows.

## Requirements

- KoAkademy 1.22 or newer
- PHP 8.5
- Laravel 13, Filament 5, and nwidart/laravel-modules 13
- A host image with Composer-installed vendor module asset support

The package is distributed through the public [KoAkademy Composer
repository](https://yukazakiri.github.io/koakademy-modules). The Marketplace
catalog does not install PHP code into a running container; Composer and the
application image do that.

## Installation

Run the following commands from the host application's repository root:

    composer config repositories.koakademy composer https://yukazakiri.github.io/koakademy-modules
    composer require koakademy/forms:^1.1
    php artisan migrate --force
    php artisan optimize:clear

If the host already has the koakademy Composer repository configured, only the
composer require command is needed. Commit composer.json and composer.lock.
Installing Composer files in one running replica is not a complete deployment.

The host's normal frontend build must include Composer module pages:

    composer install --no-dev --prefer-dist --optimize-autoloader
    npm ci
    npm run build

Use the build and release hooks supplied by the host platform. Run the
migration once, clear the application cache, and restart or roll every
application worker.

## Features

- Authenticated, guest email, verified Student ID + email, or anonymous responses.
- Optional unmatched Student ID + email submissions stored for manual review without automatic record updates.
- Text, long text, email, phone, number, year, date, select, radio, checkbox, yes/no, file, and rating fields.
- Response revision history, duplicate-response policy, close dates, CSV export, and protected uploads.
- Review-before-apply workflow for model mappings.
- Reusable built-in and tenant-scoped form templates with cloning and editing.
- Secure, one-time, 30-day record-bound email invitations for profile completion.
- Searchable safe suggestions for common profile values; suggestions never expose student identities.
- Sensitive answer, invitation, blank-only application, and skipped-field metadata captured in an audit trail.

## Student Profile Completion

The built-in **Student Profile Completion** template is generated from the host
application's approved student profile catalog. It renders only fields that are
still blank for the invited student and groups them into short sections. A
submission is bound to its invitation on the server, stored encrypted, and
applies only values that are still blank while the target record is locked.

Existing normalized student and relation columns are used when available.
Sparse or evolving fields are stored under stable keys in the host's
`students.profile_details` JSON column, so registrar workbook exports and
future analytics can read them without changing the version-1 workbook layout.

Administrators must explicitly open **Online Forms → Invitations** and queue a
batch. Deploying the package never sends email. Delivery is queued through the
host mailer; resending revokes the previous link, and completed or expired
links cannot be reused.

### Docker Compose

Build the host application image after the package has been added to
composer.json and composer.lock. Run the migration as a one-off task for the
application service, then recreate the service with the new image:

    docker compose build <app-service>
    docker compose run --rm <app-service> php artisan migrate --force
    docker compose up -d <app-service>

Replace app-service with the service name in the host application's Compose
file.

### Docker Swarm or Dokploy

Build and push the host application image, then redeploy the Swarm service with
that immutable tag or digest. Run php artisan migrate --force through the
release hook or a one-off container before or during the rollout. Confirm that
all replicas use the same image and that the persistent module status file is
available to every worker.

Dokploy is a deployment interface around this same image workflow: commit the
lockfile, trigger a new image build, run the release migration, and redeploy.
A Marketplace refresh by itself does not rebuild the image.

### Kubernetes or another platform

Add the package to the host image build, run the migration as a release Job,
and roll the application Deployment. The exact service, Job, and secret names
are platform-specific; the required order is Composer install, frontend
build, migration, cache clear, and rollout.

### Enable the module

1. Sign in as a super administrator.
2. Open **Administrators → System → Marketplace**, or visit
   /administrators/module-marketplace.
3. Enable **Forms**.
4. Restart/redeploy the application so every worker reads the new module
   status.

Fresh installations initialize optional modules disabled. Upgrades preserve
the existing modules_statuses.json choices, so an already-enabled Forms
module stays enabled.

## Where Forms appears

Forms is a custom Inertia administrator workspace, not a Filament resource.
After the package is installed, the module is enabled, and the host frontend
has been rebuilt, it appears as **Online Forms** in the administrator sidebar
under **Students**.

The direct administrator URL is:

    /administrators/forms

If the sidebar entry is not visible, open that URL directly and verify that
the current host image includes vendor module page discovery. Also check that
the signed-in account is a super administrator or has the Forms view
permission. A hard refresh may be needed after a new frontend image is
deployed.

## Create a form

1. Open Online Forms and choose **Create form**.
2. Set a title, optional public slug, description, closing date, and
   confirmation message.
3. Choose who can respond:
   - **Authenticated users** links the response to the signed-in account.
   - **Guests with email or ID** asks for the configured email address, or
     verifies both Student ID and registered email before resolving a student.
   - **Anyone anonymously** records no identity.
4. Add questions. Supported types are text, long text, email, phone, number,
   year, date, select, radio, checkbox, yes/no, file, and rating.
5. Give each question a stable key such as guardian_name or origin. Mark
   required and sensitive questions deliberately.
6. Save the draft, review the publishing checklist, and choose **Publish**.

The public form is available at:

    /forms/<public-slug>

Share the full HTTPS URL from the deployed application, not only the slug.
Closed forms cannot accept new responses.

## Link answers to KoAkademy records

When the KoAkademy adapter is available, the builder shows **Student** as an
allowed model and presents approved writable fields. To create a student
information update form:

1. Add questions such as Origin, Special Equity, Guardian Name, Parent Name,
   and Contact Number.
2. Mark personal or sensitive questions as sensitive.
3. In each question's **Record mapping**, choose **Student**.
4. Choose the matching approved field path shown by the builder. Do not type
   arbitrary model paths.
5. Publish the form and share it with the intended respondents.

Authenticated responses are linked to the user's student record. Guest
responses use the configured email or verified Student ID + registered email
to resolve a student. Student-ID forms prefill only the form's approved Student
mappings after verification; the same verification is repeated when the response
is submitted. Do not use Student ID alone for forms that expose personal data.
The built-in Student Profile Completion template allows an unmatched Student ID
and registered email pair to continue for manual review. These responses are encrypted and
kept unmatched, do not update a student record automatically, and are marked
**manual review** in the response workspace. Administrators must verify the
submitted identity before applying anything; unmatched responses cannot be
applied directly. Anonymous responses remain unlinked unless a host integration
supplies an approved identity workflow. Answers are stored first; they never
update a student record merely because a mapping was configured.

## Review and apply responses

1. Return to Online Forms and select **View responses**.
2. Inspect the respondent, revisions, answers, sensitive markers, and record
   link status.
3. For a **manual review** response, verify the Student ID and email against
   school records outside the form before updating the student manually.
4. Choose **Apply blank fields only** to avoid overwriting existing values on
   a response with a verified link.
5. Use **Apply and overwrite** only when the submitted answer has been
   verified and the operator has permission to overwrite data.
6. Use **Export CSV** when a controlled offline review is required, and
   protect the exported file like the original records.

Every mapping application and export is audited. Configure retention,
permissions, HTTPS, backups, and private upload storage according to the
institution's privacy policy.

## Host application integrations

Other host applications can bind these contracts in a service provider:

    Modules\Forms\Contracts\FormsModelRegistry
    Modules\Forms\Contracts\FormsTenantResolver

The model registry must expose an allowlisted model and writable field catalog,
resolve records by authenticated user or approved identifier, and implement
read/write persistence. The tenant resolver must return the current tenant
key. Optional hosts may also bind `FormsInvitationTargetProvider`,
`FormsFieldSuggestionProvider`, and `FormsLockableModelRegistry`; otherwise the
module falls back to no invitations, no suggestions, and review-only mappings.
Do not accept model names or write paths directly from public requests.

## Troubleshooting

### Marketplace says the core edge version does not satisfy >=1.22.0

The Forms manifest intentionally requires KoAkademy 1.22 or newer. Deploy a
current 1.22 edge image containing edge-version compatibility handling, or use
a stable KoAkademy 1.22 release. Do not weaken the Forms requirement to make
an older core appear compatible.

### Forms is installed but no sidebar item appears

Confirm all of the following:

    composer show koakademy/forms
    php artisan route:list | grep administrators.forms

- Forms is enabled in Marketplace.
- The status file contains Forms set to true.
- The image was rebuilt after composer.json and composer.lock changed.
- npm run build ran after the package was installed.
- The host core includes vendor module pages in its Vite and Inertia loaders.
- The current user can view Forms.

The direct URL remains /administrators/forms. If that URL returns a missing
route, the provider was not loaded; inspect the image's Composer autoload and
Laravel package discovery output.

## Upgrading

After releasing a newer module tag, update the package in the host repository,
rebuild the image, run migrations, clear caches, and redeploy. Refreshing
Marketplace alone only refreshes catalog metadata.

## License

AGPL-3.0-or-later
