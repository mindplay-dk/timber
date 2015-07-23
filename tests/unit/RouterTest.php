<?php

use TreeRoute\Dispatcher;
use TreeRoute\Result;
use TreeRoute\Router;

class SampleUrlHelper extends \TreeRoute\UrlHelper
{
    /**
     * @param int $id
     * @param string $title
     *
     * @return string
     */
    public function content($id, $title)
    {
        return "/content/{$id}/{$this->slug($title)}";
    }
}

class SampleController implements \TreeRoute\Controller
{
    public function run($id, $title) {
        return array($id, $title);
    }
}

class RouterTest extends \Codeception\TestCase\Test
{
    use \Codeception\Specify;

    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    /**
     * @param Result $result
     * @param int $code
     */
    private function assertResultIsError(Result $result, $code)
    {
        $this->assertNotNull($result->error, 'expected Error instance');

        if ($result->error) {
            $this->assertEquals($code, $result->error->code);
        }
    }

    /**
     * @param Result $result
     */
    private function assertResultIsSuccess(Result $result)
    {
        $this->assertEmpty($result->error);
    }

    /**
     * @param string $type
     * @param string|null $message
     * @param callable $function
     */
    protected function assertException($type, $message, callable $function)
    {
        $thrown = false;
        $reason = 'no exception thrown';

        try {
            call_user_func($function);
        } catch (Exception $e) {
            if ($e instanceof $type) {
                if ($message === null || strcasecmp($message, $e->getMessage()) === 0) {
                    $thrown = true;
                } else {
                    $reason = "unexpected message: \"{$e->getMessage()}\", expected: \"{$message}\"";
                }
            } else {
                $reason = "unexpected type: " . get_class($e);
            }
        }

        $this->assertTrue($thrown, "Expected exception: {$type}" . ($reason ? " ({$reason})" : ""));
    }

    public function testRouter()
    {
        $router = new Router();

        $router->route('/')->get('handler0');

        $this->specify('should find existed route', function () use ($router) {
            $result = $router->resolve('GET', '/');
            $this->assertResultIsSuccess($result);
            $this->assertEquals('handler0', $result->handler);
        });

        $this->specify('should return 404 error for not existed route', function () use ($router) {
            $result = $router->resolve('GET', '/not/existed/url');
            $this->assertResultIsError($result, 404);
        });

        $this->specify('should return 405 error for unsupported method', function () use ($router) {
            $result = $router->resolve('POST', '/');
            $this->assertResultIsError($result, 405);
            $this->assertEquals(['GET'], $result->error->allowed);
        });

        $this->specify('should define route with short methods', function () use ($router) {
            $router->route('create')->post('handler1');
            $result = $router->resolve('POST', '/create');

            $this->assertEquals('handler1', $result->handler);
        });

        $this->specify('should extract route params', function () use ($router) {
            $router->route('news/<id:int>')->get('handler2');
            $result = $router->resolve('GET', '/news/1');
            $this->assertResultIsSuccess($result);
            $this->assertEquals('handler2', $result->handler);
            $this->assertEquals(1, $result->params['id']);
            $this->assertTrue(1 === $result->params['id'], 'int Symbol should convert to int');

            $result = $router->resolve('GET', '/news/foo');
            $this->assertResultIsError($result, 404);
        });

        $this->specify('should match regexp in params', function () use ($router) {
            $router->route('users/<name:^[a-zA-Z]+$>')->get('handler3');
            $router->route('users/<id:int>')->get('handler4');

            $result = $router->resolve('GET', '/users/@test');
            $this->assertResultIsError($result, 404);

            $result = $router->resolve('GET', '/users/bob');
            $this->assertEquals('handler3', $result->handler);
            $this->assertEquals('bob', $result->params['name']);

            $result = $router->resolve('GET', '/users/123');
            $this->assertEquals('handler4', $result->handler);
            $this->assertEquals(123, $result->params['id']);
        });

        $this->specify('should give greater priority to statically defined route', function () use ($router) {
            $router->route('users/help')->get('handler5');
            $result = $router->resolve('GET', '/users/help');
            $this->assertEquals('handler5', $result->handler);
            $this->assertEmpty($result->params);
        });

        $this->specify('should save and restore routes', function () use ($router) {
            $routes = $router->getRoutes();
            $router = new Router();
            $result = $router->resolve('GET', '/');
            $this->assertResultIsError($result, 405);
            $router->setRoutes($routes);
            $result = $router->resolve('GET', '/');
            $this->assertEquals('handler0', $result->handler);
        });

        $this->specify('should handle pattern substitutions', function () use ($router) {
            $router->route('year/<year:int>')->get('year');

            $result = $router->resolve('GET', '/year/2020');
            $this->assertEquals('year', $result->handler);
            $this->assertEquals(2020, $result->params['year']);
        });

        $this->specify('should match multiple params in one part', function () use ($router) {
            $router->route('archive-<year:int>-<month:int>-<day:int>')->get('archive');
            $result = $router->resolve('GET', '/archive-2015-31-01');
            $this->assertEquals('archive', $result->handler);
            $this->assertEquals(2015, $result->params['year']);
            $this->assertEquals(31, $result->params['month']);
            $this->assertEquals(1, $result->params['day']);
        });

        $this->specify('can build routes progressively', function () {
            $router = new Router();

            // using statement-groups to clarify the created structure:

            $admin = $router->route('admin');
            {
                $upload = $admin->route('upload')->post('upload');

                $menu = $admin->route('menu');
                {
                    $menu->route('load')->get('load');
                    $menu->route('save')->get('save');
                }
            }

            $this->assertEquals('/admin/upload', $upload->pattern);
            $this->assertEquals($menu->route('load'), $router->route('admin/menu/load'));
            $this->assertEquals('load', $router->resolve('GET', '/admin/menu/load')->handler);
            $this->assertEquals('save', $router->resolve('GET', '/admin/menu/save')->handler);
            $this->assertEquals('upload', $router->resolve('POST', '/admin/upload')->handler);
            $this->assertEquals('/admin/menu/load', $router->route('admin/menu/load')->pattern);
        });

        $this->specify('should dispatch handlers with parameters', function () {
            $router = new Router();
            $router->route('content/<id:int>-<title:slug>')->get(SampleController::class);

            $dispatcher = new Dispatcher($router);

            $result = $dispatcher->dispatch('GET', '/content/123-hello-world');
            $this->assertEquals(array('123', 'hello-world'), $result);
        });

        $this->specify('can create URL', function () {
            $router = new Router();
            $router->route('content/<id:int>/<title:slug>')->get('content');

            $url = new SampleUrlHelper();
            $content_url = $url->content(123, 'Hello, World!');

            $this->assertEquals('/content/123/hello-world', $content_url);
            $this->assertEquals('content', $router->resolve('GET', $content_url)->handler);
        });
    }
}
