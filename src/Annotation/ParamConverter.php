<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Annotation;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ParamConverter
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $name,
        public ?string $class = null,
        public array $options = [],
        public bool $isOptional = false,
        public ?string $converter = null
    ) {}
}
