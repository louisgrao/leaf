<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="p-4">
    <nav class="flex gap-4 border-b pb-2 mb-4">
        <a href="/" wire:navigate class="underline">Site Home</a>
        <a href="/admin" wire:navigate class="underline">Admin Home</a>
        <a href="/admin/create" wire:navigate class="underline">Create Page</a>
    </nav>

    <main>
        {{ $slot }}
    </main>
</body>
</html>