<?php

namespace Tests\Feature;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use App\Models\Article;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_edit_and_soft_delete_article(): void
    {
        $user = User::factory()->create();
        $web = $this->editableWeb();

        $this->actingAs($user)->post(route('articles.store', $web), [
            'title' => 'Erster Artikel',
            'content' => '# Inhalt',
        ])->assertRedirect();

        $article = Article::query()->firstOrFail();
        $this->assertSame('erster-artikel', $article->slug);

        $this->actingAs($user)->patch(route('articles.update', [$web, $article]), [
            'title' => 'Geänderter Artikel',
            'content' => 'Neuer Inhalt',
        ])->assertRedirect(route('articles.show', [$web, $article]));

        $this->actingAs($user)->delete(route('articles.destroy', [$web, $article]))
            ->assertRedirect(route('webs.show', $web));
        $this->assertSoftDeleted($article);
    }

    public function test_user_without_web_right_cannot_create_article(): void
    {
        $user = User::factory()->create();
        $web = Web::query()->create([
            'slug' => 'intern',
            'title' => 'Intern',
            'visibility' => WebVisibility::Private,
        ]);

        $this->actingAs($user)->post(route('articles.store', $web), [
            'title' => 'Verboten',
            'content' => 'Inhalt',
        ])->assertForbidden();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_article_binding_is_scoped_to_its_web(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $firstWeb = $this->editableWeb('eins');
        $secondWeb = $this->editableWeb('zwei');
        $article = $firstWeb->articles()->create([
            'title' => 'Artikel', 'slug' => 'artikel', 'content' => 'Inhalt', 'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get(route('articles.show', [$secondWeb, $article]))->assertNotFound();
    }

    public function test_article_pages_and_editor_are_rendered(): void
    {
        $user = User::factory()->create();
        $web = $this->editableWeb();
        $article = $web->articles()->create([
            'title' => 'Sicherer Inhalt',
            'slug' => 'sicherer-inhalt',
            'content' => '**Text**',
            'user_id' => $user->id,
        ]);

        $this->get(route('articles.show', [$web, $article]))
            ->assertOk()
            ->assertSee('<strong>Text</strong>', false);
        $this->actingAs($user)->get(route('articles.create', $web))
            ->assertOk()
            ->assertSee('data-wiki-editor', false)
            ->assertSee('Wiki-Link');
    }

    public function test_editor_escaped_wiki_links_are_normalized_before_storage(): void
    {
        $user = User::factory()->create();
        $web = $this->editableWeb();

        $this->actingAs($user)->post(route('articles.store', $web), [
            'title' => 'Wiki-Link',
            'content' => 'Siehe \\[\\[Fehlender Artikel\\]\\].',
        ])->assertRedirect();

        $article = Article::query()->firstOrFail();
        $this->assertSame('Siehe [[Fehlender Artikel]].', $article->content);
        $this->get(route('articles.show', [$web, $article]))
            ->assertOk()
            ->assertSee('wiki-link-missing', false);
    }

    private function editableWeb(string $slug = 'wissen'): Web
    {
        $web = Web::query()->create([
            'slug' => $slug,
            'title' => ucfirst($slug),
            'visibility' => WebVisibility::Public,
        ]);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::Authenticated,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => true,
        ]);

        return $web;
    }
}
