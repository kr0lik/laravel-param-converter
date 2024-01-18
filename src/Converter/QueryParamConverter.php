<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Converter;

use Kr0lik\ParamConverter\Annotation\ParamConverter;
use Kr0lik\ParamConverter\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;

class QueryParamConverter implements ParamConverterInterface
{
    public const NAME = 'query_param';
    public const OPTIONAL_OPTION = 'optional';

    /**
     * @throws ValidationException
     */
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $param = $configuration->name;

        $value = $request->query->get($param);

        if (null === $value) {
            if (array_key_exists(self::OPTIONAL_OPTION, $configuration->options)) {
                return false;
            }

            throw new ValidationException([[$param => 'parameter is required.']]);
        }

        $request->attributes->set($param, $value);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return self::NAME === $configuration->converter;
    }
}
