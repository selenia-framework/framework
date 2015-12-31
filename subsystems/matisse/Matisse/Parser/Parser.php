<?php
namespace Selenia\Matisse\Parser;

use Selenia\Matisse\Components\Base\Component;
use Selenia\Matisse\Components\Internal\Metadata;
use Selenia\Matisse\Components\Internal\Page;
use Selenia\Matisse\Components\Internal\Text;
use Selenia\Matisse\Components\Literal;
use Selenia\Matisse\Exceptions\ParseException;
use Selenia\Matisse\Properties\TypeSystem\type;

class Parser
{
  const EXP_BEGIN            = '{{';
  const EXP_END              = '}}';
  const NO_TRIM              = 0;
  const PARSE_ATTRS          = '# ([\w\-\:]+) \s* (?: = \s* ("|\') (.*?) \2 )? (\s|@) #sxu';
  const PARSE_DATABINDINGS   = '# (?: \{\{ | \{!! ) ( .*? ) (?: \}\} | !!\} ) #xu';
  const PARSE_TAG            = '# (<) (/?) ([A-Z]\w+) \s* (.*?) (/?) (>) #sxu';
  const TRIM                 = 3;
  const TRIM_LEFT            = 1; // @ is at the end of the attrs string and it's used as a marker.
  const TRIM_LEFT_CONTENT    = '# (?<=\>) \s+ (?=\s) #xu';
  const TRIM_LITERAL_CONTENT = '# (?<=\>) \s+ (?=\s) | (?<=\s) \s+ (?=\<) #xu';
  const TRIM_RIGHT           = 2;
  const TRIM_RIGHT_CONTENT   = '# (?<=\s) \s+ (?=\<) #xu';
  /**
   * Points to the root of the component hierarchy.
   *
   * @var Page
   */
  public $root;
  /**
   * The rendering context for the current request.
   *
   * @var Context
   */
  private $context;
  /**
   * Points to the component being currently processed on the components tree.
   *
   * @var Component
   */
  private $current;
  /**
   * The name of the scalar property being currently parsed in a subtag format.
   * When set, it also indicates that the content of a scalar property is being parsed in a subtag format.
   *
   * @var string|null
   */
  private $currentScalarProperty = null;
  /**
   * The current value of the scalar property being currently parsed in a subtag format.
   *
   * @var string
   */
  private $currentScalarValue = '';
  /**
   * The ending position of the tag currently being parsed.
   *
   * @var int
   */
  private $currentTagEnd;
  /**
   * The starting position of the tag currently being parsed.
   *
   * @var int
   */
  private $currentTagStart;
  /**
   * When set, all tags are created as metadata and added as children of the specified component.
   *
   * @var Metadata
   */
  private $metadataContainer = null;
  /**
   * The ending position + 1 of the tag that was parsed previously.
   *
   * @var int
   */
  private $prevTagEnd;
  /**
   * @var string The source markup being parsed.
   */
  private $source;

  function __construct (Context $context)
  {
    $this->context = $context;
  }

  public static function isBindingExpression ($exp)
  {
    return is_string ($exp) ? strpos ($exp, '{{') !== false || strpos ($exp, '{!!') !== false : false;
  }

