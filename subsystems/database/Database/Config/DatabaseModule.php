<?php
namespace Selenia\Database\Config;

use PhpKit\Connection;
use PhpKit\ConnectionInterface;
use Selenia\Database\Lib\DebugConnection;
use Selenia\Database\Services\ModelController;
use Selenia\Interfaces\DI\InjectorInterface;
use Selenia\Interfaces\DI\ServiceProviderInterface;
use Selenia\Interfaces\ModelControllerInterface;

class DatabaseModule implements ServiceProviderInterface
{
  function register (InjectorInterface $injector)
  {
    $injector
      ->share (ConnectionInterface::class)
      ->delegate (ConnectionInterface::class, function ($debugConsole) {
        $con = $debugConsole ? new DebugConnection : new Connection;
        return $con->getFromEnviroment ();
      })
      ->alias (ModelControllerInterface::class, ModelController::class)
      ->share (ModelController::class);
  }

}
