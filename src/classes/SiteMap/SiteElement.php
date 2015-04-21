<?php
class SiteElement extends Object {
  const ACTIVE = 'active';

  public $name;
  public $title;
  public $subtitle;
  public $URI;
  public $URIAlias;
  public $URL; //autoset if $URI is set
  public $subnavURI;
  public $subnavURL; //autoset if $subnavURI is set
  public $onMenu = true;
  public $pages = null;
  public $isIndex = false;
  public $indexURL;      //autoset if isIndex is set
  public $autoView;
  public $autoController;
  /**
   * CSS class name(s) for menu icon.
   * @var string
   */
  public $icon;

  public $parent = null; //internal use
  public $URI_regexp;    //internal use
  public $URIAlias_regexp;    //internal use
  public $indexTitle;    //internal use
  /**
   * Indicates if the element is highlighted on the main menu.
   * @var boolean
   */
  public $selected = false; //internal use
  /**
   * Indicates if the element matches the current URI.
   * @var boolean
   */
  public $matches = false; //internal use
  /**
   * Automatically set to true when any sub-navigation links are available under this page.
   * @var bool
   */
  public $hasSubNav = false;


  public function getTypes() {
    return array(
      'name'      => 'string',
      'title'     => 'string',
      'subtitle'  => 'string',
      'URI'       => 'string',
      'URIAlias'  => 'string',
      'URL'       => 'string',
      'subnavURI' => 'string',
      'subnavURL' => 'string',
      'onMenu'    => 'boolean',
      'pages'     => 'array',
      'isIndex'   => 'boolean',
      'indexURL'  => 'string',
      'autoView'       => 'boolean',
      'autoController' => 'boolean',
      'icon'      => 'string'
    );
  }

  public function __construct(array &$init = null) {
    parent::__construct($init);
    if (isset($init))
      $this->preinit();
  }

  public function preinit() {
    global $application;
    if (!isset($this->URL)) {
      if (isset($this->URI))
        $this->URL = "$application->URI/$this->URI";
      else $this->URL = 'javascript:nop()';
    }
    if (!isset($this->subnavURL)) {
      if (isset($this->subnavURI))
        $this->subnavURL = "$application->URI/$this->subnavURI";
      else $this->subnavURL = 'javascript:nop()';
    }
    if (isset($this->URI))
      $this->URI_regexp = preg_replace('!\{.*?}!','([^/&]*)',$this->URI);
    else $this->URI_regexp = '<unmatchable>';
    if (isset($this->URIAlias))
      $this->URIAlias_regexp = preg_replace('!\{.*?}!','([^/&]*)',$this->URIAlias);
    else $this->URIAlias_regexp = '<unmatchable>';
  }

  public function getTitle() {
    return isset($this->title) ? $this->title : (isset($this->subtitle) ? $this->subtitle : (isset($this->parent) ? $this->parent->getTitle() : ''));
  }

  public function getSubtitle($first = true) {
    if (isset($this->subtitle))
      return $this->subtitle;
    return $this->title;
    /*
    if (isset($this->parent)) {
      $subtitle = $this->parent->getSubtitle(false);
      if (strlen($subtitle))
        return $subtitle;
    }
    return $first ? $this->getDefaultSubtitle() : '';
     */
  }

  protected function getDefaultSubtitle() {
    return isset($this->parent) ? $this->parent->getDefaultSubtitle() : '';
  }

  public function init($parent) {
    $this->parent = $parent;
    if (empty($this->indexURL)) {
      $index = $this->getIndex();
      if (isset($index)) {
        $this->indexTitle = $index->getSubtitle();
        $this->indexURL = $index->URL;
      }
    }
    if (isset($this->pages))
      foreach ($this->pages as $page) {
        /** @var SiteElement $page */
        $page->init($this);
        if ($page->onMenu)
          $this->hasSubNav = true;
      }
  }

