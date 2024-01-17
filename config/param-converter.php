<?php

declare(strict_types=1);

use Kr0lik\ParamConverter\Converter\RequestDataConverter;

return [
    'request' => [
        'autoConvert' => false,
    ],
    'converters' => [
        RequestDataConverter::class,
    ],
];
