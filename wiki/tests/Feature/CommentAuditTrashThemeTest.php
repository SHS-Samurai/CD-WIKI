<?php

namespace Tests\Feature;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use App\Models\Article;
use App\Models\Comment;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentAuditTrashThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_follow_web_rights_are_escaped_and_admin_can_soft_delete_them(): void
    {
        [$author, $web, $article] = $this->articleWithRights(['can_comment' => true]);
        $this->actingAs($author)->post(route('comments.store', [$web, $article]), [
            'body' => '<script>alert(1)</script> Sachlicher Kommentar',
        ])->assertRedirect();

        $comment = Comment::query()->firstOrFail();
        $this->get(route('articles.show', [$web, $article]))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->delete(route('comments.destroy', [$web, $article, $comment]))->assertRedirect();
        $this->assertSoftDeleted($comment);
        $this->get(route('articles.show', [$web, $article]))->assertSee('Dieser Kommentar wurde entfernt.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'comment.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'comment.deleted']);
    }

    public function test_user_without_comment_right_is_rejected(): void
    {
        [$author, $web, $article] = $this->articleWithRights();

        $this->actingAs($author)->post(route('comments.store', [$web, $article]), ['body' => 'Nicht erlaubt'])
            ->assertForbidden();
    }

    public function test_user_with_web_delete_right_can_remove_comments(): void
    {
        [$author, $web, $article] = $this->articleWithRights(['can_comment' => true, 'can_delete' => true]);
        $this->actingAs($author)->post(route('comments.store', [$web, $article]), ['body' => 'Entfernbar']);
        $comment = Comment::query()->firstOrFail();

        $this->actingAs($author)->delete(route('comments.destroy', [$web, $article, $comment]))->assertRedirect();

        $this->assertSoftDeleted($comment);
    }

    public function test_admin_can_restore_article_from_trash_without_losing_revisions(): void
    {
        [$author, $web, $article] = $this->articleWithRights(['can_delete' => true]);
        $this->actingAs($author)->delete(route('articles.destroy', [$web, $article]))->assertRedirect();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.trash.index'))
            ->assertOk()->assertSee($article->title);
        $this->actingAs($admin)->post(route('admin.trash.articles.restore', $article->id))->assertRedirect();

        $this->assertNotNull($article->fresh());
        $this->assertSame(1, $article->fresh()->revisions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'article.restored']);
    }

    public function test_theme_is_validated_persisted_and_served_with_etag(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->patch(route('admin.theme.update'), [
            'wiki_title' => 'Firmenwissen',
            'primary_color' => '#123456',
            'background_color' => '#f0f0f0',
            'surface_color' => '#ffffff',
            'text_color' => '#111111',
            'muted_color' => '#666666',
            'font_family' => 'serif',
            'left_sidebar_enabled' => '1',
            'page_max_width' => 1400,
        ])->assertRedirect();

        $this->assertSame('Firmenwissen', ThemeSetting::query()->firstOrFail()->wiki_title);
        $response = $this->get(route('theme.css'))->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
            ->assertSee('--wiki-primary:#123456', false);
        $etag = $response->headers->get('ETag');
        $this->withHeader('If-None-Match', $etag)->get(route('theme.css'))->assertStatus(304);

        $this->actingAs($admin)->patch(route('admin.theme.update'), [
            'wiki_title' => 'Ungültig', 'primary_color' => 'red',
        ])->assertSessionHasErrors('primary_color');
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy');
    }

    /** @param array<string, bool> $extraRights @return array{User, Web, Article} */
    private function articleWithRights(array $extraRights = []): array
    {
        $user = User::factory()->create();
        $web = Web::query()->create(['slug' => 'wissen', 'title' => 'Wissen', 'visibility' => WebVisibility::Public]);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::User,
            'user_id' => $user->id,
            'can_create' => true,
            ...$extraRights,
        ]);
        $this->actingAs($user)->post(route('articles.store', $web), ['title' => 'Handbuch', 'content' => 'Inhalt']);

        return [$user, $web, Article::query()->firstOrFail()];
    }
}
