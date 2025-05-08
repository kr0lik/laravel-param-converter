<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Middleware;

use Kr0lik\ParamConverter\Annotation\ResponseConverter;
use Kr0lik\ParamConverter\Converter\ResponseConverterInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ResponseConverterManager
{
    /**
     * @var array<int, ResponseConverterInterface>
     */
    private array $responseConverters = [];

    /**
     * @throws RuntimeException
     */
    public function applyResponse(mixed $response, ?ResponseConverter $configuration): mixed
    {
        if (null !== $configuration) {
            return $this->applyResponseConverter($response, $configuration);
        }

        return $response;
    }

    public function addResponseConverters(ResponseConverterInterface $converter, int $priority = 0): void
    {
        $this->responseConverters[$priority] = $converter;
        krsort($this->responseConverters);
    }

    /**
     * @throws RuntimeException
     */
    private function applyResponseConverter(mixed $response, ResponseConverter $configuration): mixed
    {
        // If the value is already an instance of a response
        // we should continue as no conversion is required
        $isAlreadyResponse = $response instanceof Response;

        if ($isAlreadyResponse) {
            return $response;
        }

        $converterName = $configuration->converter;

        if ('' !== $converterName) {
            foreach ($this->responseConverters as $converter) {
                if ($converter::getName() === $converterName && $converter->supports($configuration)) {
                    return $converter->apply($response, $configuration);
                }
            }

            throw new RuntimeException(sprintf("No converter named as '%s' found for conversion of response.", $converterName));
        }

        foreach ($this->responseConverters as $converter) {
            if ($converter->supports($configuration)) {
                return $converter->apply($response, $configuration);
            }
        }

        return $response;
    }
}
