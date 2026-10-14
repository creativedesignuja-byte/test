<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Label;
use App\Models\ProductStage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    private ProductStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->project = Project::create(['name' => 'پروژه آزمایشی', 'created_by' => $this->owner->id]);
        $this->project->members()->attach($this->owner, ['role' => 'owner']);
        $this->project->goal()->create(['title' => 'هدف', 'success_criteria' => 'نتیجه روشن', 'start_date' => '2026-10-01', 'end_date' => '2026-11-01']);
        $this->stage = $this->project->stages()->create(['title' => 'طراحی', 'position' => 0]);
        $this->actingAs($this->owner);
    }

    private function payload(array $extra = []): array
    {
        return [...['project_id' => $this->project->id, 'title' => 'طراحی هتل', 'description' => 'توضیح', 'stage_id' => $this->stage->id, 'assigned_to' => $this->owner->id, 'start_date' => '2026-10-01', 'due_date' => '2026-10-25', 'members' => [], 'labels' => [], 'subtasks' => [], 'completed' => false], ...$extra];
    }

    private function task(): Task
    {
        return $this->project->tasks()->create(collect($this->payload())->except(['members', 'labels', 'subtasks', 'completed'])->all() + ['created_by' => $this->owner->id]);
    }

    private function actor(string $role): User
    {
        $u = User::factory()->create();
        $this->project->members()->attach($u, ['role' => $role]);

        return $u;
    }

    public function test_login_and_logout(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $u = User::factory()->create(['password' => 'a-strong-test-password']);
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $u->email, 'password' => 'a-strong-test-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_registration_hashes_password(): void
    {
        $this->post('/logout');
        $this->post('/register', ['name' => 'کاربر', 'email' => 'new@example.test', 'password' => 'strong-password', 'password_confirmation' => 'strong-password'])->assertRedirect('/dashboard');
        $this->assertNotEquals('strong-password', User::where('email', 'new@example.test')->first()->password);
    }

    public function test_login_is_throttled(): void
    {
        $this->post('/logout');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'bad']);
        }$this->post('/login', ['email' => 'nobody@example.test', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_project_wizard_creates_goal_and_ordered_stages_atomically(): void
    {
        $res = $this->postJson('/api/projects', ['name' => 'رزرو هتل', 'description' => 'تجربه بهتر', 'goal' => ['title' => 'نسخه اول', 'success_criteria' => 'رزرو موفق', 'start_date' => '2026-10-01', 'end_date' => '2026-11-01'], 'stages' => [['title' => 'پژوهش'], ['title' => 'طراحی']]])->assertCreated();
        $p = Project::find($res->json('id'));
        $this->assertEquals('نسخه اول', $p->goal->title);
        $this->assertEquals([0, 1], $p->stages->pluck('position')->all());
        $this->assertEquals('owner', $p->members->first()->pivot->role);
    }

    public function test_project_cannot_be_created_without_goal(): void
    {
        $this->postJson('/api/projects', ['name' => 'بدون هدف', 'stages' => [['title' => 'طراحی']]])->assertUnprocessable()->assertJsonValidationErrors('goal');
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_goal_update_keeps_one_goal(): void
    {
        $this->putJson('/api/projects/'.$this->project->id.'/goal', ['title' => 'هدف جدید', 'success_criteria' => 'معیار', 'start_date' => '2026-10-01', 'end_date' => '2026-11-01'])->assertOk();
        $this->assertDatabaseCount('goals', 1);
        $this->assertEquals('هدف جدید', $this->project->fresh()->goal->title);
    }

    public function test_stage_create_update_order_and_delete(): void
    {
        $url = '/api/projects/'.$this->project->id.'/stages';
        $id = $this->postJson($url, ['title' => 'توسعه'])->assertCreated()->json('id');
        $this->putJson($url.'/product/'.$id, ['title' => 'پیاده‌سازی'])->assertOk();
        $this->putJson($url.'/product/order', ['ids' => [$id, $this->stage->id]])->assertOk();
        $this->assertEquals([$id, $this->stage->id], $this->project->stages()->pluck('id')->all());
        $this->deleteJson($url.'/product/'.$id)->assertNoContent();
    }

    public function test_stage_cannot_precede_goal_or_delete_tasks(): void
    {
        $this->project->goal()->delete();
        $this->postJson('/api/projects/'.$this->project->id.'/stages', ['title' => 'غیرمجاز'])->assertUnprocessable();
        $this->task();
        $this->deleteJson('/api/projects/'.$this->project->id.'/stages/product/'.$this->stage->id)->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_member_stages_have_independent_order(): void
    {
        $u = $this->actor('member');
        $url = '/api/projects/'.$this->project->id.'/stages';
        $a = $this->postJson($url, ['title' => 'دیتابیس', 'user_id' => $u->id])->assertCreated()->json('id');
        $b = $this->postJson($url, ['title' => 'API', 'user_id' => $u->id])->assertCreated()->json('id');
        $this->putJson($url.'/member/order', ['ids' => [$b, $a], 'user_id' => $u->id])->assertOk();
        $this->assertEquals([$b, $a], $this->project->memberStages()->pluck('id')->all());
        $this->assertEquals(0, $this->stage->fresh()->position);
    }

    public function test_create_task_with_subtasks_labels_and_members(): void
    {
        $member = $this->actor('member');
        $label = Label::create(['name' => 'طراحی', 'color' => '#886699', 'created_by' => $this->owner->id, 'project_id' => $this->project->id]);
        $r = $this->postJson('/api/tasks', $this->payload(['members' => [$member->id], 'labels' => [$label->id], 'subtasks' => [['title' => 'نسخه اولیه', 'completed' => true], ['title' => 'بازبینی', 'completed' => false]]]))->assertCreated();
        $t = Task::find($r->json('id'));
        $this->assertCount(2, $t->subtasks);
        $this->assertNotNull($t->subtasks[0]->completed_at);
        $this->assertEquals($label->id, $t->labels->first()->id);
        $this->assertEquals($member->id, $t->members->first()->id);
    }

    public function test_update_task_and_reorder_subtasks(): void
    {
        $t = $this->task();
        $a = $t->subtasks()->create(['title' => 'اول', 'position' => 0]);
        $b = $t->subtasks()->create(['title' => 'دوم', 'position' => 1]);
        $this->putJson('/api/tasks/'.$t->id, $this->payload(['title' => 'ویرایش شده', 'subtasks' => [['id' => $b->id, 'title' => 'دوم', 'completed' => true], ['id' => $a->id, 'title' => 'اول', 'completed' => false]]]))->assertOk();
        $this->assertEquals('ویرایش شده', $t->fresh()->title);
        $this->assertEquals([$b->id, $a->id], $t->subtasks()->pluck('id')->all());
    }

    public function test_complete_and_reopen_task(): void
    {
        $t = $this->task();
        $url = '/api/tasks/'.$t->id.'/complete';
        $this->patchJson($url, ['completed' => true])->assertOk();
        $this->assertNotNull($t->fresh()->completed_at);
        $this->patchJson($url, ['completed' => false])->assertOk();
        $this->assertNull($t->fresh()->completed_at);
    }

    public function test_comments_author_can_edit_delete_but_others_cannot(): void
    {
        $t = $this->task();
        $id = $this->postJson('/api/tasks/'.$t->id.'/comments', ['body' => 'سلام تیم'])->assertCreated()->json('id');
        $this->putJson('/api/comments/'.$id, ['body' => 'سلام دوباره'])->assertOk();
        $member = $this->actor('member');
        $this->actingAs($member)->deleteJson('/api/comments/'.$id)->assertForbidden();
        $this->actingAs($this->owner)->deleteJson('/api/comments/'.$id)->assertNoContent();
    }

    public function test_private_upload_and_download_authorization(): void
    {
        Storage::fake('local');
        $t = $this->task();
        $a = $this->postJson('/api/tasks/'.$t->id.'/attachments', ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'Safe test notes')])->assertCreated()->json('id');
        $attachment = Attachment::find($a);
        $this->assertNotEquals('notes.txt', $attachment->file_name);
        Storage::disk('local')->assertExists($attachment->path);
        $this->get('/attachments/'.$a)->assertOk()->assertDownload('notes.txt');
        $this->actingAs(User::factory()->create())->get('/attachments/'.$a)->assertNotFound();
    }

    public function test_upload_rejects_executables_and_oversized_files(): void
    {
        Storage::fake('local');
        $t = $this->task();
        $url = '/api/tasks/'.$t->id.'/attachments';
        $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;')])->assertUnprocessable();
        $this->postJson($url, ['file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])->assertUnprocessable();
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_viewer_cannot_mutate_projects_tasks_stages_labels_or_uploads(): void
    {
        $t = $this->task();
        $this->actingAs($this->actor('viewer'));
        $this->getJson('/api/tasks/'.$t->id)->assertOk();
        $this->postJson('/api/tasks', $this->payload())->assertForbidden();
        $this->patchJson('/api/tasks/'.$t->id.'/complete', ['completed' => true])->assertForbidden();
        $this->postJson('/api/projects/'.$this->project->id.'/stages', ['title' => 'مرحله'])->assertForbidden();
        $this->postJson('/api/tasks/'.$t->id.'/comments', ['body' => 'سلام'])->assertForbidden();
        $this->postJson('/api/tasks/'.$t->id.'/attachments', [])->assertForbidden();
        $this->postJson('/api/labels', ['name' => 'ممنوع', 'color' => '#123456', 'project_id' => $this->project->id])->assertForbidden();
    }

    public function test_outsider_cannot_read_or_mutate_tasks_or_see_project(): void
    {
        $t = $this->task();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/workspace')->assertOk()->assertJsonCount(0, 'projects')->assertJsonCount(0, 'tasks');
        $this->getJson('/api/tasks/'.$t->id)->assertNotFound();
        $this->putJson('/api/tasks/'.$t->id, $this->payload())->assertNotFound();
        $this->deleteJson('/api/tasks/'.$t->id)->assertNotFound();
    }

    public function test_cross_project_stage_and_nonmember_assignment_are_rejected(): void
    {
        $other = Project::create(['name' => 'دیگر', 'created_by' => $this->owner->id]);
        $s = $other->stages()->create(['title' => 'دیگر']);
        $this->postJson('/api/tasks', $this->payload(['stage_id' => $s->id]))->assertUnprocessable()->assertJsonValidationErrors('stage_id');
        $u = User::factory()->create();
        $this->postJson('/api/tasks', $this->payload(['assigned_to' => $u->id]))->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    }

    public function test_foreign_label_subtask_and_stage_order_rejected(): void
    {
        $other = $this->task();
        $sub = $other->subtasks()->create(['title' => 'خصوصی']);
        $t = $this->task();
        $this->putJson('/api/tasks/'.$t->id, $this->payload(['title' => 'نباید ذخیره شود', 'subtasks' => [['id' => $sub->id, 'title' => 'تغییر', 'completed' => false]]]))->assertNotFound();
        $this->assertEquals('طراحی هتل', $t->fresh()->title);
        $foreign = Label::create(['name' => 'شخصی', 'color' => '#123456', 'created_by' => User::factory()->create()->id]);
        $this->postJson('/api/tasks', $this->payload(['labels' => [$foreign->id]]))->assertUnprocessable();
        $this->putJson('/api/projects/'.$this->project->id.'/stages/product/order', ['ids' => [$this->stage->id, 999]])->assertUnprocessable();
    }

    public function test_date_validation_is_persian(): void
    {
        $r = $this->postJson('/api/tasks', $this->payload(['due_date' => '2026-09-01']))->assertUnprocessable()->assertJsonValidationErrors('due_date');
        $this->assertStringContainsString('تاریخ', $r->json('errors.due_date.0'));
    }

    public function test_only_owner_can_manage_members_and_goal(): void
    {
        $u = $this->actor('member');
        $this->actingAs($u)->postJson('/api/projects/'.$this->project->id.'/members', ['email' => $this->owner->email, 'role' => 'viewer'])->assertForbidden();
        $this->putJson('/api/projects/'.$this->project->id.'/goal', [])->assertForbidden();
    }

    public function test_label_lifecycle_and_assignment_cleanup(): void
    {
        $id = $this->postJson('/api/labels', ['name' => 'فوری', 'color' => '#ab2233'])->assertCreated()->json('id');
        $t = $this->task();
        $t->labels()->attach($id);
        $this->putJson('/api/labels/'.$id, ['name' => 'بازبینی', 'color' => '#bb3388'])->assertOk();
        $this->deleteJson('/api/labels/'.$id)->assertNoContent();
        $this->assertCount(0, $t->fresh()->labels);
    }

    public function test_task_assignment_creates_notification_only_for_recipient(): void
    {
        $u = $this->actor('member');
        $this->postJson('/api/tasks', $this->payload(['assigned_to' => $u->id]))->assertCreated();
        $this->getJson('/api/workspace')->assertJsonCount(0,'notices');
        $this->actingAs($u)->getJson('/api/workspace')->assertJsonCount(1,'notices');
        $this->postJson('/api/notices/read')->assertOk();
        $this->assertDatabaseMissing('notices',['user_id' => $u->id, 'read_at' => null]);
    }
}
