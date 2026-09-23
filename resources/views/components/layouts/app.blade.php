<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="p-4">
    <nav class="flex justify-between border-b pb-2 mb-4">
        <div class="flex gap-4">
            <a href="/" wire:navigate class="underline">Home</a>
            
            @foreach(\App\Models\Page::all() as $page)
                <a href="/{{ $page->slug }}" wire:navigate class="underline">
                    {{ $page->title }}
                </a>
            @endforeach
        </div>
        
        <div>
            <a href="/admin" wire:navigate class="underline text-blue-600">Admin</a>
        </div>
    </nav>

    <main>
        {{ $slot }}
    </main>
</body>
</html>