<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Exception;

use JsonSerializable;
use Throwable;

class ValidationException extends ParamConverterException implements JsonSerializable
{
    private const MESSAGE = 'Validation error';

    /**
     * @var array<array<string, string>>
     */
    private array $errors;

    /**
     * @param array<array<string, string>> $errors
     */
    public function __construct(array $errors, ?Throwable $previous = null)
    {
        $this->errors = $errors;

        parent::__construct(self::MESSAGE, 0, $previous);
    }

    /**
     * @return array<array<string, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, array<array<string, string>>>
     */
    public function jsonSerialize(): array
    {
        return ['errors' => $this->errors];
    }
}
