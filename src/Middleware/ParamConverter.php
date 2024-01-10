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
use Kr0lik\ParamConverter\Annotation\ParamConverter as ConfigurationParamConverter;
use Kr0lik\ParamConverter\Converter\ParamConverterInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ParamConverter
{
    private array $config;
    private Container $container;
    private ParamConverterManager $manager;
    private bool $isAutoConvert;

    /**
     * @throws BindingResolutionException
     */
    public function __construct(Container $container, ParamConverterManager $manager)
    {
        $this->container = $container;
        $this->manager = $manager;

        /** @var Repository $config */
        $config = $this->container->make(Repository::class);

        if ($config->has('param-converter')) {
            $this->config = $config->get('param-converter');
        }

        $this->isAutoConvert = $this->config['request']['autoConvert'] ?? true;

        $this->initParamConverters();
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Route $route */
        $route = $request->route();
        $controller = Str::parseCallback($route->getAction()['uses']);

        if (count($controller) < 2) {
            return $next($request);
        }

        $reflection = $this->getReflection($controller);

        $configurations = $this->getConfigurations($reflection);

        if ($this->isAutoConvert) {
            $configurations = $this->autoConfigure($reflection, $request, $configurations);
        }

        $this->manager->apply($request, $configurations);

        foreach ($request->attributes->all() as $name => $class) {
            $route->setParameter($name, $class);
        }

        return $next($request);
    }

    /**
     * @throws BindingResolutionException
     */
    protected function initParamConverters(): void
    {
        $priority = 0;

        foreach ($this->config['converters'] ?? [] as $key => $converter) {
            /** @var ParamConverterInterface $paramConverter */
            $paramConverter = $this->container->make($converter);

            $this->manager->add($paramConverter, $priority, is_string($key) ? $key : null);

            ++$priority;
        }
    }

    /**
     * @throws ReflectionException
     */
    private function getReflection(mixed $controller): ReflectionFunctionAbstract
    {
        if (is_array($controller)) {
            return new ReflectionMethod($controller[0], $controller[1]);
        }

        if (is_object($controller) && is_callable([$controller, '__invoke'])) {
            return new ReflectionMethod($controller, '__invoke');
        }

        return new ReflectionFunction($controller);
    }

    private function getConfigurations(ReflectionFunctionAbstract $r): array
    {
        $configurations = [];

        if ($r->getAttributes() > 0) {
            foreach ($r->getAttributes() as $attribute) {
                $attributeInstance = $attribute->newInstance();

                if ($attributeInstance instanceof ConfigurationParamConverter) {
                    $configurations[$attributeInstance->name] = $attributeInstance;
                }
            }
        }

        return $configurations;
    }

    /**
     * @param array<string, ConfigurationParamConverter> $configurations
     *
     * @return array<string, ConfigurationParamConverter>
     */
    private function autoConfigure(ReflectionFunctionAbstract $r, Request $request, array $configurations): array
    {
        foreach ($r->getParameters() as $param) {
            $type = $param->getType();
            $class = $this->getParamClassByType($type);

            if (null !== $class && $request instanceof $class) {
                continue;
            }

            $name = $param->getName();

            if ($type) {
                if (!isset($configurations[$name])) {
                    $configuration = new ConfigurationParamConverter($name);

                    $configurations[$name] = $configuration;
                }

                if (null !== $class && null === $configurations[$name]->class) {
                    $configurations[$name]->class = $class;
                }
            }

            if (isset($configurations[$name])) {
                $configurations[$name]->isOptional = $param->isOptional() || $param->isDefaultValueAvailable() || ($type && $type->allowsNull());
            }
        }

        return $configurations;
    }

    private function getParamClassByType(?ReflectionType $type): ?string
    {
        if (null === $type) {
            return null;
        }

        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [$type] as $type) {
            if (!$type->isBuiltin()) {
                return $type->getName();
            }
        }

        return null;
    }
}
