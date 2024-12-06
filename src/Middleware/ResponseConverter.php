<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Middleware;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Kr0lik\ParamConverter\Annotation\ResponseConverter as ConfigurationResponseConverter;
use Kr0lik\ParamConverter\Converter\ResponseConverterInterface;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ResponseConverter
{
    /**
     * @var array<string, mixed>
     */
    private array $config;
    private Container $container;
    private ResponseConverterManager $manager;
    private bool $isAutoConvert;

    /**
     * @throws BindingResolutionException
     */
    public function __construct(Container $container, ResponseConverterManager $manager)
    {
        $this->container = $container;
        $this->manager = $manager;

        /** @var Repository $config */
        $config = $this->container->make(Repository::class);

        if ($config->has('response-converter')) {
            $this->config = $config->get('response-converter');
        }

        $this->isAutoConvert = $this->config['response']['autoConvert'] ?? true;

        $this->initResponseConverters();
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Route $route */
        $route = $request->route();
        /** @var array<string, mixed> $action */
        $action = $route->getAction();
        /** @var string[] $controller */
        $controller = Str::parseCallback($action['uses']);

        if (count($controller) < 2) {
            return $next($request);
        }

        $reflection = $this->getReflection($controller);

        $configuration = $this->getConfiguration($reflection);

        if (null === $configuration && $this->isAutoConvert) {
            $configuration = $this->autoConfigure($reflection);
        }

        $response = $next($request);

        return $this->manager->applyResponse($response, $configuration);
    }

    /**
     * @throws BindingResolutionException
     */
    private function initResponseConverters(): void
    {
        $priority = 0;

        foreach ($this->config['converters'] ?? [] as $key => $converter) {
            $paramConverter = $this->container->make($converter);

            if (!$paramConverter instanceof ResponseConverterInterface) {
                continue;
            }

            $this->manager->addResponseConverters($paramConverter, $priority);

            ++$priority;
        }
    }

    /**
     * @param string[] $controller
     *
     * @throws ReflectionException
     */
    private function getReflection(array $controller): ReflectionMethod
    {
        return new ReflectionMethod($controller[0], $controller[1]);
    }

    private function getConfiguration(ReflectionMethod $r): ?ConfigurationResponseConverter
    {
        if (count($r->getAttributes()) > 0) {
            foreach ($r->getAttributes() as $attribute) {
                $attributeInstance = $attribute->newInstance();

                if ($attributeInstance instanceof ConfigurationResponseConverter) {
                    return $attributeInstance;
                }
            }
        }

        return null;
    }

    private function autoConfigure(ReflectionMethod $r): ConfigurationResponseConverter
    {
        return new ConfigurationResponseConverter((string) $r->getReturnType());
    }
}
