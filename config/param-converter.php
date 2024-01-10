<?php

declare(strict_types=1);

use Kr0lik\ParamConverter\Converter\RequestDataConverter;

return [
    'request' => [
        'autoConvert' => true,
    ],
    'converters' => [
        RequestDataConverter::class,
    ],
];
