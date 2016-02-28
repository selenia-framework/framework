<?php
namespace Selenia\Matisse\Traits\Context;

use Selenia\Interfaces\InjectorInterface;
use Selenia\Matisse\Components;
use Selenia\Matisse\Components\Base\Component;
use Selenia\Matisse\Components\GenericHtmlComponent;
use Selenia\Matisse\Components\Macro\MacroCall;
use Selenia\Matisse\Exceptions\ComponentException;
use Selenia\Matisse\Parser\Context;

/**
 * Manages components on a rendering Context.
 *
 * @property InjectorInterface injector
 */
trait ComponentsAPITrait
{
  /**
   * A map of tag names to fully qualified PHP component class names.
   * It is initialized to the core Matisse components that can be instantiated via tags.
   *
   * @var array string => string
   */
  private static $coreTags = [
    'Apply'                      => Components\Apply::class,
    'AssetsGroup'                => Components\AssetsGroup::class,
    'Content'                    => Components\Content::class,
    'If'                         => Components\If_::class,
    'Include'                    => Components\Include_::class,
    'Macro'                      => Components\Macro\Macro::class,
    'MacroParam'                 => Components\Macro\MacroParam::class,
    'Script'                     => Components\Script::class,
    'Style'                      => Components\Style::class,
    'Repeat'                     => Components\Repeat::class,
    Components\Literal::TAG_NAME => Components\Literal::class,
    MacroCall::TAG_NAME          => MacroCall::class,
  ];

  /**
   * A map of tag names to fully qualified PHP class names.
   *
   * @var array string => string
   */
  private $tags;

  /**
   * Creates an injectable component instance of the given class.
   *
   * @param string     $class
   * @param Component  $parent
   * @param array|null $props
   * @param array|null $bindings
   * @return Component
   */
  function createComponent ($class, Component $parent, array $props = null, array $bindings = null)
  {
    $component = $this->injector->make ($class);
    return $component->setup ($parent, $this, $props, $bindings);
  }

  /**
   * Creates a component corresponding to the specified tag and optionally sets its published properties.
   *
   * <p>This is called by the parser.
   *
   * @param string     $tagName
   * @param Component  $parent   The component's container component.
   * @param string[]   $props    A map of property names to property values.
   *                             Properties specified via this argument come only from markup attributes, not
   *                             from subtags.
   * @param array|null $bindings A map of attribute names and corresponding databinding expressions.
   * @param bool       $generic  If true, an instance of GenericComponent is created.
   * @param boolean    $strict   If true, failure to find a component class will throw an exception.
   *                             If false, an attempt is made to load a macro with the same name,
   * @return Component Component instance. For macros, an instance of Macro is returned.
   * @throws ComponentException
   */
  function createComponentFromTag ($tagName, Component $parent, array $props = null, array $bindings = null,
                                   $generic = false, $strict = false)
  {
    if ($generic) {
      $component = new GenericHtmlComponent($tagName, $props);
      return $component;
    }
    $class = $this->getClassForTag ($tagName);
    /** @var Context $this */
    if (!$class) {
      if ($strict)
        Component::throwUnknownComponent ($this, $tagName, $parent);

      // Component class not found.
      // Convert the tag to a MacroInstance component instance that will attempt to load a macro with the same
      // name as the tag name.

      if (is_null ($props))
        $props = [];
      $props['macro'] = $tagName;
      $component      = new MacroCall;
    }

    // Component class was found.

    else $component = $this->injector->make ($class);

    // For both types of components:

    if (!$component instanceof Component)
      throw new ComponentException (null,
        sprintf ("Class <kbd>%s</kbd> is not a subclass of <kbd>Component</kbd>", get_class ($component)));

    $component->setTagName ($tagName);
    return $component->setup ($parent, $this, $props, $bindings);
  }

  /**
   * Retrieves the name of the PHP class that implements the component for a given tag.
   *
   * @param string $tag
   * @return string
   */
  function getClassForTag ($tag)
  {
    return get ($this->tags, $tag);
  }

  /**
   * Adds additional tag to PHP class mappings to the context.
   *
   * @param array $tags
   */
  function registerTags (array $tags)
  {
    $this->tags = array_merge ($this->tags, $tags);
  }

}