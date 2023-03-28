# php-router
PHP Library for handling HTTP requests

[![Build Status](https://api.travis-ci.com/eru123/php-router.svg?branch=main)](https://app.travis-ci.com/github/eru123/php-router)
[![Latest Stable Version](https://poser.pugx.org/eru123/router/v/stable)](https://packagist.org/packages/eru123/router)
[![Total Downloads](https://poser.pugx.org/eru123/router/downloads)](https://packagist.org/packages/eru123/router)
[![License](https://poser.pugx.org/eru123/router/license)](https://packagist.org/packages/eru123/router)

# Usage
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

### Static Handler
```php
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