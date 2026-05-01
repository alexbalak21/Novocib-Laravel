# Novocib Laravel Implementation Guide

This document describes how the project is structured to cleanly separate the **front-office** (public website) from the **back-office** (admin panel).

The goal is:

- Public site -> static, SEO-friendly, Bootstrap-only
- Admin panel -> reactive, dynamic, Livewire-powered
- One Laravel project, cleanly divided

## Architecture Overview

The application is split into two layers:

### Front-office (public website)

- Blade templates
- Bootstrap styling
- No SPA, no JS framework
- Pages: Home, Products, Services, Contact, etc.

### Back-office (admin panel)

- Livewire components
- Admin-only routes
- CRUD interfaces, dashboards, search index, messages, product management
- Optional: Task Manager module

Both layers share the same Laravel installation, database, and authentication system.

## Directory Structure

### Front-office

```text
resources/views/
  layouts/
    app.blade.php
  pages/
    home.blade.php
    products/
    services/
    contact.blade.php

public/css/
public/js/
```

### Back-office

```text
resources/views/admin/
  layouts/
    admin.blade.php
  dashboard.blade.php
  products/
  messages/
  users/

app/Http/Livewire/Admin/
  Products/
  Messages/
  Dashboard/
```

### Controllers

```text
app/Http/Controllers/
  HomeController.php
  ProductController.php
  ContactController.php

app/Http/Controllers/Admin/
  DashboardController.php
  ProductController.php
  MessageController.php
```

### Routes

- `routes/web.php` -> public site
- `routes/admin.php` -> admin panel

## Routing Structure

### Front-office routes (`routes/web.php`)

```php
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
```

### Back-office routes (`routes/admin.php`)

```php
Route::middleware(['auth', 'is_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('products', Admin\ProductController::class);
        Route::resource('messages', Admin\MessageController::class)
            ->only(['index', 'show', 'destroy']);
    });
```

### Route registration (`app/Providers/RouteServiceProvider.php`)

```php
public function boot()
{
    $this->routes(function () {
        Route::middleware('web')
            ->group(base_path('routes/web.php'));

        Route::middleware('web')
            ->group(base_path('routes/admin.php'));
    });
}
```

## Middleware Separation

### Admin middleware (`app/Http/Middleware/IsAdmin.php`)

```php
public function handle($request, Closure $next)
{
    if (!auth()->check() || !auth()->user()->is_admin) {
        abort(403);
    }

    return $next($request);
}
```

### Registration (`app/Http/Kernel.php`)

```php
protected $routeMiddleware = [
    'is_admin' => \App\Http\Middleware\IsAdmin::class,
];
```

## Front-office Layout

`resources/views/layouts/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title') - Novocib</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
</head>
<body>

@include('components.navbar')

<main class="container py-4">
    @yield('content')
</main>

@include('components.footer')

</body>
</html>
```

## Back-office Layout

`resources/views/admin/layouts/admin.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - @yield('title')</title>

    @livewireStyles
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
</head>
<body>

@include('admin.components.sidebar')

<main class="container-fluid py-4">
    @yield('content')
</main>

@livewireScripts
</body>
</html>
```

## Livewire Integration

### Installation

```bash
composer require livewire/livewire
```

### Example Livewire component

`app/Http/Livewire/Admin/Products/ProductTable.php`

```php
class ProductTable extends Component
{
    public $search = '';

    public function render()
    {
        return view('livewire.admin.products.table', [
            'products' => Product::where('title', 'like', "%{$this->search}%")->paginate(20)
        ]);
    }
}
```

### Example view

`resources/views/livewire/admin/products/table.blade.php`

```blade
<div>
    <input type="text" wire:model="search" class="form-control mb-3" placeholder="Search products...">

    <table class="table table-striped">
        @foreach($products as $product)
            <tr>
                <td>{{ $product->title }}</td>
                <td>{{ $product->price }} €</td>
            </tr>
        @endforeach
    </table>

    {{ $products->links() }}
</div>
```

## Asset Separation

### Front-office assets

```text
public/css/front.css
public/js/front.js
```

### Back-office assets

```text
public/css/admin.css
public/js/admin.js
```

Livewire reduces the need for custom JavaScript in the admin.

## Authentication Separation (Optional)

If needed:

- `users` table -> admin accounts
- `customers` table -> secure customer portal

Laravel supports multiple guards.

## Summary

This architecture provides:

- A clean, SEO-friendly public website
- A reactive, modern admin panel
- A single Laravel codebase
- Clear separation of concerns
- Easy future expansion (Inertia, SPA, micro-apps, etc.)