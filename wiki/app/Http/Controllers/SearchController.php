<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Web;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $results = null;

        if ($query !== '') {
            $request->validate(['q' => ['string', 'min:2', 'max:100']], [
                'q.min' => 'Der Suchbegriff muss mindestens zwei Zeichen lang sein.',
                'q.max' => 'Der Suchbegriff darf höchstens 100 Zeichen lang sein.',
            ]);
            $results = $this->search($request, $query);
            $results->getCollection()->each(function (Article $article) use ($query): void {
                $attachment = $article->attachments->first(fn ($item) => mb_stripos($item->original_name, $query) !== false || mb_stripos((string) $item->search_text, $query) !== false);
                $article->excerpt = $attachment
                    ? 'Passender Anhang: '.$attachment->original_name.' – '.$this->excerpt((string) $attachment->search_text, $query)
                    : $this->excerpt($article->content, $query);
            });
        }

        return view('search.index', compact('query', 'results'));
    }

    private function search(Request $request, string $query): LengthAwarePaginator
    {
        $user = $request->user();
        $user?->loadMissing('groups');
        $visibleWebIds = Web::query()->with('permissions')->get()
            ->filter(fn (Web $web) => $web->hasRight($user, 'view'))
            ->modelKeys();

        $articles = Article::query()
            ->with(['web', 'categories'])
            ->whereIn('web_id', $visibleWebIds);

        $driver = $articles->getConnection()->getDriverName();
        if ($driver === 'mysql') {
            $articles->where(function ($builder) use ($query): void {
                $builder->whereFullText(['title', 'content'], $query)
                    ->orWhereHas('attachments', fn ($attachments) => $attachments->whereFullText(['original_name', 'search_text'], $query));
            });
        } else {
            $articles->where(function ($builder) use ($query): void {
                $builder->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%")
                    ->orWhereHas('attachments', fn ($attachments) => $attachments->where('original_name', 'like', "%{$query}%")
                        ->orWhere('search_text', 'like', "%{$query}%"));
            });
        }

        $articles->with(['attachments' => function ($attachments) use ($driver, $query): void {
            if ($driver === 'mysql') {
                $attachments->whereFullText(['original_name', 'search_text'], $query);

                return;
            }

            $attachments->where(function ($builder) use ($query): void {
                $builder->where('original_name', 'like', "%{$query}%")
                    ->orWhere('search_text', 'like', "%{$query}%");
            });
        }]);

        return $articles->latest('updated_at')->paginate(30)->withQueryString();
    }

    private function excerpt(string $content, string $query): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($content)));
        $position = mb_stripos($plain, $query);
        $start = $position === false ? 0 : max(0, $position - 90);
        $excerpt = mb_substr($plain, $start, 260);

        return ($start > 0 ? '…' : '').$excerpt.(mb_strlen($plain) > $start + 260 ? '…' : '');
    }
}
