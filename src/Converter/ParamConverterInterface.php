<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Converter;

use Kr0lik\ParamConverter\Annotation\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

interface ParamConverterInterface
{
    public function apply(Request $request, ParamConverter $configuration): bool;

    public function supports(ParamConverter $configuration): bool;
}
