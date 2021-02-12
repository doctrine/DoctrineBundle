<?php

namespace Doctrine\Bundle\DoctrineBundle\Mapping;

use Attribute;
use Doctrine\ORM\Mapping\Annotation;
use InvalidArgumentException;

use function array_shift;
use function is_array;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ServiceGeneratedValue implements Annotation
{
    /**
     * The service id of the id-generator to use.
     *
     * @var string
     */
    public $id;

    /**
     * A method of the id-generator service that should be used to get the actual id-generator to use.
     *
     * @var string|null
     */
    public $method;

    /**
     * The arguments to pass to the previous method to configure the actual id-generator.
     *
     * @var mixed[]
     */
    public $arguments = [];

    /**
     * @param string|mixed[] $id
     * @param mixed[]        ...$arguments
     */
    public function __construct($id, ?string $method = null, ...$arguments)
    {
        if (is_array($id)) {
            if (is_array($id['value'] ?? null)) {
                $arguments = $id['value'];
                $id        = array_shift($arguments);
                $method    = array_shift($arguments);
            } else {
                $arguments = $id['arguments'] ?? [];
                $method    = $id['method'] ?? null;
                $id        = $id['id'] ?? null;
            }

            if ($id === null) {
                throw new InvalidArgumentException('Annotation "@ServiceGeneratedValue()" is missing argument #1 ($id).');
            }
        }

        $this->id        = $id;
        $this->method    = $method;
        $this->arguments = $arguments;
    }
}
