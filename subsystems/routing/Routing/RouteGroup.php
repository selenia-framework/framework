<?php
namespace Selenia\Routing;

use Selenia\Exceptions\Fatal\ConfigException;
use Selenia\Http\Controllers\Controller;
use Selenia\Modules\Admin\Models\User;
use Selenia\Sessions\Session;

class RouteGroup extends AbstractRoute
{
  public $defaultURI;
  public $baseSubnavURI;
  public $includeMainItemOnNav = false;

  public function __construct (array &$init = null)
  {
    parent::__construct ($init);
  }

  public function getTypes ()
  {
    return array_merge (parent::getTypes (), [
      'prefix'               => 'string',
      'module'               => 'string',
      'URI'                  => 'string',
      'defaultURI'           => 'string',
      'baseSubnavURI'        => 'string',
      'includeMainItemOnNav' => 'boolean',
      'userType'             => 'string',
      'menu'                 => 'array',
    ]);
  }

  public function preinit ()
  {
    parent::preinit ();
    if (isset($this->baseSubnavURI))
      $this->baseSubnavURI = str_replace ('*', '[^/]*', $this->baseSubnavURI);

//    if (!empty($this->URI))
//      $this->prefix = empty($this->inheritedPrefix) ? $this->URI : "$this->inheritedPrefix/$this->URI";
  }

  public function init ($parent) {
    if (isset($this->defaultURI) && $this->inheritedPrefix)
      $this->defaultURI = "$this->inheritedPrefix/$this->defaultURI";
    if (isset($this->prefix))
      $this->URI = $this->inheritedPrefix ? "$this->inheritedPrefix/$this->prefix" : $this->prefix;
    parent::init ($parent);
  }

  public function searchFor ($URI, $options = 0)
  {
    global $session;
      /** @var Session $session */
    if (isset($this->userType)) {
      /** @var User $user */
      $user = $session->user();
      if ($this->userType[0] == '!') {
        if ($user->type == substr($this->userType, 1))
          return null;
      }
      else if ($user->type != $this->userType)
        return null;
    }
    if ($this->matchesURI ($URI)) {
      $this->selected = true;
      if (empty($this->defaultURI))
        throw new ConfigException("No default URI is configured for the route group matching $URI");
      Controller::redirect($this->defaultURI);
    }
    if (isset($this->routes))
      foreach ($this->routes as $route) {
      /** @var AbstractRoute $route */
        $result = $route->searchFor ($URI, $key = null);
        if (isset($result)) {
          $this->selected = true;
          return $result;
        }
      }
    return null;
  }
}