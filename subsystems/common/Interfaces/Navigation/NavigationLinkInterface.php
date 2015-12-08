<?php
namespace Selenia\Interfaces\Navigation;

use Psr\Http\Message\ServerRequestInterface;
use Selenia\Exceptions\Fault;

interface NavigationLinkInterface extends \IteratorAggregate
{
  /**
   * Are links to this location enabled?
   *
   * <p>If disabled, the links will be shown but will not be actionable.
   *
   * <p>Default: **`true`**
   *
   * > ##### Dynamic evaluation
   * > Setting this property to a callback will make it dynamic and lazily evaluated.
   * > Reading the property (calling the method without an argument) will invoke the callback and return the resulting
   * > value.
   *
   * @param bool|callable $enabled [optional]
   * @return $this|bool $this if an argument is given, the property's value otherwise.
   */
  function enabled ($enabled = null);

  /**
   * Returns the next level of navigation links, suitable for display on a navigation menu.
   * Recursively iterating each link's `getMenu()` will yield the full navigation tree.
   * @return \Iterator
   */
  function getMenu ();

  /**
   * The menu item's icon.
   *
   * @param string $icon [optional] A space-separated list of CSS class selectors. Ex: 'fa fa-home'
   * @return $this|string $this if an argument is given, the property's value otherwise.
   */
  function icon ($icon = null);

  /**
   * A unique name that identifies the link.
   *
   * <p>The ID allows you to reference the link elsewhere, for instance, when generating URLs for it.
   *
   * <p>Default: **`null`** (no ID)
   *
   * @param string $id [optional]
   * @return $this|string $this if an argument is given, the property's value otherwise.
   * @throws \InvalidArgumentException If any child has a duplicate ID on the current navigation tree.
   */
  function id ($id = null);

  /**
   * Indicates if the link is actually enabled, taking into account `enabled()`, and missing parameters on the URL.
   * @return bool
   * @throws Fault Faults::REQUEST_NOT_SET
   */
  function isActuallyEnabled ();

  /**
   * Indicates if the link is actually visible, taking into account `visible()`, `visibleIfUnavailable()` and missing
   * parameters on the URL.
   * @return bool
   * @throws Fault Faults::REQUEST_NOT_SET
   */
  function isActuallyVisible ();

  /**
   * Indicates if the link is a group pseudo-link that was created by a {@see NavigationInterface::group()} call.
   * @return bool
   */
  function isGroup ();

  /**
   * This link's navigation map (a map of child links).
   *
   * @param NavigationLinkInterface[]|\Traversable|callable $navigationMap An iterable value.
   * @return $this|NavigationLinkInterface[]|\Traversable|callable         $this if an argument is given, the
   *                                                                       property's value otherwise.
   *                                                                       <p>You should call <kbd>iterator($value)
   *                                                                       </kbd> on the returned instance to get an
   *                                                                       iterator that you can use to iterate the
   *                                                                       list of links.
   */
  function links ($navigationMap = null);

  /**
   * Merges a navigation map with this link's map.
   *
   * @param NavigationLinkInterface[]|\Traversable|callable $navigationMap An iterable value.
   * @return $this
   */
  function merge ($navigationMap);

  /**
   * The link's parent link or, if this is a root link, the navigation object.
   *
   * @param NavigationLinkInterface|NavigationInterface $parent [optional]
   * @return $this|NavigationLinkInterface $this if an argument is given, the property's value otherwise.
   */
  function parent (NavigationLinkInterface $parent = null);

  /**
   * Associates an HTTP server request with this link, to enable URL parameters resolution.
   *
   * <p>This is only done for the root link of a navigation hierarchy, all other links will read from their
   * parent until a link with a set value is reached.
   *
   * @param ServerRequestInterface $request [optional]
   * @return $this|bool $this if an argument is given, the property's value otherwise.
   */
  function request (ServerRequestInterface $request = null);

  /**
   * The page title.
   *
   * <p>It may be displayed:
   * - on the browser's title bar and navigation history;
   * - on menus and navigation breadcrumbs.
   *
   * <p>Default: **`''`** (no title)
   *
   * > ##### Dynamic evaluation
   * > Setting this property to a callback will make it dynamic and lazily evaluated.
   * > Reading the property (calling the method without an argument) will invoke the callback and return the resulting
   * > value.
   *
   * @param string|callable $title [optional]
   * @return $this|string $this if an argument is given, the property's value otherwise.
   */
  function title ($title = null);

  /**
   * The link's full URL or complete URL path.
   *
   * <p>It can be a path relative to the application's base path, an absolute path or a full URL address.
   *
   * <p>Example: **`'admin/users'`** (which is relative to the app's base path)
   *
   * > <p>**Warning:** unlike other link properties, the value read back from this property after it is explicitly set
   * will frequently differ from the set value.
   *
   * <p>If the `url` property is not explicitly set (defaults to `null`), when read, its value is automatically
   * computed
   * from concatenating all URLs (static or dynamic) from all links on the trail that begins on the home/root link and
   * that ends on this link.
   *
   * <p>The computed value is cached when read for the first time, and subsequent reads will return the cached value
   * (unless the final value is `null`, which is not cached).
   *
   * <p>If any link on the trail defines an absolute path or a full URL, it will be used for computing the subsequent
   * links' URLs. If more than one absolute/full URL exist on the trail, the last one overrides previous ones.
   *
   * > ##### Dynamic evaluation
   * > Setting this property to a callback will make it dynamic and lazily evaluated.
   * > Reading the property (calling the method without an argument) will invoke the callback and return the resulting
   * > value.
   *
   * @param string|callable $url [optional]
   * @return $this|string|null $this if an argument is given, the property's value otherwise.
   *                             If null, the link is non-navigable (it has no URL).
   */
  function url ($url = null);

  /**
   * Are links to this location displayed?
   *
   * <p>If `false`, the links will not be shown on menus, but they'll still be shown in navigation breadcrumbs.
   *
   * <p>Default: **`true`**
   *
   * > ##### Dynamic evaluation
   * > Setting this property to a callback will make it dynamic and lazily evaluated.
   * > Reading the property (calling the method without an argument) will invoke the callback and return the resulting
   * > value.
   *
   * @param bool|callable $visible [optional]
   * @return $this|bool $this if an argument is given, the property's value otherwise.
   */
  function visible ($visible = null);

  /**
   * Are links to this location displayed even if the link's URL cannot be generated due to missing route parameters?
   *
   * <p>If `true`, the link will be shown on menus, but it'll be disabled (and greyed out) until the current route
   * provides all the parameters required for generating a valid URL for this link.
   *
   * <p>Enabling this setting can be used to show the user that there are more links available, even if the user cannot
   * select them until an additional selection is performed somehwere on those link's parent page.
   *
   * ###### Example
   *
   * For the following menu:
   *
   * - Authors
   *     - Books
   *     - Publications
   *
   * The children of the `Authors` menu item will only become enabled when the user selects an author on the `Authors`
   * page and the corresponding author ID becomes available on the URL.
   *
   * <p>Default: **`false`**
   *
   * @param bool $visible [optional]
   * @return $this|bool $this if an argument is given, the property's value otherwise.
   */
  function visibleIfUnavailable ($visible = null);

}