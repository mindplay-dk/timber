<?php

namespace TreeRoute;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;
use UnexpectedValueException;

/**
 * The Dispatcher implements a convention by which {@see Route::$handlers} are
 * resolved as executable functions and dispatched.
 *
 * The use of this class is optional - you can choose to implement your own
 * dispatcher and define your own conventions for interpretation of handlers,
 * or extend the class and override the {@see toCallable()} method to implement
 * your own convention for turning a handler into something callable.
 *
 * The handler interpretation implemented by this class, is that the handler
 * is simply a class-name, nothing else. This class must implement the empty
 * {@see Controller} interface, to indicate that it follows the run() method
 * naming convention defined by that interface.
 *
 * This convention implies one action per controller (or "action per class")
 * which we believe is a healthy convention: the single responsibility principle
 * is a good, basic principle of OOP - since there is no scenario in which more
 * than one action-method would ever be called during the same request, actions
 * which may serve a higher-order responsibility, such as "managing users", can
 * always be broken down into smaller distinct responsibilites, such as "display
 * user profile", "update user profile", etc.
 */
class Dispatcher
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * @var array map of vars to make available to action methods via parameters
     */
    public $vars = array();

    /**
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * This method implements the convention by which a handler (a string) is turned into
     * a callable function or method-reference.
     *
     * @param string $handler
     *
     * @return callable
     */
    protected function toCallable($handler)
    {
        if (!class_exists($handler, true)) {
            throw new UnexpectedValueException("undefined controller class: {$handler}");
        }

        $controller = new $handler();

        if (!($controller instanceof Controller)) {
            throw new UnexpectedValueException("class does not implement the Controller inteface: {$handler}");
        }

        return array($controller, 'run');
    }

    /**
     * @param callable $callable
     *
     * @return ReflectionFunctionAbstract
     */
    protected function toReflection($callable)
    {
        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }

        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        throw new UnexpectedValueException("expected callable, got: " . print_r($callable, true));
    }

    /**
     * @param string $method HTTP method name
     * @param string $url
     * @param array $vars map of additional vars to make available to action methods via parameters
     *
     * @return mixed|Error return value from the dispatched handler (or an instance of Error)
     */
    public function run($method, $url, $vars = array())
    {
        $result = $this->router->resolve($method, $url);

        if ($result->error) {
            return $result->error;
        }

        $callable = $this->toCallable($result->handler);

        $reflection = $this->toReflection($callable);

        $params = array();

        if (isset($reflection)) {
            foreach ($reflection->getParameters() as $param) {
                $name = $param->getName();

                if (isset($vars[$name])) {
                    $params[$name] = $vars[$name];
                } elseif (isset($result->params[$name])) {
                    $params[$name] = $result->params[$name];
                } elseif (isset($this->vars[$name])) {
                    $params[$name] = $this->vars[$name];
                } elseif ($param->isOptional()) {
                    $params[$name] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException("unable to dispatch handler - missing parameter: {$name}");
                }
            }
        }

        return call_user_func_array($callable, $params);
    }
}
