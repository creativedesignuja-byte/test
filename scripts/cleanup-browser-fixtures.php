<?php
// Remove only accounts created by this repository's browser smoke test.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Browser fixture cleanup is local-only.');
}
foreach (App\Models\User::where('email', 'like', 'smoke-%@browser.test')->where('name', 'کاربر آزمون مرورگر')->get() as $user) {
    foreach (App\Models\Project::where('created_by', $user->id)->get() as $project) {
        foreach ($project->tasks()->with('attachments')->get() as $task) {
            foreach ($task->attachments as $attachment) {
                Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->path);
            }
            $task->delete();
        }
        $project->delete();
    }
    $user->delete();
}
echo "Browser fixtures cleaned.\n";
