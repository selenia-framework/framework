<?php
namespace Selenia\Routing\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Selenia\Application;
use Selenia\Exceptions\HttpException;
use Selenia\Interfaces\InjectorInterface;
use Selenia\Interfaces\MiddlewareInterface;
use Selenia\Router;
use Selenia\Routing\RoutingMap;

/**
 *
 */
class RoutingMiddleware implements MiddlewareInterface
{
  private $app;
  private $injector;

  function __construct (Application $app, InjectorInterface $injector)
  {
    $this->app      = $app;
    $this->injector = $injector;
  }

  function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    return $next();

    $this->loadRoutes ();

    if ($this->app->debugMode) {
      $filter = function ($k, $v) { return $k !== 'parent' || is_null ($v) ?: '...'; };
      WebConsole::routes ()->withFilter ($filter, $this->app->routingMap->routes);
    }

    try {
      $router = Router::route ();
      $this->injector->share ($router);
    } catch (HttpException $e) {
      @ob_get_clean ();
      http_response_code ($e->getCode ());
      echo $e->getMessage ();
      exit;
    }
    return $router->controller->__invoke ($request, $response, $next);
  }

  private function loadRoutes ()
  {
    $map         = $this->app->routingMap = new RoutingMap;
    $map->routes = array_merge ($map->routes, $this->app->routes);
    $map->init ();
  }

}
