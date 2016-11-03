Jasny Router
============

[![Build Status](https://secure.travis-ci.org/jasny/router.png?branch=master)](http://travis-ci.org/jasny/router)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/router/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/router/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/router/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/router/?branch=master)

Versatile router for PSR-7 http messages.


Installation
---

The Jasny Router package is available on [packagist](https://packagist.org/packages/jasny/router). Install it using
composer:

    composer require jasny/router


Examples
---

### Glob routes

Use a glob-like expression to match routes. In this example we assume you're using
[Jasny Controller](http://www.github.com/jasny/controller) to create controllers with actions.

```php
use Jasny\Router;
use Jasny\Routes;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$routes = new Routes\Glob([
    '/' => ['controller' => 'default', 'action' => 'home'],
    '/**' => ['controller' => '$1', 'action' => '$2|index', 'id' => '$3']
]);

$router = new Router($routes);

$request = (new ServerRequest())->withGlobalEnvironment();
$response = $router->handle($request, new Response());

$response->emit();
```

### Micro routes

In this example we'll simulate routing in [Slim v3](http://www.slimframework.com/) using the micro routes.

A middleware function is used to inject the arguments grabbed from the URL as request attributes.

```php
use Jasny\Router;
use Jasny\Routes;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$routes = new Routes\Micro();

$routes->get('/hello/{name}', function(ServerRequest $request, Response $response) {
    $name = $request->getAttribute('name');
    $response->getBody()->write("Hello, $name");

    return $response;
});

$router = new Router($routes);

$router->add(function(ServerRequest $request, Response $response, $next) {
  foreach ($route as $key => $value) {
    $request = $request->withAttribute($key, $value);
  }

  return $next($request, $response);
});

$request = (new ServerRequest())->withGlobalEnvironment();
$response = $router->handle($request, new Response());

$response->emit();
```

**Note:** In all the examples we're using the [Jasny HttpMessage](https://github.com/jasny/http-message) library as
PSR-7 implementation, but any implemention (like [Zend Diactoros](https://github.com/zendframework/zend-diactoros)) will
work with Jasny Router.


### Glob routes for REST api

When using glob routes, we can also specify which methods should match.

```
use Jasny\Router;
use Jasny\Routes;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$routes = new Routes\Glob([
    '/' => ['controller' => 'default', 'action' => 'info'],

    '/clubs +GET' => ['controller' => 'club', 'action' => 'list'],
    '/clubs +POST' => ['controller' => 'club', 'action' => 'add'],
    '/clubs/* +GET' => ['controller' => 'club', 'action' => 'get', 'id' => '$2'],
    '/clubs/* +POST' => ['controller' => 'club', 'action' => 'update', 'id' => '$2'],
    '/clubs/* +DELETE' => ['controller' => 'club', 'action' => 'delete', 'id' => '$2'],

    '/djs +GET' => ['controller' => 'dj', 'action' => 'list'],
    '/djs +POST' => ['controller' => 'dj', 'action' => 'add'],
    '/djs/* +GET' => ['controller' => 'dj', 'action' => 'get', 'id' => '$2'],
    '/djs/* +POST' => ['controller' => 'dj', 'action' => 'update', 'id' => '$2'],
    '/djs/* +DELETE' => ['controller' => 'dj', 'action' => 'delete', 'id' => '$2'],

    '/djs/*/bookings +GET' => ['controller' => 'booking', 'action' => 'list', 'dj-id' => '$2'],
    '/djs/*/bookings +POST' => ['controller' => 'booking', 'action' => 'add', 'dj-id' => '$2'],
    '/djs/*/bookings/* +DELETE' => ['controller' => 'booking', 'action' => 'cancel', 'dj-id' => '$2', 'booking-id' => '$4'],
]);

$router = new Router($routes);

$request = (new ServerRequest())->withGlobalEnvironment();
$response = $router->handle($request, new Response());

$response->emit();
```

### Grouping

You can create combine routes create route groups. Each router is setup with it's own set of rules and middleware.

The main router can also have middleware, which is executed prior to the group middleware.

```php

$siteRouter = new Router(new Router\Glob([
    '/**' => ['controller' => '$1|default', 
]);

$adminRouter = new Router(new Routes\Micro());
$adminRoutes->add(new Middleware\Basepath('/admin'));
$adminRoutes->getRoutes()
    ->get('/', function ($request, $response) { /*...*/ })
    ->get('/foo', function ($request, $response) { /*...*/ })
    ->post('/foo', function ($request, $response) { /*...*/ });

$apiRouter = new Router(new Routes\Glob([
    '/* +GET' => ['action' => 'list'],
    '/* +POST' => ['action' => 'add'],
    '/*/* +GET' => ['action' => 'get', 'id' => '$2'],
    '/*/* +POST' => ['action' => 'update', 'id' => '$2'],
    '/*/* +DELETE' => ['action' => 'delete', 'id' => '$2']
]));
$apiRouter->getRoutes()->setDefaults(['controller' => '$1', 'controller-group' => 'api']);
$adminRoutes->add(new Middleware\Basepath('/api'));

$routeGroups = new Routes\Glob([
    '/api/**' => $apiRouter,
    '/admin/**' => $adminRouter,
    '/**' => $siteRouter;
]);

$router

```
Routes
---

A `Routes` object that holds all the available routes. It has a `getRoute()` method to get the route for a server
request (a PSR-7 `ServerRequestInterface`).

The `hasRoute()` method simply checks if there is a route for the request and returns a boolean.

How routes are added is determined by the specific `Routes` implementation: `Routes\Glob` and `Routes\Micro`.

### Glob

The `Routes\Glob` constructor takes an associative array of routes. The keys of the array will be used to match the
request URL path. The value part will be used to create a [`Route`](#route) object.

When matching the URL, you can specify wildcards:

| Wildcard  | Description                     |
| --------- | ------------------------------- |
| ?         | Single character                |
| #         | One or more digits              |
| *         | One or more characters          |
| **        | Any number of subdirs           |
| [abc]     | Match character 'a', 'b' or 'c' |
| [a-z]     | Match character 'a' to 'z'      |
| {png,gif} | Match 'png' or 'gif'            |

After the url you may specify which request methods to include of exclude. For example `/foo +POST +PUT` will match a
request with url path `/foo` and method `POST` or `PUT`. Key `/bar -DELETE` will match a request with url path `/bar`
and any method except `DELETE`.




**Tip:** The Glob routes works expecially nice when defining the routes in Yaml configuration file.

```yaml
--- routes.yml
/ +GET: { controller: default, action: home },
/*/**:  { controller: $1, action: $2|index, id: $3 }
```

```php
$routes = new Routes\Glob(yaml_parse_file('routes.yml'));
```
