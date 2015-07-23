TreeRoute - request router
==========================

TreeRoute is a performance focused request router with regular expressions support.

Installation
-----------

Install the latest version with `composer require baryshev/tree-route`

Usage
-----

Basic usage:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$router = new \TreeRoute\Router();

// Defining route for one HTTP method
$router->route('/news')->get('handler0');

// Defining route for several HTTP methods
$router->route('/')->get('get_handler1')->post('post_handler1');

// Defining a route with regular expression param and keeping a reference to the Route object
$news_route = $router->route('/news/<id:^[0-9]+$>')->get('handler2')->name('show_news');

// Creating a URL using the "show_news" named route:
var_dump($news_route->url(['id' => 123])); // => "/news/123"

// Defining another route with symbolic param
$router->route('/news/<slug:slug>')->get('handler3');

// Defining static route that conflicts with previous route, but static routes have high priority
$router->route('/news/all')->get('handler4');

// Defining another route
$router->route('/news')->get('handler5');

$method = 'GET';

// Optionally pass HEAD requests to GET handlers
// if ($method == 'HEAD') {
//    $method = 'GET';
// }

$url = '/news/1';

$result = $router->resolve($method, $url);

if (!$result->error) {
    $handler = $result->handler;
    $params = $result->params;
    // Do something with handler and params
} else {
    switch ($result->error->code) {
        case 404 :
            // Not found handler here
            break;
        case 405 :
            // Method not allowed handler here
            $allowedMethods = $result->error->allowed;
            if ($method == 'OPTIONS') {
                // OPTIONS method handler here
            }
            break;
    }
}
```

Save and restore routes (useful for routes caching):

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$router = new \TreeRoute\Router();
$router->addRoute(['GET', 'POST'], '/', 'handler0');
$router->addRoute('GET', '/news', 'handler1');

$routes = $router->getRoutes();

$anotherRouter = new \TreeRoute\Router();
$anotherRouter->setRoutes($routes);

$method = 'GET';
$url = '/news';

$result = $anotherRouter->dispatch($method, $url);
```

Benchmark
---------

https://github.com/baryshev/FastRoute-vs-TreeRoute

Design Notes
------------

During the development of this library, a [design problem](commit/8bb93921c0a8b90d97f0143c0eebdf4ba44b0294)
was identified, which required us to make a trade-off. This library did at one point have URL creation as
a feature, but after carefully weighing the pros and cons, it was decided to forego this feature, in favor
of simpler implementation and support for caching.

Use-cases for [three different approaches](https://gist.github.com/mindplay-dk/feb4768dbb118c651ba0)
were explored and evaluated - our [whiteboard](https://goo.gl/photos/CZLk7iJCzeJfS3A58) summarizes the
pros and cons as we saw them, and the approach without URL creation was unanimously our favorite, as it
leads to the greatest simplicity, both in the library and in the use-case, and supports caching.

The first trade-off is that we don't get to use closures (which can't be serialized) and thereby do not
get any direct static coupling between the route and controller/action/params - we do get static coupling
to the controller class-name, by using the `::class` constant.

The other trade-off is that we can't have a URL creation feature within the router itself, as this leads to
either complexity (with the addition of a named route registry as per [case 1](https://gist.github.com/mindplay-dk/feb4768dbb118c651ba0#file-router-1-php))
or prevents caching (as per [case 2](https://gist.github.com/mindplay-dk/feb4768dbb118c651ba0#file-router-2-php) -
after some discussion, we decided URL creation provides only a small benefit, guaranteeing that URL creation
is consistent with defined patterns; but also, we value the freedom to fully customize URL creation on a
case-by-case basis using simpler code (as per [case 3](https://gist.github.com/mindplay-dk/feb4768dbb118c651ba0#file-router-3-php))
and as such the absence of URL creation can actually be seen as a benefit.
