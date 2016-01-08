<?php
namespace Selenia\Matisse\Components;

use Selenia\Matisse\Components\Base\Component;
use Selenia\Matisse\Properties\Base\ComponentProperties;
use Selenia\Matisse\Properties\TypeSystem\type;

class ScriptProperties extends ComponentProperties
{
  /**
   * If set, allows inline scripts deduplication by ignoring Script instances with the same name as a previously run
   * Script.
   * > This only applies to inline scripts, external scripts are always deduplicated.
   *
   * @var string
   */
  public $name = [type::id];
  /**
   * If set, the URL for an external script.<br>
   * If not set, the tag content will be used as an inline script.
   *
   * @var string
   */
  public $src = '';
}

class Script extends Component
{
  protected static $propertiesClass = ScriptProperties::class;

  public $allowsChildren = true;
  /** @var ScriptProperties */
  public $props;

  /**
   * Registers a script on the Page.
   */
  protected function render ()
  {
    $prop = $this->props;
    if (exists ($prop->src))
      $this->page->addScript ($prop->src);
    else {
      $css = $this->getContent ();
      if ($css != '')
        $this->page->addInlineScript ($css, $prop->name);
    }
  }
}

