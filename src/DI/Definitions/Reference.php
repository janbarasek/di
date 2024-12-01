<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette\DI;



/**
 * Reference to service. Either by name or by type or reference to the 'self' service.
 */
final class Reference extends Expression
{
	public const Self = 'self';

	/** @deprecated use Reference::Self */
	public const SELF = self::Self;

	private string $value;


	public static function fromType(string $value): static
	{
		if (!str_contains($value, '\\')) {
			$value = '\\' . $value;
		}

		return new static($value);
	}


	public function __construct(string $value)
	{
		$this->value = $value;
	}


	public function getValue(): string
	{
		return $this->value;
	}


	public function isName(): bool
	{
		return !str_contains($this->value, '\\') && $this->value !== self::Self;
	}


	public function isType(): bool
	{
		return str_contains($this->value, '\\');
	}


	public function isSelf(): bool
	{
		return $this->value === self::Self;
	}


	public function resolveType(DI\Resolver $resolver): ?string
	{
		if ($this->isSelf()) {
			return $resolver->getCurrentService(type: true);

		} elseif ($this->isType()) {
			return ltrim($this->value, '\\');
		}

		$def = $resolver->getContainerBuilder()->getDefinition($this->value);
		if (!$def->getType()) {
			$resolver->resolveDefinition($def);
		}

		return $def->getType();
	}


	public function generateCode(DI\PhpGenerator $generator): string
	{
		return match (true) {
			$this->isSelf() => '$service',
			$this->value === DI\ContainerBuilder::ThisContainer => '$this',
			default => $generator->formatPhp('$this->getService(?)', [$this->value]),
		};
	}
}
