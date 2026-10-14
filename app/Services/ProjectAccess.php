<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;

class ProjectAccess
{
    public static function check(Project $project, string $action = 'view'): string
    {
        $role = $project->members()->where('users.id', Auth::id())->first()?->pivot->role;
        abort_unless($role, 404);
        if ($action === 'write') {
            abort_if($role === 'viewer', 403, 'دسترسی شما فقط مشاهده است.');
        }
        if ($action === 'owner') {
            abort_unless($role === 'owner', 403, 'این عملیات فقط برای مالک پروژه مجاز است.');
        }

        return $role;
    }
}
