<?php

/**
 * Lightweight shims for missing Symfony VarDumper classes.
 *
 * This is a fallback so the app can run even if the real
 * `symfony/var-dumper` package is not present in vendor.
 *
 * NOTE: For proper debugging, you should still install the
 * official package via Composer when your environment allows it.
 */

namespace Symfony\Component\VarDumper\Cloner;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

class Data implements Countable, IteratorAggregate
{
    public mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function count(): int
    {
        return is_countable($this->value) ? count($this->value) : 0;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
}

class Stub
{
}

class AbstractCloner
{
    public static array $defaultCasters = [];

    public function addCasters(array $casters): static
    {
        // No-op in shim; just keep method chainable.
        return $this;
    }

    public function cloneVar(mixed $var): Data
    {
        // Wrap value in a simple Data wrapper for type compatibility.
        return new Data($var);
    }
}

class VarCloner extends AbstractCloner
{
}

namespace Symfony\Component\VarDumper\Caster;

class Caster
{
    public const PREFIX_PROTECTED = "\0*\0";

    // Minimal constant used by Laravel's CliDumper::register.
    public const UNSET_CLOSURE_FILE_INFO = [];
}

class ReflectionCaster
{
    public const UNSET_CLOSURE_FILE_INFO = [];
}

namespace Symfony\Component\VarDumper\Dumper;

class CliDumper
{
    public function __construct()
    {
    }

    public function dump($var, $return = false)
    {
        return $return ? '' : null;
    }
}

class HtmlDumper
{
    public function __construct()
    {
    }

    public function dump($var, $output = null)
    {
        return null;
    }
}
