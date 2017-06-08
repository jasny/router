Jasny Router
============

[![Build Status](https://secure.travis-ci.org/jasny/router.png?branch=master)](http://travis-ci.org/jasny/router)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/router/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/router/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/router/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/router/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/28aade61-e14f-4532-bba2-5146cf3a2b67/mini.png)](https://insight.sensiolabs.com/projects/28aade61-e14f-4532-bba2-5146cf3a2b67)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/router.svg)](https://packagist.org/packages/jasny/router)
[![Packagist License](https://img.shields.io/packagist/l/jasny/router.svg)](https://packagist.org/packages/jasny/router)


Jasny Router is a versatile PSR-7 compatible router. It decouples the way to determine a route, from the routing and
from running the routed action. The router supports double pass middleware.


Installation
---

The Jasny Router package is available on [packagist](https://packagist.org/packages/jasny/router). Install it using
composer:

    composer require jasny/router

Basic Usage
---

```php
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$routes = new Routes([
    '/' => function($request, $response) {
        $response->getBody()->write('Hello world');
        return $response;
    },
]);

$router = new Router($routes);
$router->handle(new ServerRequest()->withGlobalEnvironment(), new Response());
```

Routes
---

When creating a `Router`, you need to pass a object that implements the `RoutesInterface`. Routes should be seen as a
collection of routes, with the ability to select one of those routes based on the server request.
