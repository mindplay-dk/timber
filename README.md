# mindplay/timber

[![PHP Version](https://img.shields.io/badge/php-5.4%2B-blue.svg)](https://packagist.org/packages/mindplay/timber)
[![Build Status](https://travis-ci.org/mindplay-dk/timber.svg)](https://travis-ci.org/mindplay-dk/timber)

Timber is a request router with regular expression support, high performance, and a
developer-friendly, human-readable API.

Originally a fork of [TreeRoute](https://github.com/baryshev/TreeRoute) by
[Vadim Baryshev](https://github.com/baryshev), the API and feature-set quickly
grew into something else entirely. What does carry over from the original fork,
is great performance.


## Installation

Install the latest version with `composer require mindplay/timber`


## Introduction

The core router system is independent of some other optional features - this includes
a Dispatcher and a UrlHelper, both of which work great together with the router, but
the router itself is not dependent on either of these components.


## Usage

In the examples below, we assume that handlers (attached using `get()`, `post()`, etc.)
are controller class-names - the included Dispatcher (see below) uses a class-per-action
strategy, which may not be everyone's cup of tea, but the point is that, the meaning or
interpretation of the handler string isn't baked into the router; it's supported by the
Dispatcher only, and as such, you can easily implement any convention or strategy you
like, either by doing your own dispatch (without using our Dispatcher) or by extending
the Dispatcher and overriding the `toCallable()` function.

Note the use of the magic `::class` constant in the following examples - the library is
compatible with PHP 5.4, but this syntax is only supported by PHP 5.5 and up.

Basic usage of the router, without Dispatcher, goes a little something like this:

```PHP
use mindplay\timber\Router;

require __DIR__ . '/vendor/autoload.php';

$router = new Router();

// Defining route for one HTTP method
$router->route('news')->get(ListNews::class);

// Defining route for several HTTP methods
$router->route('/')->get(ShowHomePage::class)->post(PostComments::class);

// Defining a route with regular expression param
$news_route = $router->route('news/<id:^[0-9]+$>')->get(ShowNews::class);

// Defining another route with symbolic param
$router->route('users/<username:slug>')->get(ShowUser::class);

// Defining static route that conflicts with previous route, but static routes have high priority
$router->route('news/all')->get(ShowAllNews::class);

// Defining a wildcard route, matching e.g. "categories/foo", "categories/foo/bar", etc.:
$router->route('categories/<path:*>')->get(ShowCategory::class);

// Resolve HTTP method and URL:

$method = 'GET';
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

If you're building a set of routes under the same parent route, you can continue routing
from by keeping a temporary reference to a parent route - for example:

```PHP
$admin = $router->route('admin')->get(AdminMenu::class);

$admin->route('users')->get(AdminUserList::class);
$admin->route('groups')->get(AdminGroupList::class);
```

This example will route `/admin` to `AdminMenu`, and `/admin/users` to `AdminUserList`, etc.

Continuing from a parent route avoids the overhead of repeatedly parsing the same parent path,
and also enables modular reuse of route definitions - for example:

```PHP
$build_comment_routes = function (Route $parent) {
    $parent->route('comments')->get(ShowComments::class);
    $parent->route('comments/new')->get(ShowCommentForm::class)->post(PostComment::class);
}

$build_comment_routes($router->route('articles/<article_id:int>'));
$build_comment_routes($router->route('products/<product_id:int>'));
```

This example creates two identical sets of routes for displaying and posting comments for two
different parent routes.


### Dispatch

We also provide a [Dispatcher](src/Dispatcher.php) class and [Controller](src/Controller.php)
interface, following a simple convention of handler names being class-names, and a
class-per-action strategy:

```PHP
use mindplay\timber\Router;
use mindplay\timber\Controller;
use mindplay\timber\Dispatcher;

class ShowNews implements Controller
{
    public function run($id) {
        // load and display news article...
    }
}

$router = new Router();
$router->route('news/<id:int>', ShowNews::class);

$dispatcher = new Dispatcher($router);

$dispatcher->run('GET', '/news');
```

The named parameter in this example will be converted to an integer and provided
to the `run()` method.


### URL Creation

In addition, we provide a [base class for URL creation helpers](src/UrlHelper.php) -
this has no direct relationship with the router as such, it's provided for
convenience, to support our overall strategy of creating testable URL helpers.


## Optimization

You can save and restore the defined routes:

```php
use mindplay\timber\Router;

require __DIR__ . '/vendor/autoload.php';

$router = new Router();
$router->route('/')->get(ShowHomePage::class);
$router->route('/news')->get(ShowNews::class);

$routes = $router->getRoutes();

$anotherRouter = new Router();
$anotherRouter->setRoutes($routes);

$method = 'GET';
$url = '/news';

$result = $anotherRouter->resolve($method, $url);
```

The point is, you can serialize/unserialize the routes that have been built, and
store them in a cache somewhere, to avoid the initialization overhead. For most
projects, this would be considered a micro-optimization - the overhead of building
an extremely high number of routes (hundreds or thousands) may make this worthwhile
in a very large modular project.


## Hacking

To run the test-suite, navigate to the project root folder and type:

    php test/test.php


## Design Notes

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
