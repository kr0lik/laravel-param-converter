<?php

declare(strict_types=1);

use Kr0lik\ParamConverter\Converter\RequestDataConverter;
use Kr0lik\ParamConverter\Converter\QueryParamConverter;

return [
    'request' => [
        'autoConvert' => false,
    ],
    'converters' => [
        RequestDataConverter::NAME => RequestDataConverter::class,
        QueryParamConverter::NAME => QueryParamConverter::class,
    ],
];
