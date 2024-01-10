<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Kr0lik\ParamConverter\Middleware\ParamConverter;
use Kr0lik\ParamConverter\Serializer\RequestDataSerializer;
use Kr0lik\ParamConverter\Serializer\RequestDataSerializerFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class ParamConverterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'param-converter');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function boot(): void
    {
        if (function_exists('config_path')) {
            $publishPath = config_path('param-converter.php');
        } else {
            $publishPath = base_path('config/param-converter.php');
        }

        $this->publishes([$this->configPath() => $publishPath], 'config/param-converter');

        $this->registerRequestDataSerializer();
        $this->registerValidator();
        $this->registerParamConverter();
    }

    private function configPath(): string
    {
        return __DIR__.'/../config/param-converter.php';
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function registerParamConverter(): void
    {
        /** @var Router $router */
        $router = $this->app->get('router');

        foreach ($router->getMiddlewareGroups() as $group => $middleware) {
            $router->pushMiddlewareToGroup($group, ParamConverter::class);
        }
    }

    private function registerRequestDataSerializer(): void
    {
        $this->app->singleton(RequestDataSerializer::class, static function (Application $app): RequestDataSerializer {
            return RequestDataSerializerFactory::create($app);
        });
    }

    private function registerValidator(): void
    {
        $this->app->singleton(ValidatorInterface::class, static function (Application $app): ValidatorInterface {
            return (new ValidatorBuilder())
                ->enableAttributeMapping()
                ->getValidator()
            ;
        });
    }
}
