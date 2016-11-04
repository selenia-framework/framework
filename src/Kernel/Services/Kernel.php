<?php
namespace Electro\Kernel\Services;

use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Interfaces\ProfileInterface;
use Electro\Kernel\Lib\ModuleInfo;
use Electro\Traits\EventEmitterTrait;
use Exception;
use PhpKit\WebConsole\Lib\Debug;
use Psr\Log\LoggerInterface;

/**
 * Use this event for overriding core framework services.
 */
const PRE_REGISTER = 0;
/**
 * Use this event for registering a module's services on the injector.
 */
const REGISTER_SERVICES = 1;
/**
 * Use this event for configuring services.
 */
const CONFIGURE = 2;
/**
 * Use this event for performing additional initialization/configuration steps.
 */
const RECONFIGURE = 3;

/**
 * The service that loads the bulk of the framework code and the application's modules.
 *
 * <p>Modules should use this service to subscribe to startup events (see the `Electro\Kernel\Services` constants).
 */
class Kernel implements KernelInterface
{
  use EventEmitterTrait;

  /**
   * @var InjectorInterface
   */
  private $injector;
  /**
   * @var ProfileInterface
   */
  private $profile;

  function __construct (InjectorInterface $injector, ProfileInterface $profile)
  {
    $this->injector = $injector;
    $this->profile  = $profile;
  }

  function boot ()
  {
    /*
     * Load all remaining modules, allowing them to subscribe to bootstrap events.
     */
    $exclude    = array_flip ($this->profile->getExcludedModules ());
    $subsystems = array_flip ($this->profile->getSubsystems ());

    /** @var ModulesRegistry $registry */
    $registry = $this->injector->make (ModulesRegistry::class);

    try {
      foreach ($registry->onlyBootable ()->onlyEnabled ()->getModules () as $name => $module) {
        /** @var ModuleInfo $module */
        if (isset ($exclude[$module->name]) ||
            ($module->type == ModuleInfo::TYPE_SUBSYSTEM && !isset($subsystems[$module->name]))
        ) continue;
        $modBoot = $module->bootstrapper;
        /** @var ModuleInterface|string $modBoot */
        if (!class_exists ($modBoot)) // don't load this module.
          $this->logModuleError ("Class <kbd>$modBoot</kbd> was not found.");
        elseif (is_a ($modBoot, ModuleInterface::class, true))
          $modBoot::startUp ($this, $module);
        //else ignore the module
      }

      /**
       * Boot up all non-core modules.
       */

      $this->emit (PRE_REGISTER, $this->injector);
      $this->emit (REGISTER_SERVICES, $this->injector);
      $this->emitAndInject (CONFIGURE);
      $this->emitAndInject (RECONFIGURE);
    }
    catch (Exception $e) {
      $this->logModuleError ($e->getMessage (), $e);
    }
  }

  function getProfile ()
  {
    return $this->profile;
  }

  function onConfigure (callable $handler)
  {
    return $this->on (CONFIGURE, $handler);
  }

  function onPreRegister (callable $handler)
  {
    return $this->on (PRE_REGISTER, $handler);
  }

  function onReconfigure (callable $handler)
  {
    return $this->on (RECONFIGURE, $handler);
  }

  function onRegisterServices (callable $handler)
  {
    return $this->on (REGISTER_SERVICES, $handler);
  }

  /**
   * Emits an event to all handlers registered to that event (if any), injecting the arguments to each calling handler.
   *
   * @param string $event The event name.
   */
  protected function emitAndInject ($event)
  {
    foreach (get ($this->listeners, $event, []) as $l)
      $this->injector->execute ($l);
  }

  private function logModuleError ($message, Exception $e = null)
  {
    if (!$e)
      $e = new \RuntimeException ($message);
    // Make sure the exception gets logged before throwing it.
    // Note: the logger is lazily created to allow some module to override it before this error occurs.
    Debug::logException ($this->injector->make (LoggerInterface::class), $e);

    throw $e;
  }

}