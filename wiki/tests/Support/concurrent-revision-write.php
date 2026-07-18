<?php

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleRevisionService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$article = Article::query()->findOrFail((int) $argv[1]);
$user = User::query()->findOrFail((int) $argv[2]);
$number = (int) $argv[3];

$app->make(ArticleRevisionService::class)->update(
    $article,
    ['title' => $article->title, 'content' => "Paralleler Stand {$number}"],
    $user,
);