  protected function matchesMyURI($URI) {
    return preg_match("!^$this->URI_regexp(?:$|&)!",$URI) > 0;
  }

  protected function matchesMyURIAlias($URI) {
    return preg_match("!^$this->URIAlias_regexp(?:$|&)!",$URI) > 0;
  }

  protected function matchesURI($URI) {
    return $this->matchesMyURI($URI) || $this->matchesMyURIAlias($URI);
  }

  public function searchFor($URI,$options = 0) {
    if ($this->matchesURI($URI)) {
      $this->selected = $this->matches = true;
      return $this;
    }
    if (isset($this->pages)) {
      foreach ($this->pages as $subpage) {
        $result = $subpage->searchFor($URI,$options);
        if (isset($result)) {
          $this->selected = true;
          return $result;
        }
      }
    }
    return null;
  }

  public function getIndex() {
    if ($this->isIndex)
      return $this;
    if (is_a($this->parent,'SiteElement'))
      return $this->parent->getIndex();
    return null;
  }

  /**
   *
   * @global Application $application
   * @global ModuleLoader $loader
   * @return array
   */
  public function getURIParams() {
    global $application,$loader;
    $URI = $loader->virtualURI;
    $result = array();
    $count = preg_match("!^$this->URI_regexp(?:$|&|/)!",urldecode($URI),$URIValues);
    if ($count)
      $uriexp = $this->URI;
    else {
      $count = preg_match("!^$this->URIAlias_regexp(?:$|&|/)!",urldecode($URI),$URIValues);
      if ($count)
        $uriexp = $this->URIAlias;
      else $uriexp = '';
    }
    if (preg_match_all('!\{(.*?)}!',$uriexp,$matches)) {
      foreach ($matches[1] as $i=>$field)
        if (count($URIValues) > $i)
          $result[$field] = get($URIValues,$i + 1);
        else {
          if ($application->debugMode) {
            $x = "URIValues:\n".print_r($URIValues,true);
            $x.= "URIParams:\n".print_r($result,true);
            throw new FatalException("No match found for parameter <b>$field</b> on the URI <b>$URI</b> for pattern <b>$uriexp</b><p>URI parameters found:<p><pre>$x");
          }
        }
    }
    return $result;
  }

  public function evalURI($URIParams = null,$ignoreMissing = false,$URI = null) {
    if (is_null($URIParams))
      $URIParams = $this->getURIParams();
    if (is_null($URI))
      $URI = $this->URI;
    try {
      return preg_replace_callback('!\{(.*?)}!',function ($args) use ($URIParams,$ignoreMissing) { return self::fillURIParam($args[1],$URIParams,$ignoreMissing);},$URI);
    }
    catch (Exception $e) {
      $x = print_r($URIParams,true);
      throw new FatalException("URI parameter value for <b>{$e->getMessage()}</b> was not found on the URI parameters:<br><pre>$x<br>URI: <b>$URI</b>");
    }
  }

  public function getPresetParameters() {
    if (!empty($this->preset)) {
      $presetParams = array();
      $paramList = explode('&',$this->preset);
      preg_match('!'.$this->URI_regexp.'!',$_SERVER['REQUEST_URI'],$matches);
      $URIParams = $this->getURIParams();
      foreach ($paramList as $x) {
        list ($k,$v) = explode('=',$x);
        if ($v[0] == '{') {
          $i = trim($v,'{}');
          $v = get($matches,$i);
          if (!isset($v))
            $v = get($URIParams,$i);
          if (!isset($v))
            throw new ConfigException("On the preset <b>$this->preset</b>, the key <b>$k</b> was not found on the URI.");
        }
        $presetParams[$k] = $v;
      }
      return $presetParams;
    }
    return null;
  }

  private static function fillURIParam($match,$data,$ignoreMissing) {
    if (isset($data[$match]))
      return $data[$match];
    else if ($ignoreMissing)
      return '';
    throw new Exception($match);
  }

}

