<?php
namespace Selenia\Interfaces;

interface InjectorInterface
{
  const I_BINDINGS = 1;
  const I_DELEGATES = 2;
  const I_PREPARES = 4;
  const I_ALIASES = 8;
  const I_SHARES = 16;
  const I_ALL = 17;

  /**
   * Define an alias for all occurrences of a given typehint
   *
   * Use this method to specify implementation classes for interface and abstract class typehints.
   *
   * @param string $original The typehint to replace
   * @param string $alias    The implementation name
   * @return self
   */
  function alias ($original, $alias);

  /**
   * Provision an Executable instance from any valid callable or class::method string
   *
   * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
   * @return \Auryn\Executable
   */
  function buildExecutable ($callableOrMethodStr);

  /**
   * Define instantiation directives for the specified class
   *
   * @param string $name The class (or alias) whose constructor arguments we wish to define
   * @param array  $args An array mapping parameter names to values/instructions
   * @return self
   */
  function define ($name, array $args);

  /**
   * Assign a global default value for all parameters named $paramName
   *
   * Global parameter definitions are only used for parameters with no typehint, pre-defined or
   * call-time definition.
   *
   * @param string $paramName The parameter name for which this value applies
   * @param mixed  $value     The value to inject for this parameter name
   * @return self
   */
  function defineParam ($paramName, $value);

  /**
   * Delegate the creation of $name instances to the specified callable
   *
   * @param string $name
   * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
   * @return self
   */
  function delegate ($name, $callableOrMethodStr);

  /**
   * Invoke the specified callable or class::method string, provisioning dependencies along the way
   *
   * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
   * @param array $args                Optional array specifying params with which to invoke the provisioned callable
   * @throws \Auryn\InjectionException
   * @return mixed Returns the invocation result returned from calling the generated executable
   */
  function execute ($callableOrMethodStr, array $args = []);

  /**
   * Retrieve stored data for the specified definition type
   *
   * Exposes introspection of existing binds/delegates/shares/etc for decoration and composition.
   *
   * @param string $nameFilter An optional class name filter
   * @param int    $typeFilter A bitmask of <kbd>InjectorInterface::I_*</kbd> type constant flags
   * @return array
   */
  function inspect ($nameFilter = null, $typeFilter = null);

  /**
   * Instantiate/provision a class instance
   *
   * @param string $name
   * @param array  $args
   * @return mixed
   */
  public function make ($name, array $args = []);

  /**
   * Register a prepare callable to modify/prepare objects of type $name after instantiation
   *
   * Any callable or provisionable invokable may be specified. Preparers are passed two
   * arguments: the instantiated object to be mutated and the current Injector instance.
   *
   * @param string $name
   * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
   * @return self
   */
  function prepare ($name, $callableOrMethodStr);

  /**
   * Share the specified class/instance across the Injector context
   *
   * @param mixed $nameOrInstance The class or object to share
   * @return self
   */
  function share ($nameOrInstance);

}
