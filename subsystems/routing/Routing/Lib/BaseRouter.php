<?php

namespace Electro\Routing\Lib;

use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\Http\RouteMatcherInterface;
use Electro\Interfaces\Http\RouterInterface;
use Electro\Interfaces\Http\Shared\CurrentRequestInterface;
use Electro\Interfaces\RenderableInterface;
use Electro\Interop\InjectableFunction;
use Electro\Traits\InspectionTrait;
use Iterator;
use League\Glide\Server;
use PhpKit\Flow\Flow;
use PhpKit\WebConsole\Lib\Debug;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A service that assists in routing an HTTP request to one or more request handlers.
 *
 * > **Note:** instances of this class are immutable.
 */
abstract class BaseRouter implements RouterInterface
{
  use InspectionTrait;

  static public  $INSPECTABLE  = ['routingEnabled', 'handlers'];
  static private $stackCounter = 0;
  /**
   * @var bool When false, the iteration keys of the pipeline elements are ignored;
   * when true (the default), they are used as routing patterns.
   */
  public $routingEnabled = true;
  /**
   * Holds the current request for the applications's global middlware stack; updated as the router calls request
   * handlers.
   *
   * @var CurrentRequestInterface
   */
  protected $currentRequest;
  /**
   * **Note:** used by RoutingMiddleware
   *
   * @var array|\Traversable
   */
  protected $handlers;
  /**
   * **Note:** used by RoutingMiddleware
   *
   * @var InjectorInterface
   */
  protected $injector;
  /**
   * For use by logging/debugging decorators.
   *
   * @var int
   */
  protected $stackId;
  /**
   * @var RouteMatcherInterface
   */
  private $matcher;

  public function __construct (RouteMatcherInterface $matcher, InjectorInterface $injector,
                               CurrentRequestInterface $currentRequest)
  {
    $this->matcher        = $matcher;
    $this->injector       = $injector;
    $this->currentRequest = $currentRequest;
  }

