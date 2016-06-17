<?php
namespace Electro\Localization\Middleware;

use PhpKit\WebConsole\DebugConsole\DebugConsole;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Electro\Application;
use Electro\Interfaces\Http\RequestHandlerInterface;
use Electro\Interfaces\SessionInterface;
use Electro\Localization\Config\LocalizationSettings;
use Electro\Localization\Services\Locale;

/**
 *
 */
class LanguageMiddleware implements RequestHandlerInterface
{
  /**
   * @var bool
   */
  private $debugConsole;
  /**
   * @var Locale
   */
  private $locale;
  /**
   * @var SessionInterface
   */
  private $session;
  /**
   * @var LocalizationSettings
   */
  private $settings;

  /**
   * LanguageMiddleware constructor.
   *
   * @param SessionInterface     $session
   * @param Locale               $locale
   * @param LocalizationSettings $settings
   * @param bool $debugConsole
   */
  function __construct (SessionInterface $session, Locale $locale, LocalizationSettings $settings,
                           $debugConsole)
  {
    $this->session  = $session;
    $this->locale   = $locale;
    $this->settings = $settings;
    $this->debugConsole = $debugConsole;
  }

  function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    $mode = $this->settings->selectionMode ();
    $this->locale->selectionMode ($mode);
    if ($mode == 'session') {
      $lang = $this->session->getLang () ?: $this->locale->defaultLang ();
      $this->locale->locale ($lang);
      $this->session->setLang ($lang);
    }

    if ($this->debugConsole)
      DebugConsole::logger ('config')->inspect ($this->locale);
    return $next();
  }
}
