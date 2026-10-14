<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Notice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    private function visibleLabels(Request $r)
    {
        $ids = $r->user()->projects()->pluck('projects.id');

        return Label::where(fn ($q) => $q->whereIn('project_id', $ids)->orWhere(fn ($q) => $q->whereNull('project_id')->where('created_by', $r->user()->id)));
    }

    public function data(Request $r)
    {
        $projects = $r->user()->projects()->with(['goal', 'stages', 'memberStages', 'members'])->withCount(['tasks', 'tasks as completed_count' => fn ($q) => $q->whereNotNull('completed_at')])->get();
        $tasks = Task::whereIn('project_id', $projects->pluck('id'))->with(['subtasks', 'labels', 'members', 'assignee'])->latest()->get();

        return ['user' => $r->user(), 'projects' => $projects, 'tasks' => $tasks, 'labels' => $this->visibleLabels($r)->get(), 'notices' => Notice::where('user_id', $r->user()->id)->latest()->limit(30)->get()];
    }

    public function project(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:2000', 'goal' => 'required|array:title,description,success_criteria,start_date,end_date', 'goal.title' => 'required|string|max:200', 'goal.description' => 'nullable|string|max:3000', 'goal.success_criteria' => 'required|string|max:3000', 'goal.start_date' => 'required|date_format:Y-m-d', 'goal.end_date' => 'required|date_format:Y-m-d|after_or_equal:goal.start_date', 'stages' => 'required|array|min:1|max:30', 'stages.*' => 'array:title,description,start_date,end_date', 'stages.*.title' => 'required|string|max:120', 'stages.*.description' => 'nullable|string|max:2000', 'stages.*.start_date' => 'nullable|date_format:Y-m-d', 'stages.*.end_date' => 'nullable|date_format:Y-m-d|after_or_equal:stages.*.start_date']);
        $p = DB::transaction(function () use ($v, $r) {
            $p = Project::create(['name' => $v['name'], 'description' => $v['description'] ?? null, 'created_by' => $r->user()->id]);
            $p->members()->attach($r->user()->id, ['role' => 'owner']);
            $p->goal()->create($v['goal']);
            foreach ($v['stages'] as $i => $s) {
                $p->stages()->create([...$s, 'position' => $i]);
            }

return $p;
        });

        return response()->json($p, 201);
    }

    public function goal(Request $r, Project $project)
    {
        ProjectAccess::check($project, 'owner');
        $v = $r->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:3000', 'success_criteria' => 'required|string|max:3000', 'start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date']);

        return $project->goal()->updateOrCreate(['project_id' => $project->id], $v);
    }

    public function stage(Request $r, Project $project)
    {
        ProjectAccess::check($project, 'write');
        abort_unless($project->goal, 422, 'ابتدا هدف پروژه را مشخص کنید.');
        $v = $r->validate(['title' => 'required|string|max:120', 'description' => 'nullable|string|max:2000', 'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date', 'user_id' => ['nullable', Rule::exists('project_members', 'user_id')->where('project_id', $project->id)]]);
        $rel = isset($v['user_id']) ? $project->memberStages() : $project->stages();
        $v['position'] = ($rel->max('position') ?? -1) + 1;

        return response()->json($rel->create($v), 201);
    }

    public function updateStage(Request $r, Project $project, string $kind, int $id)
    {
        ProjectAccess::check($project, 'write');
        $stage = ($kind === 'member' ? $project->memberStages() : $project->stages())->findOrFail($id);
        if ($r->isMethod('delete')) {
            if ($kind !== 'member' && $stage->tasks()->exists()) {
                throw ValidationException::withMessages(['stage' => 'قبل از حذف، تسک‌های این مرحله را منتقل کنید.']);
            }$stage->delete();

            return response()->noContent();
        }
        $stage->update($r->validate(['title' => 'required|string|max:120', 'description' => 'nullable|string|max:2000', 'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date']));

        return $stage;
    }

    public function order(Request $r, Project $project, string $kind)
    {
        ProjectAccess::check($project, 'write');
        $v = $r->validate(['ids' => 'required|array|min:1', 'ids.*' => 'required|integer|distinct', 'user_id' => 'nullable|integer']);
        $rel = $kind === 'member' ? $project->memberStages()->where('user_id', $v['user_id'] ?? 0) : $project->stages();
        $actual = $rel->pluck('id')->sort()->values()->all();
        $ids = $v['ids'];
        sort($ids);
        abort_unless($actual === $ids, 422, 'فهرست مراحل معتبر نیست.');
        DB::transaction(function () use ($v, $rel) {
            foreach ($v['ids'] as $i => $id) {
                (clone $rel)->where('id', $id)->update(['position' => $i]);
            }
        });

        return ['ok' => true];
    }

    public function member(Request $r, Project $project)
    {
        ProjectAccess::check($project, 'owner');
        $v = $r->validate(['email' => 'required|email|exists:users,email', 'role' => 'required|in:member,viewer']);
        $user = User::where('email', $v['email'])->firstOrFail();
        abort_if($user->id === $project->created_by, 422, 'نقش مالک قابل تغییر نیست.');
        $project->members()->syncWithoutDetaching([$user->id => ['role' => $v['role']]]);

        return ['ok' => true];
    }

    public function taskDetail(Task $task)
    {
        ProjectAccess::check($task->project);

        return $task->load(['subtasks', 'labels', 'members', 'comments.user', 'attachments.user']);
    }

    public function saveTask(Request $r, ?Task $task = null)
    {
        $project = $task?->project ?? Project::findOrFail($r->input('project_id'));
        ProjectAccess::check($project, 'write');
        $v = $r->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:10000', 'stage_id' => ['required', 'integer', Rule::exists('product_stages', 'id')->where('project_id', $project->id)], 'member_stage_id' => ['nullable', 'integer', Rule::exists('member_stages', 'id')->where('project_id', $project->id)], 'assigned_to' => ['nullable', 'integer', Rule::exists('project_members', 'user_id')->where('project_id', $project->id)], 'start_date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:start_date', 'completed' => 'boolean', 'members' => 'present|array|max:100', 'members.*' => ['integer', 'distinct', Rule::exists('project_members', 'user_id')->where('project_id', $project->id)], 'labels' => 'present|array|max:30', 'labels.*' => 'integer|distinct', 'subtasks' => 'present|array|max:100', 'subtasks.*.id' => 'nullable|integer|distinct', 'subtasks.*.title' => 'required|string|max:200', 'subtasks.*.completed' => 'required|boolean']);
        $validLabels = $this->visibleLabels($r)->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $project->id))->pluck('id');
        if (collect($v['labels'])->diff($validLabels)->isNotEmpty()) {
            throw ValidationException::withMessages(['labels' => 'برچسب انتخاب‌شده متعلق به این پروژه نیست.']);
        }
        $new = ! $task;
        $oldAssignee = $task?->assigned_to;
        $task = DB::transaction(function () use ($v, $project, $task, $r) {
            $fields = collect($v)->except(['members', 'labels', 'subtasks', 'completed'])->all();
            $fields['completed_at'] = ($v['completed'] ?? false) ? ($task?->completed_at ?? now()) : null;
            if ($task) {
                $task->update($fields);
            } else {
                $task = $project->tasks()->create([...$fields, 'created_by' => $r->user()->id]);
            }
            $task->members()->sync($v['members']);
            $task->labels()->sync($v['labels']);
            $keep = [];
            foreach ($v['subtasks'] as $i => $s) {
                $fields = ['title' => $s['title'], 'position' => $i, 'completed_at' => $s['completed'] ? now() : null];
                if (! empty($s['id'])) {
                    $sub = $task->subtasks()->findOrFail($s['id']);
                    $sub->update($fields);
                } else {
                    $sub = $task->subtasks()->create($fields);
                }$keep[] = $sub->id;
            }$task->subtasks()->whereNotIn('id', $keep)->delete();

            return $task;
        });
        if ($task->assigned_to && $task->assigned_to !== $r->user()->id) {
            Notice::create(['user_id' => $task->assigned_to, 'task_id' => $task->id, 'body' => ($new || $oldAssignee !== $task->assigned_to ? 'تسک جدید برای شما: ' : 'تسک به‌روزرسانی شد: ').$task->title]);
        }

        return response()->json($task->load(['subtasks', 'labels', 'members']), $new ? 201 : 200);
    }

    public function complete(Request $r, Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        $v = $r->validate(['completed' => 'required|boolean']);
        $task->update(['completed_at' => $v['completed'] ? now() : null]);

        return $task;
    }

    public function removeTask(Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        foreach ($task->attachments as $a) {
            Storage::disk('local')->delete($a->path);
        }$task->delete();

        return response()->noContent();
    }

    public function label(Request $r, ?Label $label = null)
    {
        if ($label) {
            if ($label->project_id) {
                ProjectAccess::check($label->project, 'write');
            } else {
                abort_unless($label->created_by === $r->user()->id, 404);
            }
        }
        if ($r->isMethod('delete')) {
            $label->delete();

            return response()->noContent();
        }
        $v = $r->validate(['name' => 'required|string|max:60', 'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/', 'description' => 'nullable|string|max:500', 'project_id' => 'nullable|integer']);
        if (! $label && isset($v['project_id'])) {
            ProjectAccess::check(Project::findOrFail($v['project_id']), 'write');
        }
        if ($label) {
            unset($v['project_id']);
            $label->update($v);
        } else {
            $label = Label::create([...$v, 'created_by' => $r->user()->id]);
        }

return $label;
    }

    public function comment(Request $r, Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        $v = $r->validate(['body' => 'required|string|max:5000']);
        $c = $task->comments()->create([...$v, 'user_id' => $r->user()->id]);
        if ($task->assigned_to && $task->assigned_to !== $r->user()->id) {
            Notice::create(['user_id' => $task->assigned_to, 'task_id' => $task->id, 'body' => 'نظر جدید در «'.$task->title.'»']);
        }

return response()->json($c->load('user'), 201);
    }

    public function editComment(Request $r, Comment $comment)
    {
        ProjectAccess::check($comment->task->project, 'write');
        abort_unless($comment->user_id === $r->user()->id, 403);
        if ($r->isMethod('delete')) {
            $comment->delete();

            return response()->noContent();
        }$comment->update($r->validate(['body' => 'required|string|max:5000']));

        return $comment;
    }

    public function upload(Request $r, Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        $r->validate(['file' => 'required|file|max:5120|mimes:pdf,png,jpg,jpeg,webp,txt|extensions:pdf,png,jpg,jpeg,webp,txt']);
        $f = $r->file('file');
        $path = $f->store('attachments', 'local');

        return response()->json($task->attachments()->create(['user_id' => $r->user()->id, 'original_name' => mb_substr(basename($f->getClientOriginalName()), 0, 200), 'file_name' => basename($path), 'mime_type' => $f->getMimeType(), 'size' => $f->getSize(), 'path' => $path])->load('user'), 201);
    }

    public function download(Attachment $attachment)
    {
        ProjectAccess::check($attachment->task->project);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function removeAttachment(Request $r, Attachment $attachment)
    {
        $role = ProjectAccess::check($attachment->task->project, 'write');
        abort_unless($attachment->user_id === $r->user()->id || $role === 'owner', 403);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }

    public function notices(Request $r)
    {
        Notice::where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return ['ok' => true];
    }

    public function settings(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', Rule::unique('users')->ignore($r->user()->id)]]);
        $r->user()->update($v);

        return $r->user();
    }
}
