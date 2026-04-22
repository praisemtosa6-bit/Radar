<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 antialiased text-slate-900">
    <div class="min-h-screen flex flex-col">
        <main class="flex-grow flex items-center justify-center p-6">
            @yield('content')
        </main>
    </div>
</body>
</html>
