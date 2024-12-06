<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Converter;

use InvalidArgumentException;
use Kr0lik\ParamConverter\Annotation\ResponseConverter as ConfigurationResponseConverter;
use Kr0lik\ParamConverter\Contract\ResponseDtoInterface;
use Kr0lik\ParamConverter\Serializer\DataSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use TypeError;

class DtoResponseConverter implements ResponseConverterInterface
{
    public function __construct(
        private readonly DataSerializer $serializer,
    ) {}

    /**
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeError
     */
    public function apply(mixed $response, ConfigurationResponseConverter $configuration): JsonResponse
    {
        return new JsonResponse($this->serializer->serialize(
            $response,
            JsonEncoder::FORMAT,
        ));
    }

    public function supports(ConfigurationResponseConverter $configuration): bool
    {
        return self::getName() === $configuration->converter
            || is_a($configuration->class, ResponseDtoInterface::class, true);
    }

    public static function getName(): string
    {
        return 'json_response';
    }
}
