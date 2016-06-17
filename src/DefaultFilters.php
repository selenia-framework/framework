<?php
namespace Electro;

/**
 * Predefined filters provided by the framework.
 *
 * ><p>**Note:** the `filter_` prefix allows filter functions to have any name without conflicting with PHP reserved
 * keywords.
 */
class DefaultFilters
{
  private $app;

  function __construct (Application $app)
  {
    $this->app = $app;
  }

  /**
   * Alternating values for iterator indexes (0 or 1); allows for specific formatting of odd/even rows.
   *
   * @param int $v
   * @return int
   */
  function filter_alt ($v)
  {
    return $v % 2;
  }

  /**
   * @param string $v
   * @return string
   */
  function filter_currency ($v)
  {
    return formatMoney ($v) . ' €';
  }

  /**
   * @param string $v
   * @return string
   */
  function filter_datePart ($v)
  {
    return explode (' ', $v) [0];
  }

  /**
   * Returns the same value if it's not null, false or an empty string, otherwise returns the specified default value.
   *
   * @param mixed  $v
   * @param string $default
   * @return string
   */
  function filter_else ($v, $default = '')
  {
    return isset ($v) && $v !== '' && $v !== false ? $v : $default;
  }

  /**
   * Returns `true` if a number is even.
   *
   * @param int $v
   * @return boolean
   */
  function filter_even ($v)
  {
    return $v % 2 == 0;
  }

  /**
   * @param string $v
   * @return string
   */
  function filter_fileURL ($v)
  {
    return $this->app->getFileDownloadURI ($v);
  }

  /**
   * @param mixed $v
   * @return string
   */
  function filter_json ($v)
  {
    return json_encode ($v, JSON_PRETTY_PRINT);
  }

  /**
   * Converts line breaks to `<br>` tags.
   *
   * @param $v
   * @return string
   */
  function filter_nl2br ($v)
  {
    return nl2br ($v);
  }

  /**
   * Returns `true` if a number is odd.
   *
   * @param int $v
   * @return boolean
   */
  function filter_odd ($v)
  {
    return $v % 2 == 1;
  }

  /**
   * The ordinal value of an iterator index.
   *
   * @param int $v
   * @return int
   */
  function filter_ord ($v)
  {
    return $v + 1;
  }

  /**
   * @param mixed  $v
   * @param string $true
   * @param string $false
   * @return string
   */
  function filter_then ($v, $true = '', $false = '')
  {
    return $v ? $true : $false;
  }

  /**
   * @param string $v
   * @return string
   */
  function filter_timePart ($v)
  {
    return explode (' ', $v) [1];
  }

  /**
   * @param mixed $v
   * @return string
   */
  function filter_type ($v)
  {
    return typeOf ($v);
  }

}
