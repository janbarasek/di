<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * Definition used by ContainerBuilder.
 *
 * @author     David Grudl
 */
class ServiceDefinition extends Nette\Object
{
	/** @var string  class or interface name */
	public $class;

	/** @var Statement */
	public $factory;

	/** @var Statement[] */
	public $setup = array();

	/** @var array */
	public $parameters = array();

	/** @var array */
	public $tags = array();

	/** @var bool */
	public $autowired = TRUE;

	/** @var bool */
	public $inject = TRUE;

	/** @var string  interface name */
	public $implement;

	/** @internal @var string  create | get */
	public $implementType;


	public function setClass($class, array $args = array())
	{
		$this->class = $class;
		if ($args) {
			$this->setFactory($class, $args);
		}
		return $this;
	}


	public function setFactory($factory, array $args = array())
	{
		$this->factory = new Statement($factory, $args);
		return $this;
	}


	public function setArguments(array $args = array())
	{
		if ($this->factory) {
			$this->factory->arguments = $args;
		} else {
			$this->setClass($this->class, $args);
		}
		return $this;
	}


	public function addSetup($target, array $args = array())
	{
		$this->setup[] = new Statement($target, $args);
		return $this;
	}


	public function setParameters(array $params)
	{
		$this->parameters = $params;
		return $this;
	}


	public function addTag($tag, $attrs = TRUE)
	{
		$this->tags[$tag] = $attrs;
		return $this;
	}


	public function setAutowired($on)
	{
		$this->autowired = (bool) $on;
		return $this;
	}


	/** @deprecated */
	public function setShared($on)
	{
		trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
		$this->autowired = $on ? $this->autowired : FALSE;
		return $this;
	}


	/** @deprecated */
	public function isShared()
	{
		trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
	}


	public function setInject($on)
	{
		$this->inject = (bool) $on;
		return $this;
	}


	public function setImplement($implement)
	{
		$this->implement = $implement;
		return $this;
	}

}
