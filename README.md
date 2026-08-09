# Switch Router (`switch/router`)

> A fast, flexible PHP HTTP router with regex parameter matching, group prefixes, and PSR-15 middleware support.

---

## 📦 Installation

```bash
composer require switch/router
```

---

## 🚀 Quick Start

```php
use Switch\Router\Router;
use Switch\Http\ServerRequest;

$router = new Router();

// Basic Routes
$router->get('/', fn() => 'Home Page');
$router->post('/users', [UserController::class, 'store']);

// Dynamic Parameters
$router->get('/users/{id}', fn($id) => "User ID: {$id}")
    ->where('id', '[0-9]+');

// Route Groups & Prefixes
$router->group(['prefix' => '/api/v1', 'middleware' => [AuthMiddleware::class]], function (Router $r) {
    $r->get('/products', [ProductController::class, 'index']);
});

// Matching Request
$request = ServerRequest::fromGlobals();
$match = $router->match($request);
```

---

## 📄 License
MIT License.
