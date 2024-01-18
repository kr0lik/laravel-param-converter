# laravel-param-converter
Request ParamConverter with json serialization

## Install
```
$ composer require kr0lik/laravel-param-converter
```
Setup is extremely simple, just add the service provider to your app.php config.

Kr0lik\ParamConverter\ParamConverterServiceProvider::class,

You can also publish the config so you can add your own param converters.
```
$ php artisan vendor:publish --provider="Kr0lik\ParamConverter\ParamConverterServiceProvider"
```

## Usage

Add Config:
```php
<?php

declare(strict_types=1);

use Kr0lik\ParamConverter\Converter\RequestDataConverter;
use Kr0lik\ParamConverter\Converter\QueryParamConverter;

return [
    'request' => [
        'autoConvert' => true,
    ],
    'converters' => [
        RequestDataConverter::NAME => RequestDataConverter::class,
        QueryParamConverter::NAME => QueryParamConverter::class,
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
use Kr0lik\ParamConverter\Annotation\ParamConverter;

class YourAction extends Controller
{
    #[ParamConverter('token', converter: QueryParamConverter::NAME)]
    #[ParamConverter("requestDto", class=TextDto::class)]
    public function __invoke(string $token, TextDto $requestDto)
    {
        ....
    }
}
```

or

Add annotation if in config set `autoConvert = true`:
```php
<?php
use Kr0lik\ParamConverter\Annotation\ParamConverter;

class YourAction extends Controller
{
    public function __invoke(TextDto $requestDto)
    {
        ....
    }
}
```