  function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    return empty($this->handlers) ? $next () : $this->route ($this->handlers, $request, $response, $next);
  }

  function add ($handlers, $key = null, $before = null, $after = null)
  {
    if ($handlers) {
      if (!is_array ($handlers))
        $handlers = [$handlers];
      if (empty($this->handlers))
        $this->handlers = [];
      else if (!is_array ($this->handlers))
        $this->handlers = iterator_to_array ($this->handlers);
      if (isset($before))
        $this->handlers = array_insertBeforeKey ($this->handlers, $before, $handlers);
      else $this->handlers = array_insertAfterKey ($this->handlers, $after, $handlers);
    }
    return $this;
  }

  function append ($handlers)
  {
    foreach ($handlers as $handler)
      $this->add ($handler);
  }

  /**
   * Performs the actual routing.
   *
   * @param mixed                  $routable
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param callable               $next
   * @return ResponseInterface
   * @throws \Auryn\InjectionException
   */
  function route ($routable, ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    if (is_null ($routable))
      return $next ();

    if (is_callable ($routable)) {
      if ($routable instanceof InjectableFunction) {
        $instance = $this->runInjectable ($routable);
        return $this->route ($instance, $request, $response, $next);
      }
      else $response = $this->callHandler ($routable, $request, $response, $next);
    }
    else {
      if ($routable instanceof \IteratorAggregate)
        $routable = $routable->getIterator ();

      elseif (is_array ($routable))
        $routable = new \ArrayIterator($routable);

      if ($routable instanceof Iterator) {
        $response = $this->iteration_start ($routable, $request, $response, ++self::$stackCounter);
        return $response ?: $this->iteration_stop ($request, $response, $next);
      }

      elseif (is_string ($routable)) {
        $routable = $this->injector->make ($routable);

        if (is_callable ($routable))
          $response = $this->callHandler ($routable, $request, $response, $next);

        else throw new \RuntimeException (sprintf ("Instances of class <span class=class>%s</span> are not routable.",
          Debug::getType ($routable)));
      }
      else throw new \RuntimeException (sprintf ("Invalid routable type <span class=type>%s</span>.",
        typeOf ($routable)));
    }

    return $response;
  }

  function set ($handlers)
  {
    // Convert the list to an interable and prunes it of NULL values.
    $this->handlers = Flow::from ($handlers)->where (identity ());
    return $this;
  }

  function with ($handlers)
  {
    if (!is_iterable ($handlers))
      $handlers = [$handlers];
    $class = get_class ($this);
    /** @var static $new */
    $new = new $class ($this->injector, $this->matcher);
    return $new->set ($handlers);
  }

  /**
   * Invokes a request handler.
   *
   * <p>The router does not call handlers directly; instead, it does it trough this method, so that calls can be
   * intercepted, validated and logged.
   *
   * > This method also functions as a router extension point.
   *
   * @param callable               $handler
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param callable               $next
   * @return ResponseInterface
   * @throws \Auryn\InjectionException
   */
  protected function callHandler (callable $handler, ServerRequestInterface $request, ResponseInterface $response,
                                  callable $next)
  {
    $this->currentRequest->setInstance ($request);

    if ($handler instanceof RenderableInterface) {
      $class = $handler->getContextClass ();
      $handler->setContext ($this->injector->make ($class));
    }

    /*try {
      $response = $handler ($request, $response, $next);
    }
    catch (HttpException $error) {
      // Convert HTTP exceptions to normal responses
      $msg = $error->getTitle () ?: str_segmentsFirst ($error->getMessage (), "\n"); // use only the first line of text
      return $response->withStatus ($error->getCode (), $msg);
    }*/

    $newResponse = $handler ($request, $response, $next);

    if (!$newResponse)
      throw new \RuntimeException (sprintf (
        "Request handler <span class=__type>%s</span> did not return a response.",
        Debug::getType ($handler)
      ));

    if (!is_object ($newResponse) || !($newResponse instanceof ResponseInterface))
      return $this->route ($newResponse, $request, $response, $next);

    return $newResponse;
  }

  /**
   * Begins iterating the handler pipeline and continue iterating until a handler returns without calling the provided
   * `$next` argument or the iteration ends.
   *
   * After the iteration stops and all called handlers have returned an HTTP response, it returns the final response
   * to the caller.
   *
   * > This method also functions as a router extension point.
   *
   * @param Iterator               $it
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param int                    $stackId For use by logging/debugging decorators.
   * @return ResponseInterface|null
   * @throws \Auryn\InjectionException
   */
  protected function iteration_start (Iterator $it,
                                      ServerRequestInterface $request,
                                      ResponseInterface $response,
                                      $stackId)
  {
    inspect ("BEGIN", $request->getRequestTarget ());
    $this->stackId = $stackId;
    $middleware    = [];
    $patterns      = [];
    // Split the iteration into a list of middleware and a map of route patterns.
    foreach ($it as $key => $routable)
      if (is_int ($key))
        $middleware[] = $routable;
      else $patterns[$key] = $routable;

    if ($middleware) {
      $prevReq = $request;
      $prevRes = $response;
      // Define the middleware iterator
      $next = function ($req = null, $res = null) use (&$middleware, &$next, &$patterns, &$prevReq, &$prevRes) {
        $req = $req ?: $prevReq;
        $res = $res ?: $prevRes;
        $prevReq = $req;
        $prevRes= $res;
        if ($middleware) {
          $handler = array_shift ($middleware);
          return $this->route ($handler, $req, $res, $next);
        }
        // The end of the stack was reached.
        return $this->routingEnabled ? $this->match_patterns ($patterns, $req, $res) : null;
      };
      // Run the middleware.
      return $next ($request, $response);
    }

    // No middleware; just match the patterns.
    return $this->routingEnabled ? $this->match_patterns ($patterns, $request, $response) : null;
  }

  /**
   * @param array                  $patterns
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @return ResponseInterface|null
   * @throws \Auryn\InjectionException
   */
  protected function match_patterns (array $patterns, ServerRequestInterface $request,
                                     ResponseInterface $response)
  {
    inspect ("MATCH PATTERNS", $request->getRequestTarget ());
    foreach ($patterns as $key => $routable) {
      $newReq = $this->iteration_step ($key, $routable, $request, $response);
      if ($newReq)
        return $this->iteration_stepMatchRoute ($key, $routable, $newReq, $response);
      else $this->iteration_stepNotMatchRoute ($key, $routable, $request, $response);
    }
    return null; // No match was found.
  }

  /**
   * Invoked when a route iteration step takes place.
   * > This method also functions as a router extension point.
   *
   * @param string                      $key
   * @param mixed                       $routable
   * @param ServerRequestInterface|null $request
   * @param ResponseInterface|null      $response
   * @return boolean|ServerRequestInterface false if the pattern doesn't match the path, a modified request instance
   *                                                otherwise.
   */
  protected function iteration_step ($key, $routable, ServerRequestInterface $request = null,
                                     ResponseInterface $response = null)
  {
    inspect ("PATTERN", $request->getRequestTarget ());
    return $this->matcher->match ($key, $request);
  }

  /**
   * Invoked when a route iteration step matches a middleware.
   * > The main purpose of this method is to provide a router extension point.
   *
   * @param string                 $key
   * @param mixed                  $routable
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param callable               $nextIteration
   * @return ResponseInterface
   * @throws \Auryn\InjectionException
   */
  protected function iteration_stepMatchMiddleware ($key, $routable, ServerRequestInterface $request,
                                                    ResponseInterface $response, callable $nextIteration)
  {
    inspect ("MIDDLEWARE", $key);
    return $this->route ($routable, $request, $response, $nextIteration);
  }

  /**
   * Invoked when a route iteration step matches a route.
   * > The main purpose of this method is to provide a router extension point.
   *
   * @param string                 $key
   * @param mixed                  $routable
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @return ResponseInterface
   * @throws \Auryn\InjectionException
   */
  protected function iteration_stepMatchRoute ($key, $routable, ServerRequestInterface $request,
                                               ResponseInterface $response)
  {
    inspect ("MATCH $key", $request->getRequestTarget ());
    return $this->route ($routable, $request, $response, back ());
  }

  /**
   * Invoked when a route iteration step doesn't match a route.
   * > The main purpose of this method is to provide a router extension point and for debugging.
   *
   * @param string                 $key
   * @param mixed                  $routable
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   */
  protected function iteration_stepNotMatchRoute ($key, $routable, ServerRequestInterface $request,
                                                  ResponseInterface $response)
  {
    inspect ("NOT MATCH $key", $request->getRequestTarget ());
  }

  /**
   * Invoked when route iteration ended without a final response being generated.
   * > The main purpose of this method is to provide a router extension point.
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param callable               $next
   * @return ResponseInterface
   */
  protected function iteration_stop (ServerRequestInterface $request, ResponseInterface $response,
                                     callable $next)
  {
    inspect ("END", $request->getRequestTarget ());
    return $next ($request, $response);
  }

  /**
   * Runs a given factory routable.
   * > The main purpose of this method is to provide a router extension point.
   *
   * @param \Electro\Interop\InjectableFunction $fn
   * @return mixed A routable instance.
   * @throws \Auryn\InjectionException
   */
  protected function runInjectable (InjectableFunction $fn)
  {
    return $this->injector->execute ($fn ());
  }

}

