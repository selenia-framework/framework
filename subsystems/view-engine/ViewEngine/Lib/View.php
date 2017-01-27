<?php

namespace Electro\ViewEngine\Lib;

use Electro\Exceptions\FatalException;
use Electro\Interfaces\Views\ViewEngineInterface;
use Electro\Interfaces\Views\ViewInterface;
use Electro\Interfaces\Views\ViewModelInterface;
use Electro\ViewEngine\Config\ViewEngineSettings;

class View implements ViewInterface
{
  /**
   * @var mixed
   */
  private $compiled = null;
  /**
   * @var ViewEngineSettings
   */
  private $engineSettings;
  /**
   * @var string
   */
  private $path;
  /**
   * @var string
   */
  private $source = '';
  /**
   * @var ViewEngineInterface
   */
  private $viewEngine;

  public function __construct (ViewEngineSettings $engineSettings)
  {
    $this->engineSettings = $engineSettings;
  }

  function compile ()
  {
    if (is_null ($this->source))
      throw new FatalException ("No template is set for compilation");
    $this->compiled = $this->viewEngine->compile ($this->source);
    return $this;
  }

  function getCompiled ()
  {
    return $this->compiled;
  }

  function setCompiled ($compiled)
  {
    $this->compiled = $compiled;
    return $this;
  }

  function getEngine ()
  {
    return $this->viewEngine;
  }

  function getPath ()
  {
    return $this->path;
  }

  /**
   * Sets the full filesystem path of the template that originated this view, if a template was loaded.
   *
   * <p>This is meaningless for dynamically generated views.
   *
   * @return $this
   */
  public function setPath ($path)
  {
    $this->path = $path;
    return $this;
  }

  function getSource ()
  {
    return $this->source;
  }

  function setSource ($src)
  {
    $this->source   = $src;
    $this->compiled = null;
    return $this;
  }

  function render (ViewModelInterface $data = null)
  {
    if (!$this->compiled)
      $this->compile ();
    $template = $this->compiled ?: $this->source;
    if (is_null ($template))
      throw new FatalException ("No template is set for rendering");
    return $this->viewEngine->render ($template, $data);
  }

  /**
   * Sets the view engine to be used for compiling and rendering the view.
   *
   * @param ViewEngineInterface $viewEngine
   * @return $this
   */
  public function setEngine (ViewEngineInterface $viewEngine)
  {
    $this->viewEngine = $viewEngine;
    return $this;
  }

}
