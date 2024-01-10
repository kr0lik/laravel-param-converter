<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Middleware;

use Kr0lik\ParamConverter\Annotation\ParamConverter;
use Kr0lik\ParamConverter\Converter\ParamConverterInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function is_object;

class ParamConverterManager
{
    /**
     * @var array<int, ParamConverterInterface>
     */
    private array $converters = [];
    /**
     * @var array<string, ParamConverterInterface>
     */
    private array $namedConverters = [];

    /**
     * @param ParamConverter|ParamConverter[] $configurations
     *
     * @throws RuntimeException
     */
    public function apply(Request $request, array|object $configurations): void
    {
        if (is_object($configurations)) {
            $configurations = [$configurations];
        }

        foreach ($configurations as $configuration) {
            $this->applyConverter($request, $configuration);
        }
    }

    public function add(ParamConverterInterface $converter, int $priority = 0, ?string $name = null): void
    {
        $this->converters[$priority] = $converter;

        if (null !== $name) {
            $this->namedConverters[$name] = $converter;
        }
    }

    /**
     * @return ParamConverterInterface[]
     */
    public function all(): array
    {
        krsort($this->converters);

        return $this->converters;
    }

    /**
     * @throws RuntimeException
     */
    private function applyConverter(Request $request, ParamConverter $configuration): void
    {
        $value = $request->attributes->get($configuration->name);
        $className = $configuration->class;

        // If the value is already an instance of the class we are trying to convert it into
        // we should continue as no conversion is required
        $isAlreadyInstance = is_object($value) && $value instanceof $className;

        if ($isAlreadyInstance) {
            return;
        }

        $converterName = $configuration->converter;

        if (null !== $converterName && '' !== $converterName) {
            if (!isset($this->namedConverters[$converterName])) {
                throw new RuntimeException(sprintf("No converter named '%s' found for conversion of parameter '%s'.", $converterName, $configuration->name));
            }

            $converter = $this->namedConverters[$converterName];

            if (!$converter->supports($configuration)) {
                throw new RuntimeException(sprintf("Converter '%s' does not support conversion of parameter '%s'.", $converterName, $configuration->name));
            }

            $converter->apply($request, $configuration);

            return;
        }

        foreach ($this->all() as $converter) {
            if ($converter->supports($configuration)) {
                if ($converter->apply($request, $configuration)) {
                    return;
                }
            }
        }
    }
}
