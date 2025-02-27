<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;


/**
 * The dependency injection container default implementation.
 */
class Container
{
	use Nette\SmartObject;

	/** @var array  user parameters */
	public $parameters = [];

	/** @var string[]  services name => type (complete list of available services) */
	protected $types = [];

	/** @var string[]  alias => service name */
	protected $aliases = [];

	/** @var array[]  tag name => service name => tag value */
	protected $tags = [];

	/** @var array[]  type => level => services */
	protected $wiring = [];

	/** @var object[]  service name => instance */
	private $instances = [];

	/** @var array circular reference detector */
	private $creating;

	/** @var array */
	private $methods;


	public function __construct(array $params = [])
	{
		$this->parameters = $params;
		$this->methods = array_flip(get_class_methods($this));
	}


	public function getParameters(): array
	{
		return $this->parameters;
	}


	/**
	 * Adds the service to the container.
	 * @param  object  $service  service or its factory
	 * @return static
	 */
	public function addService(string $name, $service)
	{
		$name = $this->aliases[$name] ?? $name;
		if (isset($this->instances[$name])) {
			throw new Nette\InvalidStateException("Service '$name' already exists.");

		} elseif (!is_object($service)) {
			throw new Nette\InvalidArgumentException(sprintf("Service '%s' must be a object, %s given.", $name, gettype($service)));
		}

		$type = $service instanceof \Closure
			? (string) (new \ReflectionFunction($service))->getReturnType()
			: get_class($service);

		if (!isset($this->methods[self::getMethodName($name)])) {
			$this->types[$name] = $type;

		} elseif (($expectedType = $this->getServiceType($name)) && !is_a($type, $expectedType, true)) {
			throw new Nette\InvalidArgumentException("Service '$name' must be instance of $expectedType, " . ($type ? "$type given." : 'add typehint to closure.'));
		}

		if ($service instanceof \Closure) {
			$this->methods[self::getMethodName($name)] = $service;
			$this->types[$name] = $type;
		} else {
			$this->instances[$name] = $service;
		}

		return $this;
	}


	/**
	 * Removes the service from the container.
	 */
	public function removeService(string $name): void
	{
		$name = $this->aliases[$name] ?? $name;
		unset($this->instances[$name]);
	}


	/**
	 * Gets the service object by name.
	 * @return object
	 * @throws MissingServiceException
	 */
	public function getService(string $name)
	{
		if (!isset($this->instances[$name])) {
			if (isset($this->aliases[$name])) {
				return $this->getService($this->aliases[$name]);
			}
			$this->instances[$name] = $this->createService($name);
		}
		return $this->instances[$name];
	}


	/**
	 * Gets the service type by name.
	 * @throws MissingServiceException
	 */
	public function getServiceType(string $name): string
	{
		$method = self::getMethodName($name);
		if (isset($this->aliases[$name])) {
			return $this->getServiceType($this->aliases[$name]);

		} elseif (isset($this->types[$name])) {
			return $this->types[$name];

		} elseif (isset($this->methods[$method])) {
			return (string) (new \ReflectionMethod($this, $method))->getReturnType();

		} else {
			throw new MissingServiceException("Service '$name' not found.");
		}
	}


	/**
	 * Does the service exist?
	 */
	public function hasService(string $name): bool
	{
		$name = $this->aliases[$name] ?? $name;
		return isset($this->methods[self::getMethodName($name)]) || isset($this->instances[$name]);
	}


	/**
	 * Is the service created?
	 */
	public function isCreated(string $name): bool
	{
		if (!$this->hasService($name)) {
			throw new MissingServiceException("Service '$name' not found.");
		}
		$name = $this->aliases[$name] ?? $name;
		return isset($this->instances[$name]);
	}


	/**
	 * Creates new instance of the service.
	 * @return object
	 * @throws MissingServiceException
	 */
	public function createService(string $name, array $args = [])
	{
		$name = $this->aliases[$name] ?? $name;
		$method = self::getMethodName($name);
		$cb = $this->methods[$method] ?? null;
		if (isset($this->creating[$name])) {
			throw new Nette\InvalidStateException(sprintf('Circular reference detected for services: %s.', implode(', ', array_keys($this->creating))));

		} elseif ($cb === null) {
			throw new MissingServiceException("Service '$name' not found.");
		}

		try {
			$this->creating[$name] = true;
			$service = $cb instanceof \Closure ? $cb(...$args) : $this->$method(...$args);

		} finally {
			unset($this->creating[$name]);
		}

		if (!is_object($service)) {
			throw new Nette\UnexpectedValueException("Unable to create service '$name', value returned by " . ($cb instanceof \Closure ? 'closure' : "method $method()") . ' is not object.');
		}

		return $service;
	}


	/**
	 * Resolves service by type.
	 * @param  bool  $throw  exception if service doesn't exist?
	 * @return object|null  service
	 * @throws MissingServiceException
	 */
	public function getByType(string $type, bool $throw = true)
	{
		$type = Helpers::normalizeClass($type);
		if (!empty($this->wiring[$type][0])) {
			if (count($names = $this->wiring[$type][0]) === 1) {
				return $this->getService($names[0]);
			}
			natsort($names);
			throw new MissingServiceException("Multiple services of type $type found: " . implode(', ', $names) . '.');

		} elseif ($throw) {
			throw new MissingServiceException("Service of type $type not found.");
		}
		return null;
	}


	/**
	 * Gets the autowired service names of the specified type.
	 * @return string[]
	 * @internal
	 */
	public function findAutowired(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		return array_merge($this->wiring[$type][0] ?? [], $this->wiring[$type][1] ?? []);
	}


	/**
	 * Gets the service names of the specified type.
	 * @return string[]
	 */
	public function findByType(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		return empty($this->wiring[$type])
			? []
			: array_merge(...array_values($this->wiring[$type]));
	}


	/**
	 * Gets the service names of the specified tag.
	 * @return array of [service name => tag attributes]
	 */
	public function findByTag(string $tag): array
	{
		return $this->tags[$tag] ?? [];
	}


	/********************* autowiring ****************d*g**/


	/**
	 * Creates new instance using autowiring.
	 * @return object
	 * @throws Nette\InvalidArgumentException
	 */
	public function createInstance(string $class, array $args = [])
	{
		$rc = new \ReflectionClass($class);
		if (!$rc->isInstantiable()) {
			throw new ServiceCreationException("Class $class is not instantiable.");

		} elseif ($constructor = $rc->getConstructor()) {
			return $rc->newInstanceArgs(Resolver::autowireArguments($constructor, $args, $this));

		} elseif ($args) {
			throw new ServiceCreationException("Unable to pass arguments, class $class has no constructor.");
		}
		return new $class;
	}


	/**
	 * Calls all methods starting with with "inject" using autowiring.
	 * @param  object  $service
	 */
	public function callInjects($service): void
	{
		Extensions\InjectExtension::callInjects($this, $service);
	}


	/**
	 * Calls method using autowiring.
	 * @return mixed
	 */
	public function callMethod(callable $function, array $args = [])
	{
		return $function(...Resolver::autowireArguments(Nette\Utils\Callback::toReflection($function), $args, $this));
	}


	public static function getMethodName(string $name): string
	{
		if ($name === '') {
			throw new Nette\InvalidArgumentException('Service name must be a non-empty string.');
		}
		return 'createService' . str_replace('.', '__', ucfirst($name));
	}
}
