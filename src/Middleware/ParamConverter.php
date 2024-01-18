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
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ParamConverter
{
    /**
     * @var array<string, mixed>
     */
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
        /** @var array<string, mixed> $action */
        $action = $route->getAction();
        /** @var string[] $controller */
        $controller = Str::parseCallback($action['uses']);

        if (count($controller) < 2) {
            return $next($request);
        }

        $reflection = $this->getReflection($controller);

        $configurations = $this->getConfigurations($reflection);

        if ($this->isAutoConvert) {
            $configurations = $this->autoConfigure($reflection, $request, $configurations);
        }

        $this->manager->apply($request, $configurations);

        foreach ($request->attributes->all() as $name => $value) {
            $route->setParameter($name, $value);
        }

        return $next($request);
    }

    /**
     * @throws BindingResolutionException
     */
    private function initParamConverters(): void
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
     * @param string[] $controller
     *
     * @throws ReflectionException
     */
    private function getReflection(array $controller): ReflectionMethod
    {
        return new ReflectionMethod($controller[0], $controller[1]);
    }

    /**
     * @return array<string, ConfigurationParamConverter>
     */
    private function getConfigurations(ReflectionMethod $r): array
    {
        $configurations = [];

        if (count($r->getAttributes()) > 0) {
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
    private function autoConfigure(ReflectionMethod $r, Request $request, array $configurations): array
    {
        foreach ($r->getParameters() as $param) {
            $type = $param->getType();
            $class = $this->getParamClassByType($type);

            if (null !== $class && $request instanceof $class) {
                continue;
            }

            $name = $param->getName();

            if (null !== $type) {
                if (!isset($configurations[$name])) {
                    $configuration = new ConfigurationParamConverter($name);

                    $configurations[$name] = $configuration;
                }

                if (null !== $class && null === $configurations[$name]->class) {
                    $configurations[$name]->class = $class;
                }
            }

            if (isset($configurations[$name])) {
                $configurations[$name]->isOptional = $param->isOptional() || $param->isDefaultValueAvailable() || (null !== $type && $type->allowsNull());
            }
        }

        return $configurations;
    }

    private function getParamClassByType(?ReflectionType $type): ?string
    {
        if (null === $type) {
            return null;
        }

        /** @var ReflectionNamedType[] $types */
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($types as $t) {
            if (!$t->isBuiltin()) {
                return $t->getName();
            }
        }

        return null;
    }
}
