<?php

namespace Tests\Feature;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use App\Models\Article;
use App\Models\Attachment;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_attachment_upload_download_revision_delete_and_restore(): void
    {
        [$user, $web, $article] = $this->article();
        $this->actingAs($user)->post(route('attachments.store', [$web, $article]), [
            'attachment' => UploadedFile::fake()->createWithContent('Notiz.txt', 'Erste Fassung'),
        ])->assertRedirect();
        $attachment = Attachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->get(route('attachments.download', [$web, $article, $attachment]))
            ->assertOk()->assertHeader('Content-Disposition');
        $this->get(route('search', ['q' => 'Notiz']))->assertOk()->assertSee('Passender Anhang: Notiz.txt');
        $this->get(route('search', ['q' => 'Fassung']))->assertOk()->assertSee('Passender Anhang: Notiz.txt');

        $this->actingAs($user)->post(route('attachments.store', [$web, $article]), [
            'attachment' => UploadedFile::fake()->createWithContent('Notiz.txt', 'Zweite Fassung'),
        ])->assertRedirect();
        $attachment->refresh();
        $this->assertSame(2, $attachment->current_revision);
        $this->assertSame(2, $attachment->revisions()->count());
        $firstRevision = $attachment->revisions()->where('revision_number', 1)->firstOrFail();
        $this->get(route('attachments.revisions.download', [$web, $article, $attachment, $firstRevision]))
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->actingAs($user)->delete(route('attachments.destroy', [$web, $article, $attachment]))->assertRedirect();
        $this->assertSoftDeleted($attachment);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.trash.attachments.restore', $attachment->id))->assertRedirect();
        $this->assertNotNull($attachment->fresh());
    }

    public function test_fake_pdf_and_executable_extension_are_rejected(): void
    {
        [$user, $web, $article] = $this->article();
        $this->actingAs($user)->post(route('attachments.store', [$web, $article]), [
            'attachment' => UploadedFile::fake()->createWithContent('falsch.pdf', 'kein pdf'),
        ])->assertSessionHasErrors('attachment');
        $this->actingAs($user)->post(route('attachments.store', [$web, $article]), [
            'attachment' => UploadedFile::fake()->createWithContent('schadcode.php', '<?php echo 1;'),
        ])->assertSessionHasErrors('attachment');
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_malformed_office_document_returns_validation_error_instead_of_server_error(): void
    {
        [$user, $web, $article] = $this->article();
        $malformed = "PK\x03\x04[Content_Types].xml_rels/.relsunvollständig";

        $this->actingAs($user)->post(route('attachments.store', [$web, $article]), [
            'attachment' => UploadedFile::fake()->createWithContent('defekt.docx', $malformed),
        ])->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_private_attachment_is_not_downloadable_without_view_right(): void
    {
        [$user, $web, $article] = $this->article(WebVisibility::Private);
        $this->actingAs($user)->post(route('attachments.store', [$web, $article]), [
            'attachment' => UploadedFile::fake()->createWithContent('Notiz.txt', 'Privat'),
        ]);
        $attachment = Attachment::query()->firstOrFail();
        $this->post(route('logout'));

        $this->get(route('attachments.download', [$web, $article, $attachment]))->assertForbidden();
    }

    /** @return array{User, Web, Article} */
    private function article(WebVisibility $visibility = WebVisibility::Public): array
    {
        $user = User::factory()->create();
        $web = Web::query()->create(['slug' => 'wissen', 'title' => 'Wissen', 'visibility' => $visibility]);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::User,
            'user_id' => $user->id,
            'can_view' => true,
            'can_create' => true,
            'can_upload' => true,
            'can_delete' => true,
        ]);
        $this->actingAs($user)->post(route('articles.store', $web), ['title' => 'Handbuch', 'content' => 'Inhalt']);

        return [$user, $web, Article::query()->firstOrFail()];
    }
}
