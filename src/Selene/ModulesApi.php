<?php
namespace Selene;
use Flow\FilesystemFlow;
use Selene\Exceptions\ConfigException;
use Selene\Lib\JsonFile;
use Selene\Traits\Singleton;
use SplFileInfo;

/**
 * Provides an API for querying module information.
 */
class ModulesApi
{
  use Singleton;

  private $app;

  function __construct (Application $app)
  {
    $this->app = $app;
  }

  /**
   * @throws ConfigException
   */
  function bootModules ()
  {
    global $application; // Used by the loaded bootstrap.php
    $manifest = $this->getManifest ();
    foreach ($manifest->modules as $module)
      includeFile ("{$module->path}/bootstrap.php");
  }

  function buildManifest ()
  {
    return (object)[
      'modules' => $this->get ()->modules (),
    ];
  }

  function getManifest ()
  {
    $json = new JsonFile ("{$this->app->modulesPath}/manifest.json");
    return $json->exists ()
      ? $json->load ()->data
      : $json->assign ($this->buildManifest ())->save ()->data;
  }

  /**
   * Checks if a module is installed, either as a plugin or as a local module, by verifying its existence on disk.
   * @param string $moduleName `vendor-name/package-name` syntax.
   * @return bool
   */
  function isInstalled ($moduleName)
  {
    return $this->pathOf ($moduleName) !== false;
  }

  /**
   * Checks if the installed module with the given name is a plugin.
   * @param string $moduleName `vendor-name/package-name` syntax.
   * @return bool
   */
  function isPlugin ($moduleName)
  {
    return file_exists ("{$this->app->pluginModulesPath}/$moduleName");
  }

  /**
   * Gets the names of all local (non-plugin) modules.
   * @return string[] Names in `vendor-name/package-name` syntax.
   */
  function projectModuleNames ()
  {
    return array_getColumn ($this->projectModules (), 'name');
  }

  function projectModules ()
  {
    return FilesystemFlow
      ::from ("{$this->app->baseDirectory}/{$this->app->modulesPath}")
      ->onlyDirectories ()
      ->expand (function (SplFileInfo $dirInfo) {
        return FilesystemFlow
          ::from ($dirInfo)
          ->onlyDirectories ()
          ->map (function (SplFileInfo $subDirInfo) use ($dirInfo) {
            return (object)[
              'name' => $dirInfo->getFilename () . '/' . $subDirInfo->getFilename (),
              'path' => $subDirInfo->getPathname (),
            ];
          });
      })
      ->all ();
  }

  /**
   * Converts a module name in `vendor-name/package-name` form to a valid PSR-4 namespace.
   * @param string $moduleName
   * @return string
   */
  function moduleNameToNamespace ($moduleName)
  {
    $o = explode ('/', $moduleName);
    if (count ($o) != 2)
      throw new \RuntimeException ("Invalid module name");
    list ($vendor, $module) = $o;
    $namespace1 = ucfirst (dehyphenate ($vendor));
    $namespace2 = ucfirst (dehyphenate ($module));

    return "$namespace1\\$namespace2";
  }

  /**
   * Gets the names of all installed modules.
   * @return string[] Names in `vendor-name/package-name` syntax.
   */
  function moduleNames ()
  {
    $modules = array_merge ($this->pluginNames (), $this->projectModuleNames ());
    sort ($modules);
    return $modules;
  }

  /**
   * Returns information about all installed modules.
   *
   * Each module record defines:<dl>
   * <dt>name <dd>The module name (vendor/package).
   * <dt>path <dd>The full path of the module's root directory.
   * <dt>description <dd>A short one-liner describing the module.
   * <dt>type <dd>The type of module: Plugin | Project module.
   * </dl>
   * @return \StdClass[]
   */
  function modules ()
  {
    $modules = array_merge ($this->plugins (), $this->projectModules ());
    return flow ($modules)->map (function ($module) {
      $composerJson        = new JsonFile ("$module->path/composer.json");
      $module->description = $composerJson->exists ()
        ? $composerJson->load ()->get ('description')
        : '';
      $module->type        = $this->isPlugin ($module->name) ? 'Plugin' : 'Project module';
      return $module;
    })->all ();
  }

  /**
   * Returns the directory path where the specified module is installed.
   * @param string $moduleName `vendor-name/package-name` syntax.
   * @return bool|string The path or `false` if the module is not installed.
   */
  function pathOf ($moduleName)
  {
    $path = "{$this->app->pluginModulesPath}/$moduleName";
    if (file_exists ($path)) return $path;
    $path = "{$this->app->modulesPath}/$moduleName";
    if (file_exists ($path)) return $path;
    return false;
  }

  /**
   * Gets the names of all modules installed as plugins.
   * @return string[] Names in `vendor-name/package-name` syntax.
   */
  function pluginNames ()
  {
    return array_getColumn ($this->plugins (), 'name');
  }

  function plugins ()
  {
    return FilesystemFlow
      ::from ("{$this->app->baseDirectory}/{$this->app->pluginModulesPath}")
      ->onlyDirectories ()
      ->expand (function (SplFileInfo $dirInfo) {
        return FilesystemFlow
          ::from ($dirInfo)
          ->onlyDirectories ()
          ->map (function (SplFileInfo $subDirInfo) use ($dirInfo) {
            return (object)[
              'name' => $dirInfo->getFilename () . '/' . $subDirInfo->getFilename (),
              'path' => $subDirInfo->getPathname (),
            ];
          });
      })
      ->all ();
  }

}