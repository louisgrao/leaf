

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-screen bg-gray-50 antialiased">
    
    <aside class="w-64 bg-gray-900 text-white flex flex-col h-full shrink-0">
        <div class="p-4 border-b border-gray-800">
            <h2 class="text-lg font-medium">Admin Panel</h2>
        </div>
        
        <nav class="flex-1 overflow-y-auto p-4 flex flex-col gap-2">
            <a href="/" wire:navigate class="hover:underline text-gray-300">Site Home</a>
            <a href="/admin" wire:navigate class="hover:underline text-gray-300">Dashboard</a>
            <a href="/admin/create" wire:navigate class="hover:underline text-gray-300">Create Page</a>
            
            <div class="mt-6 mb-2 text-xs uppercase text-gray-500 font-semibold tracking-wider">Item Groups</div>
            <a href="/admin/groups/create" wire:navigate class="hover:underline text-blue-400 mb-2">+ New Group</a>
            

            @foreach(\App\Models\ItemBase::whereNull('parent_id')->get() as $group)
                <a href="/admin/groups/{{ $group->id }}" wire:navigate class="hover:underline text-gray-300 pl-2 border-l-2 border-transparent hover:border-gray-500">
                    {{ $group->base_title }}
                </a>
            @endforeach

            
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto p-8">
        {{ $slot }}
    </main>

</body>
</html>