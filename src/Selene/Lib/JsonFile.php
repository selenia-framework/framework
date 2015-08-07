<?php
namespace Selene\Lib;

class JsonFile
{
  public $data;

  protected $assoc;
  protected $path;
  protected $useIncludePath;

  function __construct ($path, $assoc = false, $useIncludePath = false)
  {
    $this->path           = $path;
    $this->assoc          = $assoc;
    $this->useIncludePath = $useIncludePath;
  }

  function __toString ()
  {
    return $this->json();
  }

  function assign ($data)
  {
    $this->data = $data;
    return $this;
  }

  function exists ()
  {
    return fileExists ($this->path, $this->useIncludePath);
  }

  function get ($jsonPath)
  {
    return getAt ($this->data, $jsonPath);
  }

  function json ($pretty = true)
  {
    $json =
      json_encode ($this->data, ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $pretty
      ? preg_replace_callback ('/^ +/m', function ($m) { return str_repeat (' ', strlen ($m[0]) / 2); }, $json)
      : $json;
  }

  function load ()
  {
    $json       = loadFile ($this->path, $this->useIncludePath);
    $this->data = $json ? json_decode ($json, $this->assoc) : false;
    return $this;
  }

  function save ($pretty = true)
  {
    file_put_contents ($this->path, $this->json ($pretty),
      ($this->useIncludePath ? FILE_USE_INCLUDE_PATH : 0) | LOCK_EX);
    return $this;
  }

  function set ($jsonPath, $value)
  {
    setAt ($this->data, $jsonPath, $value, $this->assoc);
  }

}