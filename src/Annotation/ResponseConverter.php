<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Annotation;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ResponseConverter
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $class = '',
        public array $options = [],
        public string $converter = ''
    ) {}
}
