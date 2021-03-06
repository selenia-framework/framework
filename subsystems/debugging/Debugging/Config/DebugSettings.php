<?php

namespace Electro\Debugging\Config;

use Electro\Kernel\Services\Kernel;
use Electro\Logging\Config\LogSettings;
use Electro\Profiles\WebProfile;

/**
 * Settings for the Debugging subsystem.
 */
class DebugSettings
{
  /**
   * Indicates whether the application is running in a development (aka debugging) environment (TRUE) or not (FALSE).
   *
   * @var bool
   */
  public $devEnv;
  /**
   * @var bool TRUE to enable logging of several configuration settings and its display on a web console panel.
   */
  public $logConfig = true;
  /**
   * ><p>**Warning**: enabling this will have a severe impact on performance!
   *
   * @var bool TRUE to enable logging of the server-side DOM and its display on a web console panel.
   */
  public $logDOM = false;
  /**
   * @var bool TRUE to enable logging of database queries and its display on a web console panel.
   */
  public $logDatabase = true;
  /**
   * @var bool TRUE to enable logging of inspection commands and its display on a web console panel.
   */
  public $logInspections = true;
  /**
   * @var bool TRUE to enable logging of the view model and its display on a web console panel.
   */
  public $logModel = true;
  /**
   * @var bool TRUE to enable logging of all defined navigation links and its display on a web console panel.
   */
  public $logNavigation = true;
  /**
   * @var bool TRUE to enable logging of profiling data and its display on a web console panel.
   */
  public $logProfiling = true;
  /**
   * @var bool TRUE to enable logging of the HTTP request object and its display on a web console panel.
   */
  public $logRequest = true;
  /**
   * @var bool TRUE to enable logging of the HTTP response object and its display on a web console panel.
   */
  public $logResponse = true;
  /**
   * @var bool TRUE to enable logging of the traversed routes and middleware and its display on a web console panel.
   */
  public $logRouting = true;
  /**
   * @var bool TRUE to enable logging of session-related information and its display on a web console panel.
   */
  public $logSession = true;
  /**
   * @var bool TRUE to enable logging of the current view state its display on a web console panel.
   */
  public $logView = true;
  /**
   * Indicates whether the Web Console is displayed (TRUE) or not (FALSE).
   *
   * <p>Even if TRUE, the console is only displayed on a development environment (i.e. when {@see $devEnv}=true).
   *
   * @var bool
   */
  public $webConsole;

  /**
   * @var bool For use by {@see webConsoleIsAvailable}
   */
  private $isWebProfile;

  public function __construct (Kernel $kernel, LogSettings $logSettings)
  {
    $this->devEnv       = $kernel->devEnv ();
    $this->webConsole   = env ('CONSOLE', false);
    $this->isWebProfile = $kernel->getProfile () instanceof WebProfile;;
  }

  function isWebConsoleAvailable ()
  {
    return $this->webConsole && $this->isWebProfile;
  }

}