  /*********************************************************************************************************************
   * THE MAIN PARSING LOOP
   *******************************************************************************************************************
   *
   * @param string    $body
   * @param Component $parent
   * @param Page      $root
   * @throws ParseException
   */
  public function parse ($body, Component $parent, Page $root = null)
  {
    $pos = 0;
    if (!$root) $root = $parent;
    $this->current = $parent;
    $this->root    = $root;
    $this->source  = $body;

    while (preg_match (self::PARSE_TAG, $body, $match, PREG_OFFSET_CAPTURE, $pos)) {
      list(, list(, $start), list($term), list($tag), list($attrs), list($term2), list(, $end)
        ) = $match;

      $this->prevTagEnd      = $pos;
      $this->currentTagStart = $start;
      $this->currentTagEnd   = $end;

      if ($start > $pos)
        $this->parse_text (trim (substr ($body, $pos, $start - $pos)));

      if ($term) {
        if ($attrs) $this->parsingError ('Closing tags must not have attributes.');
        $this->parse_closingTag ($tag);
      }
      else {
        // OPEN TAG

        if ($this->currentScalarProperty)
          $this->parsingError ("Invalid tag <kbd>$tag</kbd>; components are not allowed inside the <kbd>{$this->current->getTagName()}</kbd> scalar property.");

        if (isset($this->metadataContainer) || $this->subtag_check ($tag))
          $this->parse_subtag ($tag, $attrs);

        else $this->parse_componentTag ($tag, $attrs);

        // SELF-CLOSING TAG
        if ($term2)
          $this->parse_exitTagContext ();
      }

      // LOOP: advance to the next component tag
      $pos = $end + 1;
    }

    // PROCESS REMAINING TEXT

    $nextContent = substr ($body, $pos);
    if (strlen ($nextContent))
      $this->parse_text (trim ($nextContent));
    $this->text_optimize ($parent);

    // DONE.
  }

  /*********************************************************************************************************************
   * PARSE ATTRIBUTES
   *********************************************************************************************************************
   *
   * @param string $attrStr
   * @param array  $attributes
   * @param array  $bindings
   * @param bool   $processBindings
   */
  private function parse_attributes ($attrStr, array &$attributes = null, array &$bindings = null,
                                     $processBindings = true)
  {
    $attributes = $bindings = null;
    if (!empty($attrStr)) {
      $sPos = 0;
      while (preg_match (self::PARSE_ATTRS, "$attrStr@", $match, PREG_OFFSET_CAPTURE, $sPos)) {
        list(, list($key), list($quote), list($value, $exists), list($marker, $next)) = $match;
        if ($exists < 0)
          $value = 'true';
        if ($processBindings && strpos ($value, '{') !== false)
          $bindings[renameAttribute ($key)] = $value;
        else $attributes[renameAttribute ($key)] = $value;
        $sPos = $next;
      }
    }
  }

  /*********************************************************************************************************************
   * PARSE CLOSING TAG
   *********************************************************************************************************************
   *
   * @param string $tag
   * @throws ParseException
   */
  private function parse_closingTag ($tag)
  {
    if (isset($this->currentScalarProperty)) {
      $expected = ucfirst ($this->currentScalarProperty);
      $scope    = $this->current;
    }
    else {
      $expected = $this->current->getTagName ();
      $scope    = $this->current->parent;
    }
    if ($expected != $tag) {
      $this->parsingError ("Closing tag mismatch.
<table>
  <tr><th>Found:<td class='fixed'><b>&lt;/$tag&gt;</b>
  <tr><th>Expected:<td class='fixed'><b>&lt;/$expected&gt;</b>
  <tr><th>Component in scope:<td class='fixed'><b>&lt;{$scope->getTagName()}></b><td>Class: <b>{$scope->className}</b>
" . (isset($scope->parent) ? "
  <tr><th>Scope's parent:<td><b>&lt;{$scope->parent->getTagName()}></b><td>Class: <b>{$scope->parent->className}</b>"
          : '') . "
</table>");
    }
    $this->parse_exitTagContext ();
  }

  /*********************************************************************************************************************
   * PARSE A COMPONENT TAG
   *********************************************************************************************************************
   *
   * @param string $tag
   * @param string $attrs
   * @throws ParseException
   */
  private function parse_componentTag ($tag, $attrs)
  {
    if (!$this->current->allowsChildren)
      $this->parsingError ("Neither the component <b>{$this->current->getTagName()}</b> supports children, neither the element <b>$tag</b> is a {$this->current->getTagName()} parameter.");

    /** @var Metadata|boolean $defParam */
    $this->parse_attributes ($attrs, $attributes, $bindings, true);
    $component =
      Component::create ($this->context, $this->current, $tag, $attributes, false /*TODO: support HTML components*/);

    $component->bindings = $bindings;
    $this->current->addChild ($component);
    $this->current = $component;
  }

