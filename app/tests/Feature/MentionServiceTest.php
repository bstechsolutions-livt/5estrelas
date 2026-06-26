<?php

namespace Tests\Feature;

use App\Models\CommentMention;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\Permission;
use App\Models\User;
use App\Services\MentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private MentionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MentionService();
    }

    private function makePayable(): Payable
    {
        return Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 1000,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
        ]);
    }

    // ─── extractMentions ─────────────────────────────────────────────────

    public function test_extract_mentions_with_id_prefix(): void
    {
        $ids = $this->service->extractMentions('Olá @[João Silva](id:42) tudo bem?');
        $this->assertEquals([42], $ids);
    }

    public function test_extract_mentions_without_id_prefix(): void
    {
        $ids = $this->service->extractMentions('Olá @[Maria](55) tudo?');
        $this->assertEquals([55], $ids);
    }

    public function test_extract_multiple_mentions(): void
    {
        $ids = $this->service->extractMentions('@[A](id:1) e @[B](id:2) e @[C](id:3)');
        $this->assertEquals([1, 2, 3], $ids);
    }

    public function test_extract_no_mentions(): void
    {
        $ids = $this->service->extractMentions('Texto normal sem menção');
        $this->assertEquals([], $ids);
    }

    public function test_extract_deduplicates(): void
    {
        $ids = $this->service->extractMentions('@[A](id:5) e @[A](id:5) repetido');
        $this->assertEquals([5], $ids);
    }

    // ─── processComment ──────────────────────────────────────────────────

    public function test_process_comment_creates_mention_and_notification(): void
    {
        $payable = $this->makePayable();
        $sender = User::factory()->create(['is_active' => true]);
        $mentioned = User::factory()->create(['is_active' => true]);

        $comment = PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $sender->id,
            'body' => "Verificar com @[{$mentioned->name}](id:{$mentioned->id}) por favor",
            'type' => 'comment',
        ]);

        $this->service->processComment($comment);

        $this->assertDatabaseHas('comment_mentions', [
            'payable_comment_id' => $comment->id,
            'mentioned_user_id' => $mentioned->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $mentioned->id,
            'type' => 'mention',
        ]);
    }

    public function test_process_comment_does_not_self_mention(): void
    {
        $payable = $this->makePayable();
        $sender = User::factory()->create(['is_active' => true]);

        $comment = PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $sender->id,
            'body' => "Eu me menciono @[{$sender->name}](id:{$sender->id})",
            'type' => 'comment',
        ]);

        $this->service->processComment($comment);

        $this->assertDatabaseMissing('comment_mentions', [
            'mentioned_user_id' => $sender->id,
        ]);
    }

    public function test_process_comment_idempotent(): void
    {
        $payable = $this->makePayable();
        $sender = User::factory()->create(['is_active' => true]);
        $mentioned = User::factory()->create(['is_active' => true]);

        $comment = PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $sender->id,
            'body' => "@[{$mentioned->name}](id:{$mentioned->id})",
            'type' => 'comment',
        ]);

        $this->service->processComment($comment);
        $this->service->processComment($comment); // segunda vez

        $this->assertEquals(1, CommentMention::where('payable_comment_id', $comment->id)->count());
    }

    // ─── mentionableUsers ────────────────────────────────────────────────

    public function test_mentionable_users_with_wildcard_returns_all_active(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->permissions()->attach(
            Permission::firstOrCreate(['key' => '*'], ['label' => '*', 'module' => 'system'])->id
        );
        $other1 = User::factory()->create(['is_active' => true]);
        $other2 = User::factory()->create(['is_active' => false]); // inativo
        $payable = $this->makePayable();

        $users = $this->service->mentionableUsers($admin, $payable->id);

        $ids = collect($users)->pluck('id');
        $this->assertTrue($ids->contains($other1->id));
        $this->assertFalse($ids->contains($other2->id)); // inativo excluído
        $this->assertFalse($ids->contains($admin->id)); // não inclui a si mesmo
    }

    public function test_mentionable_users_same_department(): void
    {
        $dept = Department::create(['name' => 'TI', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true, 'department_id' => $dept->id]);
        $colleague = User::factory()->create(['is_active' => true, 'department_id' => $dept->id]);
        $outsider = User::factory()->create(['is_active' => true, 'department_id' => null]);
        $payable = $this->makePayable();

        $users = $this->service->mentionableUsers($user, $payable->id);
        $ids = collect($users)->pluck('id');

        $this->assertTrue($ids->contains($colleague->id));
        // outsider pode não aparecer (sem dept em comum, sem participar do processo)
    }
}
