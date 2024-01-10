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
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionType;
use ReflectionUnionType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter as ConfigurationParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterManager;
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

        foreach ($this->getParamConverters() as $paramConverter) {
            $this->manager->add($paramConverter);
        }
    }

    /**
     * @throws ReflectionException
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
     *
     * @return ParamConverterInterface[]
     */
    protected function getParamConverters(): array
    {
        return array_map(function (string $converter): ParamConverterInterface {
            return $this->container->make($converter);
        }, $this->config['converters'] ?? []);
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
                    $configurations[$attributeInstance->getName()] = $attributeInstance;
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
                    $configuration = new ConfigurationParamConverter([]);
                    $configuration->setName($name);

                    $configurations[$name] = $configuration;
                }

                if (null !== $class && null === $configurations[$name]->getClass()) {
                    $configurations[$name]->setClass($class);
                }
            }

            if (isset($configurations[$name])) {
                $configurations[$name]->setIsOptional($param->isOptional() || $param->isDefaultValueAvailable() || ($type && $type->allowsNull()));
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
