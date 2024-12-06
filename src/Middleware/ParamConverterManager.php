<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Middleware;

use Kr0lik\ParamConverter\Annotation\ParamConverter;
use Kr0lik\ParamConverter\Converter\ParamConverterInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

class ParamConverterManager
{
    /**
     * @var array<int, ParamConverterInterface>
     */
    private array $paramConverters = [];

    /**
     * @throws RuntimeException
     */
    public function applyRequest(Request $request, ParamConverter ...$configurations): void
    {
        foreach ($configurations as $configuration) {
            $this->applyParamConverter($request, $configuration);
        }
    }

    public function addParamConverters(ParamConverterInterface $converter, int $priority = 0): void
    {
        $this->paramConverters[$priority] = $converter;
        krsort($this->paramConverters);
    }

    /**
     * @throws RuntimeException
     */
    private function applyParamConverter(Request $request, ParamConverter $configuration): void
    {
        $value = $request->attributes->get($configuration->name);
        $className = $configuration->class;

        // If the value is already an instance of the class we are trying to convert it into
        // we should continue as no conversion is required
        $isAlreadyInstance = $value instanceof $className;

        if ($isAlreadyInstance) {
            return;
        }

        $converterName = $configuration->converter;

        if ('' !== $converterName) {
            foreach ($this->paramConverters as $converter) {
                if ($converter::getName() === $converterName && $converter->supports($configuration)) {
                    $converter->apply($request, $configuration);

                    return;
                }
            }

            throw new RuntimeException(sprintf("No converter named '%s' found for conversion of parameter '%s'.", $converterName, $configuration->name));
        }

        foreach ($this->paramConverters as $converter) {
            if ($converter->supports($configuration)) {
                if ($converter->apply($request, $configuration)) {
                    return;
                }
            }
        }
    }
}
