<?php

namespace Tests\Feature;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use App\Models\Category;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_title_and_content_but_never_leaks_private_webs(): void
    {
        $public = $this->web('public', WebVisibility::Public);
        $private = $this->web('private', WebVisibility::Private);
        $public->articles()->create([
            'title' => 'Laravel Handbuch', 'slug' => 'laravel', 'content' => 'Öffentliches Fachwissen',
        ]);
        $private->articles()->create([
            'title' => 'Laravel Geheimnis', 'slug' => 'geheim', 'content' => 'Vertraulich',
        ]);

        $this->get(route('search', ['q' => 'Laravel']))
            ->assertOk()
            ->assertSee('Laravel Handbuch')
            ->assertDontSee('Laravel Geheimnis');
        $this->get(route('search', ['q' => 'Fachwissen']))
            ->assertOk()
            ->assertSee('Öffentliches Fachwissen');
    }

    public function test_category_page_only_lists_articles_from_visible_webs(): void
    {
        $category = Category::query()->create(['name' => 'Technik', 'slug' => 'technik']);
        $public = $this->web('public', WebVisibility::Public);
        $private = $this->web('private', WebVisibility::Private);
        $visible = $public->articles()->create(['title' => 'Sichtbar', 'slug' => 'sichtbar', 'content' => 'Text']);
        $hidden = $private->articles()->create(['title' => 'Versteckt', 'slug' => 'versteckt', 'content' => 'Text']);
        $category->articles()->attach([$visible->id, $hidden->id]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('1 Artikel');
        $this->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee('Sichtbar')
            ->assertDontSee('Versteckt');
    }

    public function test_admin_can_create_and_edit_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Organisation',
            'slug' => 'organisation',
            'description' => 'Interne Abläufe',
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.categories.update', $category), [
            'name' => 'Prozesse',
            'slug' => 'prozesse',
            'description' => 'Abläufe',
        ])->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Prozesse', 'slug' => 'prozesse']);
    }

    public function test_categories_can_be_assigned_while_saving_an_article(): void
    {
        $user = User::factory()->create();
        $web = $this->web('wissen', WebVisibility::Public);
        $category = Category::query()->create(['name' => 'Technik', 'slug' => 'technik']);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::User,
            'user_id' => $user->id,
            'can_create' => true,
            'can_edit' => true,
        ]);

        $this->actingAs($user)->post(route('articles.store', $web), [
            'title' => 'Kategorisiert',
            'content' => 'Inhalt',
            'category_ids' => [$category->id],
        ])->assertRedirect();

        $article = $web->articles()->firstOrFail();
        $this->assertTrue($article->categories->contains($category));

        $this->actingAs($user)->patch(route('articles.update', [$web, $article]), [
            'title' => 'Ohne Kategorie',
            'content' => 'Inhalt',
            'category_ids' => [],
        ])->assertRedirect();
        $this->assertCount(0, $article->fresh()->categories);
    }

    private function web(string $slug, WebVisibility $visibility): Web
    {
        return Web::query()->create([
            'slug' => $slug,
            'title' => ucfirst($slug),
            'visibility' => $visibility,
        ]);
    }
}
