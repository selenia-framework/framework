<?php
namespace Electro\Sessions\Config;

use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Interfaces\SessionInterface;
use Electro\Kernel\Lib\ModuleInfo;
use Electro\Sessions\Services\Session;

class SessionsModule implements ModuleInterface
{
  static function startUp (KernelInterface $kernel, ModuleInfo $moduleInfo)
  {
    $kernel->onRegisterServices (
      function (InjectorInterface $injector) {
        $injector
          ->alias (SessionInterface::class, Session::class)
          ->share (Session::class, 'session');
      });
  }

}
