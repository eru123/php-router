# php-router

[![Build Status](https://api.travis-ci.com/eru123/php-router.svg?branch=main)](https://app.travis-ci.com/github/eru123/php-router)
[![Latest Stable Version](https://poser.pugx.org/eru123/router/v/stable)](https://packagist.org/packages/eru123/router)
[![Total Downloads](https://poser.pugx.org/eru123/router/downloads)](https://packagist.org/packages/eru123/router)
[![License](https://poser.pugx.org/eru123/router/license)](https://packagist.org/packages/eru123/router)

PHP Library for handling HTTP requests.

For latest documentation, please visit [Docs](https://eru123.github.io/php-router)

## Supported Features
 - [x] `URL Parameters` - Allowed dynamic parameters in the url path (ex: `/user/$id`)
 - [x] `Static Routes` - Serve static files from a directory (even forbidden directories) with directory traversal protection
 - [x] `Fallback Routes` - Fallback route for handling requests that doesn't match any other route but match the prefix url
 - [x] `Debugging` - Debug mode for debugging routes and route state
 - [x] `Route State` - Route state is passed to all route handlers and response handlers
 - [x] `Router Bootstrapper` - Allows you to run pre-handlers and allows you to manipulate the route state before the router starts running handlers
 - [x] `Route Handlers` - Route handlers are called when a route matches the request
 - [x] `Response Handlers` - Response handlers are called after the last route handler is called
 - [x] `Error Handlers` - Error handlers are called when a route handler creates a Throwable
 - [x] `Parent-Child Routes` - You can create parent-child routes for grouping routes

# Basic Usage
## Install
```bash
composer require eru123/router
```

## Creating basic routes
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use eru123\router\Router;
use eru123\router\Builtin;

// Create Router instance
$r = new Router;

// Enable Debug Mode.
// When debug mode is on, we add is_debug and debug() methods to the route state
// $state->is_debug - true if debug mode is on
// $state->debug - array of debugging information (ex: routes, route, errors, etc.)
$r->debug();

// Base Path
// This is used as the prefix for all routes
$r->base('/api');

// Set a response handler using a callable function
// Response handlers are called after the last route
// handler is called, it uses the return value of the 
// last route handler as the first argument and then
// the route state as the second argument
$r->response(function($result, $state) {
    if (is_array($res) || is_object($res)) {

        header('Content-Type: application/json');
        if ($state->is_debug && is_array($res)) {
            $result['debug'] = $state->debug;
        }
        
        print json_encode($result);
        exit;
    }

    if (is_string($result) && strpos($result, '<?xml') === 0) {
        header('Content-Type: application/xml');
        print $result;
        exit;
    }

    print $result;
    exit;
});

// Set an error handler using a callable function
// Error handlers are called when a route handler creates a Throwable
$r->error(function($error, $state) {
    $result = [
        'error' => $error->getMessage(),
        'code' => $error->getCode(),
    ];

    if ($state->is_debug) {
        $result['debug'] = $state->debug;
    }

    header('Content-Type: application/json');
    print json_encode($result);
    exit;
});

// Default response and error handlers
$r->response([Builtin::class, 'response']);
$r->error([Builtin::class, 'error']);

// Create Route Request
// The first argument is the HTTP method
// The second argument is the path
// The third and so on arguments are route handlers
$r->request('GET', '/', function($state) {
    return 'Hello World!';
});

// Request Aliases
// We implement most used and common HTTP methods as aliases
$r->get($path, $handler);
$r->post($path, $handler);
$r->put($path, $handler);

// URL Parameters
// You can use URL parameters in the path with $<name> syntax.
// Parameter name must start with alpha and the following 
// characters can be alpanumeric or underscore.
$r->get('/user/$id', function($state) {
    return 'User ID: ' . $state->params['id'];
});

// Fallback Route
// This route is called when no other route matches
// all requests with /pages as the base path will 
// be handled by this route if no other route matches
$r->fallback('/pages', function($state) {
    return 'Page not found';
});

// OR Global Fallback Route
// This route is called when no other route matches
// all requests will be handled by this route if no other route matches.
// It uses a prefix url, and process the fallback route only if the URL
// matches the prefix url.
// Example:
//      $r->fallback('/pages', $handler)
//      Process all requests starting with /pages (ex: /pages/1, /pages/user/1, etc.) 
$r->fallback('/', function($state) {
    return 'Page not found';
});

// Static Routes
// Static routes are routes that are intended to be used for static files.
// It can serve files from a forbidden directory that can't be accessed by
// the client. You can inject a middleware to it for checking authentication
// or etc.
// It uses a prefix url, and process the static route only if the URL
// matches the prefix url.
// For the second argument, you need to pass a directory path where the 
// request file needs to be looked up.
// Example:
//      $r->static('/css', __DIR__ . '/../src/assets/css', $handler)
//      Process all requests starting with /css (ex: /css/style.css, /css/style.min.css, etc.) 
$r->static('/static', __DIR__ . '/static', function($state) {
    // Check if user is authenticated
    if (!$state->user) {
        throw new \Exception('Not authenticated', 401); // this will be handled by the error handler

        // You can also return a response here if you dont want to use the error handler
        header('Content-Type: application/json');
        print json_encode([
            'error' => 'Not authenticated',
            'code' => 401,
        ]);

        exit;
    }

    // If the handler doesn't return anything, the file will be served accordingly
});

```
# Advanced Usage
In this section, we will cover some advanced usage and in-depth details about the usage of the library.

## Router paths matching order
For advanced usage, you need to understand how the router matches the request path to the route path. The router matches the request path to the route path in the following order:
 - `Router::static('/', $directory[, ...$handlers])` - Routes defined using the static method are matched first.
 - `Router::request($method, $path, ...$handlers)`, `Router::get($path, ...$handlers)`, `Router::post($path, ...$handlers)` - Routes defined using the request method and it's aliases are matched next.
 - `Router::fallback($path, ...$handlers)` - Routes defined using the fallback method are matched last.

Regarding to order of matching inside these groups, the router matches the request path to the route path in the first come first serve order. 
 
For example, if you have the following routes:
```php
// First come first serve order

// this will be matched first, this will also match /user/me
$r->get('/user/$x', $handler1); 

// this will be matched second, but it's handlers will not be 
// called unless the $handler1's state called skip() method
// Example: $state->skip();
$r->get('/user/me', $handler2); 

// NOTE: These kind of URL path designs are NOT RECOMMENDED.
```

## Route State
`RouteState` is the class instance that is passed to all handlers to share information between handlers and routes.

These are methods of the `RouteState` you can use:
 - `skip()` - Set the state to allow skip to skip all the remaining handlers of the current route to proceed to the next route.
 - `unskip()` - Set the state to disallow skip to cancel the skip state.
 - `is_allowed_skip()` - Check if the state is allowed to skip. Values can be `true`, `false`, or `null`. `null` means the state is not set or `skip()` or `unskip()` is not called.
 - `next()` - This must be called for each route handler (except the last handler) to proceed to the next handler.
 - `stop()` - This should be called if you want want to stop the execution for the next handlers of the current route.
 - `is_allowed_next()` - Check if the state is allowed to next. Values can be `true`, `false`, or `null`. `null` means the state is not set or `next()` or `stop()` is not called.
 - `extract_info(Route $route)` - Extracts the information from the result of $route->info() and set it as RouteState properties. Below are the defined route info properties to be extracted:
    - `method` - The HTTP method defined in the route. This also includes the magic methods of this library like ANY, FALLBACK, and STATIC.
    - `path` - The URL path defined in the route. If you set the base path in the router, it will be included in the path.
    - `params` - The URL parameters array defined in the route.
    - `matched` - A boolean value that indicates if the route is matched to the request path.
    - `regex` - The regex pattern used to match the request path to the route path.

Alternatively, you can override the `RouteState` properties by setting it directly like magic. For example:
```php
// Override existing properties
$state->method = 'GET';
$state->path = '/user/1';

// Add new properties
$state->user_id = 1;
```

However, there are protected properties that you can't override which are:
 - `allow_next`
 - `allow_skip`
 - `route`

## Static Handler `Router::static($path, $directory[, ...$handlers])`
 - `$path` - The URL path prefix for the static route.
 - `$directory` - The directory path where the request file needs to be looked up.
 - `$handlers` - Optional route handlers. These can be any callable function, class static method, or object method call which accepts a `RouterState $state` object as a parameter.

### Serve files from a forbidden directory
Static handler can serve files from a forbidden directory that can't be accessed by the client browser directly because of the web server configuration (e.g. Apache's `deny from all` directive). As long as the system user of the web server application have access to the directory, the static handler can serve the files.
```php
$r->static('/', '/');
```