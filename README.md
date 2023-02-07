# php-router
A PHP Library for handling Routes using a Parent-Child Model

# Usage
## Install
```bash
composer require eru123/router
```

## Creating basic routes
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use eru123\Router\Router;

$router = new Router();
$router->base('/api');

// Get method example
$router->get('/hello', function() {
    return 'Hello World with Get!';
});

// Post method example
$router->post('/hello', function() {
    return 'Hello World! with Get!';
});

// Custom method example with PUT
$router->request('PUT', '/hello', function() {
    return 'Hello World! with Put!';
});

// Run the router
$router->run();
```

## Parent-Child Routes
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use eru123\Router\Router;

$childRouteV1 = new Router();
$childRouteV1->base('/v1');
$childRouteV1->get('/hello', function() {
    return 'Hello World! from v1';
});

$childRouteV2 = new Router();
$childRouteV2->base('/v2');
$childRouteV2->get('/hello', function() {
    return 'Hello World! from v2';
});

$router = new Router();
$router->base('/api');
$router->add($childRouteV1);
$router->add($childRouteV2);

/**
 * Registred routes:
 *  - /api/v1/hello
 *  - /api/v2/hello
 */

$router->run();
```

## Working with parameters
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use eru123\Router\Router;

$router = new Router();

$router->get('/hello/{name}', function($params) {
    return 'Hello ' . $params['name'];
});
```

## Pipeline-based handlers
 - Middleware is a function that is not the last in the pipeline
 - Handler is a function that is the last in the pipeline
 - If a middleware returns null or void, url parameters array will be passed to the next middleware or handler
 - If a middleware returns mixed value, it will be passed to the next middleware or handler as argument
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use eru123\Router\Router;

$router = new Router();

$numberMiddleware = function($params) {
    if (!is_numeric($params['a']) || !is_numeric($params['b'])) {
        throw new \Exception('a and b must be numeric');
    }
};

$convertToIntMiddleware = function($params) {
    $params['a'] = (int) $params['a'];
    $params['b'] = (int) $params['b'];
    return $params;
};

$mutiplyHandler = function ($params) {
    return $params['a'] * $params['b'];
};

$router->get('/multiply/{a}/{b}', $numberMiddleware,  $convertToIntMiddleware, $mutiplyHandler);

$router->run();
```