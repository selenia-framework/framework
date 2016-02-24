<?php
namespace Selenia\ViewEngine;

use Selenia\Exceptions\FatalException;
use Selenia\Interfaces\Views\ViewEngineInterface;
use Selenia\Interfaces\Views\ViewInterface;

class View implements ViewInterface
{
  /**
   * @var mixed
   */
  private $compiled = null;
  /**
   * @var string
   */
  private $source = '';
  /**
   * @var ViewEngineInterface
   */
  private $viewEngine;

  public function __construct (ViewEngineInterface $viewEngine)
  {
    $this->viewEngine = $viewEngine;
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

  function getEngine ()
  {
    return $this->viewEngine;
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

  function render ($data = null)
  {
    if (!$this->compiled)
      $this->compile ();
    $template = $this->compiled ?: $this->source;
    if (is_null ($template))
      throw new FatalException ("No template is set for rendering");
    return $this->viewEngine->render ($template, $data);
  }

}
