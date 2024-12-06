<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Converter;

use Kr0lik\ParamConverter\Annotation\ResponseConverter;
use Kr0lik\ParamConverter\Annotation\ResponseConverter as ConfigurationResponseConverter;
use Symfony\Component\HttpFoundation\Response;

interface ResponseConverterInterface
{
    public function apply(mixed $response, ResponseConverter $configuration): Response;

    public function supports(ConfigurationResponseConverter $configuration): bool;

    public static function getName(): string;
}
