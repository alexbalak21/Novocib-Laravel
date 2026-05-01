<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'My App')</title>
    @vite(['resources/css/bootstrap.min.css', 'resources/css/app.css', 'resources/js/bootstrap.bundle.min.js', 'resources/js/app.js'])


    @stack('css')
</head>
<body>

    {{-- Navbar --}}
    @include('components.navbar')

    <main class="container py-4">
        @yield('content')
    </main>

    {{-- Footer --}}
    @include('components.footer')

    @stack('js')
</body>
</html>
