<?php

namespace Tests\Feature;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use App\Models\Article;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_saved_version_is_kept_with_author_and_change_note(): void
    {
        [$user, $web, $article] = $this->createArticle();

        $this->actingAs($user)->patch(route('articles.update', [$web, $article]), [
            'title' => 'Zweite Fassung',
            'content' => "Erste Zeile\nNeue Zeile",
            'change_note' => 'Inhalt ergänzt',
        ])->assertRedirect();

        $article->refresh();
        $this->assertSame(2, $article->current_revision);
        $this->assertDatabaseHas('article_revisions', [
            'article_id' => $article->id,
            'revision_number' => 1,
            'title' => 'Erste Fassung',
            'content' => 'Erste Zeile',
        ]);
        $this->assertDatabaseHas('article_revisions', [
            'article_id' => $article->id,
            'revision_number' => 2,
            'title' => 'Zweite Fassung',
            'change_note' => 'Inhalt ergänzt',
            'author_name' => $user->name,
        ]);
    }

    public function test_visible_revision_history_detail_and_diff_can_be_opened(): void
    {
        [$user, $web, $article] = $this->createArticle();
        $this->actingAs($user)->patch(route('articles.update', [$web, $article]), [
            'title' => 'Zweite Fassung',
            'content' => "Erste Zeile\nNeue Zeile",
        ]);
        $first = $article->revisions()->where('revision_number', 1)->firstOrFail();
        $second = $article->revisions()->where('revision_number', 2)->firstOrFail();

        $this->get(route('articles.revisions.index', [$web, $article]))
            ->assertOk()
            ->assertSee('Versionsverlauf')
            ->assertSee('Revision 2');
        $this->get(route('articles.revisions.show', [$web, $article, $first]))
            ->assertOk()
            ->assertSee('Revision 1')
            ->assertSee('Erste Zeile');
        $this->get(route('articles.revisions.compare', [
            $web, $article, 'from' => $first->revision_number, 'to' => $second->revision_number,
        ]))->assertOk()
            ->assertSee('Versionsvergleich')
            ->assertSee('Neue Zeile');
    }

    public function test_restoring_old_revision_creates_a_new_revision_without_destroying_history(): void
    {
        [$user, $web, $article] = $this->createArticle();
        $this->actingAs($user)->patch(route('articles.update', [$web, $article]), [
            'title' => 'Zweite Fassung',
            'content' => 'Anderer Inhalt',
        ]);
        $first = $article->revisions()->where('revision_number', 1)->firstOrFail();

        $this->actingAs($user)->post(route('articles.revisions.restore', [$web, $article, $first]))
            ->assertRedirect();

        $article->refresh();
        $this->assertSame(3, $article->current_revision);
        $this->assertSame('Erste Fassung', $article->title);
        $this->assertSame('Erste Zeile', $article->content);
        $this->assertSame(3, $article->revisions()->count());
        $this->assertDatabaseHas('article_revisions', [
            'article_id' => $article->id,
            'revision_number' => 3,
            'change_note' => 'Revision 1 wiederhergestellt',
        ]);
    }

    public function test_user_without_edit_right_cannot_restore_revision(): void
    {
        [, $web, $article] = $this->createArticle();
        $viewer = User::factory()->create();
        $revision = $article->revisions()->firstOrFail();

        $this->actingAs($viewer)->post(route('articles.revisions.restore', [$web, $article, $revision]))
            ->assertForbidden();
        $this->assertSame(1, $article->fresh()->current_revision);
    }

    /** @return array{User, Web, Article} */
    private function createArticle(): array
    {
        $user = User::factory()->create();
        $web = Web::query()->create([
            'slug' => 'wissen',
            'title' => 'Wissen',
            'visibility' => WebVisibility::Public,
        ]);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::User,
            'user_id' => $user->id,
            'can_create' => true,
            'can_edit' => true,
        ]);
        $this->actingAs($user)->post(route('articles.store', $web), [
            'title' => 'Erste Fassung',
            'content' => 'Erste Zeile',
            'change_note' => 'Angelegt',
        ])->assertRedirect();

        return [$user, $web, Article::query()->firstOrFail()];
    }
}
