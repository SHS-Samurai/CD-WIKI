<nav class="border-b border-gray-100 bg-white">
    <div class="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a class="font-semibold text-gray-800" href="{{ route('home') }}">CD-Wiki</a>
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <span class="text-gray-600">{{ Auth::user()->name }}</span>
            <a class="font-medium text-sky-700" href="{{ route('profile.edit') }}">Profil</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="font-medium text-red-700" type="submit">Abmelden</button></form>
        </div>
    </div>
</nav>
