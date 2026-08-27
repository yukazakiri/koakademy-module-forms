<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

final class FormsAuthorization
{
    /** @var array<string, list<string>> */
    private const PERMISSIONS = [
        'view' => ['forms.view', 'ViewAny:Form'],
        'create' => ['forms.create', 'Create:Form'],
        'update' => ['forms.update', 'Update:Form'],
        'publish' => ['forms.publish', 'Publish:Form'],
        'responses' => ['forms.responses.view', 'ViewAny:FormResponse'],
        'export' => ['forms.responses.export', 'Export:FormResponse'],
        'apply' => ['forms.mappings.apply', 'Apply:FormResponseMapping'],
        'templates.view' => ['forms.templates.view', 'ViewAny:FormTemplate'],
        'templates.create' => ['forms.templates.create', 'Create:FormTemplate'],
        'templates.update' => ['forms.templates.update', 'Update:FormTemplate'],
        'templates.delete' => ['forms.templates.delete', 'Delete:FormTemplate'],
        'invitations.view' => ['forms.invitations.view', 'ViewAny:FormInvitation'],
        'invitations.create' => ['forms.invitations.create', 'Create:FormInvitation'],
    ];

    public function allows(mixed $user, string $ability): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if ((bool) data_get($user, 'is_super_admin') || in_array($this->role($user), ['developer', 'admin', 'super_admin'], true)) {
            return true;
        }

        $permissions = self::PERMISSIONS[$ability] ?? [];
        if ($permissions === []) {
            return false;
        }

        if (method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($permissions)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (method_exists($user, 'can') && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function role(object $user): ?string
    {
        $role = data_get($user, 'role');

        return is_object($role) ? ($role->value ?? null) : (is_string($role) ? $role : null);
    }
}
