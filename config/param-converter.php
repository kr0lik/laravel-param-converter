<?php

declare(strict_types=1);

use Kr0lik\ParamConverter\Converter\DtoResponseConverter;
use Kr0lik\ParamConverter\Converter\JsonResponseConverter;
use Kr0lik\ParamConverter\Converter\RequestDataConverter;
use Kr0lik\ParamConverter\Converter\QueryParamConverter;

return [
    'request' => [
        'autoConvert' => false,
    ],
    'response' => [
        'autoConvert' => false,
    ],
    'converters' => [
        RequestDataConverter::class,
        QueryParamConverter::class,
        DtoResponseConverter::class,
        JsonResponseConverter::class,
    ],
];
