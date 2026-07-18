<?php

namespace Tests\Feature;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use App\Models\Article;
use App\Models\EditorImage;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_user_with_upload_right_can_upload_and_attach_valid_image(): void
    {
        [$user, $web] = $this->uploadWeb();
        $response = $this->actingAs($user)->post(route('editor-images.store', $web), [
            'image' => $this->png(),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonStructure(['url', 'alt']);

        $image = EditorImage::query()->firstOrFail();
        Storage::disk('local')->assertExists($image->path);
        $this->assertNull($image->article_id);

        $this->actingAs($user)->post(route('articles.store', $web), [
            'title' => 'Mit Bild',
            'content' => '![Bild]('.$response->json('url').')',
        ])->assertRedirect();

        $article = Article::query()->firstOrFail();
        $this->assertSame($article->id, $image->fresh()->article_id);
        $this->post(route('logout'));
        $this->get(route('editor-images.show', $image))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_pending_image_is_only_visible_to_its_uploader(): void
    {
        [$user, $web] = $this->uploadWeb();
        $this->actingAs($user)->post(route('editor-images.store', $web), ['image' => $this->png()], ['Accept' => 'application/json']);
        $image = EditorImage::query()->firstOrFail();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('editor-images.show', $image))->assertForbidden();
    }

    public function test_upload_without_web_right_is_forbidden(): void
    {
        $user = User::factory()->create();
        $web = Web::query()->create([
            'slug' => 'wissen', 'title' => 'Wissen', 'visibility' => WebVisibility::Public,
        ]);

        $this->actingAs($user)->post(route('editor-images.store', $web), [
            'image' => $this->png(),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_non_image_upload_is_rejected(): void
    {
        [$user, $web] = $this->uploadWeb();

        $this->actingAs($user)->post(route('editor-images.store', $web), [
            'image' => UploadedFile::fake()->createWithContent('schadcode.php', '<?php echo 1;'),
        ])->assertRedirect()->assertSessionHasErrors('image');

        $this->assertDatabaseCount('editor_images', 0);
    }

    public function test_old_unattached_images_are_removed_by_cleanup_command(): void
    {
        [$user, $web] = $this->uploadWeb();
        $this->actingAs($user)->post(route('editor-images.store', $web), ['image' => $this->png()], ['Accept' => 'application/json']);
        $image = EditorImage::query()->firstOrFail();
        $image->timestamps = false;
        $image->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('wiki:cleanup-orphan-images')->assertSuccessful();

        $this->assertDatabaseMissing('editor_images', ['id' => $image->id]);
        Storage::disk('local')->assertMissing($image->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'editor_image.orphan_removed']);
    }

    /** @return array{User, Web} */
    private function uploadWeb(): array
    {
        $user = User::factory()->create();
        $web = Web::query()->create([
            'slug' => 'wissen', 'title' => 'Wissen', 'visibility' => WebVisibility::Public,
        ]);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::User,
            'user_id' => $user->id,
            'can_create' => true,
            'can_upload' => true,
        ]);

        return [$user, $web];
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'bild.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII='),
        );
    }
}