  /*********************************************************************************************************************
   * EXIT THE CURRENT TAG CONTEXT (and go up)
   *********************************************************************************************************************
   */
  private function parse_exitTagContext ()
  {
    $current = $this->current;
    if (isset($this->currentScalarProperty)) {
      $prop = $this->currentScalarProperty;
      $v    = $this->currentScalarValue;

      $this->currentScalarProperty = null;
      $this->currentScalarValue    = '';

      if (self::isBindingExpression ($v))
        $this->current->bindings[$prop] = $v;
      else $this->current->props->$prop = $v;
    }
    else {
      $this->text_optimize ($current);
      $parent = $current->parent;
      $current->onParsingComplete (); //Note: calling this method may unset the 'parent' property (ex. with macros).

      // Check if the metadata context is being closed.
      if (isset($this->metadataContainer) && $this->current == $this->metadataContainer)
        unset ($this->metadataContainer);

      $this->current = $parent; //also discards the current scalar parameter, if that is the case.
    }
  }

  /*********************************************************************************************************************
   * PARSE A SUBTAG
   *********************************************************************************************************************
   *
   * @param string $tag
   * @param string $attrs
   * @throws ParseException
   */
  private function parse_subtag ($tag, $attrs)
  {
    $property = lcfirst ($tag);

    if (!$this->current instanceof Metadata) {

      // Move the tag's content to the corresponding slot property.

      if (!$this->current->supportsProperties)
        $this->parsingError ("The component <b>&lt;{$this->current->getTagName()}&gt;</b> does not support parameters.");

      $this->parse_attributes ($attrs, $attributes, $bindings);

      if (!$this->current->props->defines ($property)) {
        $s = '&lt;' . join ('>, &lt;', array_map ('ucfirst', $this->current->props->getPropertyNames ())) . '>';
        $this->parsingError ("The component <b>&lt;{$this->current->getTagName()}&gt;</b> ({$this->current->className})
does not support the specified parameter <b>$tag</b>.
<p>Expected: <span class='fixed'>$s</span>");
      }

      $this->subtag_createSlotContent ($property, $tag, $attributes, $bindings);
    }
    else {

      // Currently on a metadata property. Create a child component of the corresponding component.

      $this->parse_attributes ($attrs, $attributes, $bindings);
      $this->subtag_createSlotSubcontent ($property, $tag, $attributes, $bindings);
    }
  }

  /*********************************************************************************************************************
   * PARSE LITERAL TEXT
   *********************************************************************************************************************
   *
   * @param string $text
   * @throws ParseException
   */
  private function parse_text ($text)
  {
    if (!empty($text)) {

      if ($this->current->allowsChildren) {
        $sPos = 0;
        //process data bindings
        while (preg_match (self::PARSE_DATABINDINGS, $text, $match, PREG_OFFSET_CAPTURE, $sPos)) {
          list(list($brData, $brPos)) = $match;
          if ($brPos > $sPos) //append previous literal content
            $this->text_addComponent (substr ($text, $sPos, $brPos - $sPos), self::TRIM_LEFT);
          //create databound literal
          $l = strlen ($brData);
          $this->text_addComponent ($brData);
          $sPos = $brPos + $l;
        }
        //append remaining literal content
        $this->text_addComponent (substr ($text, $sPos), self::TRIM_RIGHT);
      }

      else {
        $s = $this->current->supportsProperties
          ? '&lt;' . join ('>, &lt;', array_map ('ucfirst', $this->current->props->getPropertyNames ())) . '>'
          : '';
        throw new ParseException("
<h4>You may not define literal content at this location.</h4>
<table>
  <tr><th>Component:<td class='fixed'>&lt;{$this->current->getTagName()}&gt;
  <tr><th>Expected&nbsp;tags:<td class='fixed'>$s
</table>", $this->source, $this->prevTagEnd, $this->currentTagStart);
      }

    }
  }

  private function parsingError ($msg)
  {
    throw new ParseException($msg, $this->source, $this->currentTagStart, $this->currentTagEnd);
  }

  /**
   * Checks if a tag is a subtag of the current component or it is a child component.
   *
   * @param string $tagName
   * @return bool true if it is a subtag.
   */
  private function subtag_check ($tagName)
  {
    $propName = lcfirst ($tagName);
    if ($this->current instanceof Metadata) {
      switch ($this->current->type) {
        // All descendants of a metadata property are always metadata.
        case type::metadata:
          return true;
      }
      // Descendants of a content property are children, not properties.
      return false;
    }
    // If the current component defines an property with the same name as the tag being checked, and if that property
    // supports begin specified as a tag, the tag is a subtag.
    return $this->current->supportsProperties && $this->current->props->defines ($propName, true);
  }

  private function subtag_createSlotContent ($propName, $tagName, array $attributes = null, array $bindings = null)
  {
    $component = $this->current;
    $type      = $component->props->getTypeOf ($propName);
    if ($type == type::string)
      $this->currentScalarProperty = $propName;
    else {
      $this->current = $param = new Metadata ($this->context, $tagName, $type, $attributes);
      $param->attachTo ($component);
      switch ($type) {
        case type::content:
          $component->props->$propName = $param;
          $param->bindings             = $bindings;
          break;
        case type::metadata:
          $component->props->$propName = $param;
          $param->bindings             = $bindings;
          $this->metadataContainer     = $param;
          break;
        case type::collection:
          if (isset($component->props->$propName))
            $component->props->{$propName}[] = $param;
          else $component->props->$propName = [$param];
          $param->bindings = $bindings;
          break;
        default:
          $this->parsingError ("Invalid subtag <kbd>$tagName</kbd>");
      }
    }
  }

  private function subtag_createSlotSubcontent ($name, $tagName, array $attributes = null, array $bindings = null)
  {
    $param              = $this->current;
    $this->current      = $subparam = new Metadata($this->context, $tagName, type::content, $attributes);
    $subparam->bindings = $bindings;
    $param->addChild ($subparam);
  }

  private function text_addComponent ($content, $trim = self::NO_TRIM)
  {
    if ($this->context->condenseLiterals)
      switch ($trim) {
        case self::TRIM_LEFT:
          $content = preg_replace (self::TRIM_LEFT_CONTENT, '', $content);
          break;
        case self::TRIM_RIGHT:
          $content = preg_replace (self::TRIM_RIGHT_CONTENT, '', $content);
          break;
        case self::TRIM:
          $content = preg_replace (self::TRIM_LITERAL_CONTENT, '', $content);
          break;
      }
    if (strlen ($content)) {
      if (isset($this->currentScalarProperty)) {
        $this->currentScalarValue .= $content; //Note: databinding will be taken care of later.
      }
      else {
        $v = [
          'value' => $content,
        ];
        if ($content[0] == '{') {
          $lit           = new Literal($this->context);
          $lit->bindings = $v;
        }
        else $lit = new Text ($this->context, $v);
        $this->current->addChild ($lit);
      }
    }
  }

  /**
   * Merges adjacent Literal or Text children of the specified container whenever that merge can be safely done.
   *
   * > Note: Although the parser doesn't generate redundant literals, they may occur after macro substitutions are
   * performed.
   *
   * @param Component $c The container component.
   */
  private function text_optimize (Component $c)
  {
    $o    = [];
    $prev = null;
    if ($c->hasChildren ())
      foreach ($c->getChildren () as $child) {
        if ($prev
            && ($prev instanceof Literal || $prev instanceof Text)
            && empty ($prev->bindings)
            && ($child instanceof Literal || $child instanceof Text)
            && empty($child->bindings)
            && !$prev->props->isModified ()
            && !$child->props->isModified ()
        ) {
          // safe to merge
          $prev->props->value .= $child->props->value;
          continue;
        }
        $o[]  = $child;
        $prev = $child;
      }
    $c->setChildren ($o);
  }

}