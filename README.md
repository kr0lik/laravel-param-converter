# laravel-param-converter
Request ParamConverter with json serialization

## Usage

Add Config:
```php
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
```

Create dto:

```php
<?php

declare(strict_types=1);

use Kr0lik\ParamConverter\Contract\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TextDto implements RequestDtoInterface
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(10)]
        public int               $a,
        #[Assert\NotBlank()]
        public string            $b,
        public DateTimeImmutable $c,
    ) {
    }
}
```

Create action or controller:
```php
<?php
class YourAction extends Controller
{
    #[ParamConverter("requestDto", class=TextDto::class)]
    public function __invoke(TextDto $requestDto)
    {
        ....
    }
}
```

or

Add annotation if in config set `autoConvert = false`:
```php
<?php
use Kr0lik\ParamConverter\Annotation\ParamConverter;

class YourAction extends Controller
{
    #[ParamConverter("requestDto", class=TextDto::class)]
    public function __invoke(TextDto $requestDto)
    {
        ....
    }
}
```