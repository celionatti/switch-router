# Switch Router (`switch/router`)

> A fast, flexible PHP HTTP router with Laravel-style Facade, resource routes, regex parameter constraints, fluent group chaining, named route URL generation, and PSR-15 middleware support.

---

## 📦 Installation

```bash
composer require switch/router
```

---

## 🚀 Quick Start

```php
use Switch\Router\Facade\Route;

// Basic Routes
Route::get('/', fn() => 'Home Page');
Route::post('/users', [UserController::class, 'store']);
Route::get('/profile', 'ProfileController@show');

// Dynamic Parameters with Constraints
Route::get('/users/{id}', fn($id) => "User #{$id}")
    ->whereNumber('id');

// Named Routes & URL Generation
Route::get('/posts/{slug}', [PostController::class, 'show'])
    ->name('posts.show')
    ->whereAlpha('slug');

$url = Route::getRouter()->url('posts.show', ['slug' => 'hello-world']);
// => /posts/hello-world
```

---

## 📖 Features

### Route Registration

Register routes for any HTTP method:

```php
use Switch\Router\Facade\Route;

Route::get('/path', $handler);
Route::post('/path', $handler);
Route::put('/path', $handler);
Route::patch('/path', $handler);
Route::delete('/path', $handler);
Route::any('/path', $handler);      // Matches ALL methods
```

### Handler Formats

Switch Router supports multiple handler syntaxes:

```php
// 1. Closure
Route::get('/', fn() => 'Hello World');

// 2. Controller Array (Laravel-style)
Route::get('/home', [HomeController::class, 'index']);

// 3. Controller@Method String
Route::get('/about', 'AboutController@show');
```

All controller handlers are automatically resolved — if a PSR-11 container is available, it resolves from the container; otherwise it instantiates directly.

### Named Routes & URL Generation

```php
Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
Route::get('/posts/{slug}', [PostController::class, 'show'])->name('posts.show');

// Generate URLs by name
$router = Route::getRouter();
$router->url('users.show', ['id' => 42]);    // => /users/42
$router->url('posts.show', ['slug' => 'hi']); // => /posts/hi
```

### Parameter Constraints

```php
Route::get('/items/{id}', $handler)->whereNumber('id');           // [0-9]+
Route::get('/tags/{tag}', $handler)->whereAlpha('tag');            // [a-zA-Z]+
Route::get('/codes/{code}', $handler)->whereAlphaNumeric('code'); // [a-zA-Z0-9]+
Route::get('/orders/{uuid}', $handler)->whereUuid('uuid');        // UUID format

// Custom regex
Route::get('/posts/{slug}', $handler)->where('slug', '[a-z0-9\-]+');
Route::get('/files/{path}', $handler)->where('path', '.*');
```

### Route Groups (Array Syntax)

```php
Route::getRouter()->group([
    'prefix' => '/api/v1',
    'middleware' => ['auth', 'cors'],
    'as' => 'api.',
], function ($router) {
    $router->get('/users', [UserController::class, 'index'])->name('users.index');
    // Route name: api.users.index
    // Full path: /api/v1/users
});
```

### Fluent Group Builder (Laravel-style)

```php
Route::prefix('/admin')
    ->middleware('auth', 'admin')
    ->name('admin.')
    ->group(function ($router) {
        $router->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        $router->get('/settings', [SettingsController::class, 'index'])->name('settings');
    });

// Route names: admin.dashboard, admin.settings
// Full paths: /admin/dashboard, /admin/settings
```

### Nested Groups

```php
Route::getRouter()->group(['prefix' => '/api', 'as' => 'api.'], function ($router) {
    $router->group(['prefix' => '/v1', 'as' => 'v1.', 'middleware' => ['auth']], function ($router) {
        $router->get('/users', [UserController::class, 'index'])->name('users.index');
        // Route name: api.v1.users.index
        // Full path: /api/v1/users
    });
});
```

### Resource Routes

Automatically register all 7 RESTful routes for a controller:

```php
Route::resource('/products', ProductController::class);
```

This registers:

| Method         | URI                        | Action    | Route Name        |
|----------------|----------------------------|-----------|--------------------|
| `GET`          | `/products`                | `index`   | `products.index`   |
| `GET`          | `/products/create`         | `create`  | `products.create`  |
| `POST`         | `/products`                | `store`   | `products.store`   |
| `GET`          | `/products/{product}`      | `show`    | `products.show`    |
| `GET`          | `/products/{product}/edit` | `edit`    | `products.edit`    |
| `PUT/PATCH`    | `/products/{product}`      | `update`  | `products.update`  |
| `DELETE`       | `/products/{product}`      | `destroy` | `products.destroy` |

### Shortcut Routes

```php
// Redirect route
Route::redirect('/old-page', '/new-page', 301);

// View route (renders Switch\View\View if available)
Route::view('/about', 'pages.about', ['title' => 'About Us']);

// JSON response route
Route::json('/api/status', ['status' => 'ok', 'version' => '1.0']);

// Fallback (404 catch-all — register last)
Route::fallback(fn() => 'Page Not Found');
```

### Route Middleware

```php
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('auth', 'admin', 'log');

// Or pass an array
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified']);
```

### Current Route Introspection

```php
$router = Route::getRouter();

// After matching a request
$router->match('GET', '/dashboard');

$currentRoute = $router->current();         // Route instance or null
$routeName = $router->currentRouteName();    // 'dashboard' or null
```

### Route Facade

The `Switch\Router\Facade\Route` class provides static access to the router singleton:

```php
use Switch\Router\Facade\Route;

// Automatically creates/uses a Router singleton
Route::get('/', fn() => 'Hello');
Route::post('/submit', fn() => 'OK');

// Inject a custom Router instance
Route::setRouter($myRouter);

// Get the underlying Router
$router = Route::getRouter();
```

---

## 🔧 Using with Switch\Kernel

The router integrates automatically with `Switch\Kernel\App` via `RoutingMiddleware`:

```php
use Switch\Kernel\App;
use Switch\Router\Router;
use Switch\Router\Facade\Route;

$app = App::create();

// Register routes via Facade
Route::setRouter($app->getContainer()->get(Router::class));
Route::get('/', fn() => 'Welcome to Switch!');
Route::resource('/users', UserController::class);

$app->run();
```

---

## 📄 License
MIT License.
