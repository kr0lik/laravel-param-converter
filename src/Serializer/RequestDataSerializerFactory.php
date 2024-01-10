<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Serializer;

use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class RequestDataSerializerFactory
{
    /**
     * @throws BindingResolutionException
     */
    public static function create(Application $app): RequestDataSerializer
    {
        /** @var AnnotationReader $annotationReader */
        $annotationReader = $app->make(AnnotationReader::class);

        /** @var AttributeLoader $attributeLoader */
        $attributeLoader = $app->make(AttributeLoader::class, ['reader' => $annotationReader]);

        /** @var DateTimeNormalizer $dateTimeNormalizer */
        $dateTimeNormalizer = $app->make(DateTimeNormalizer::class);

        /** @var ArrayDenormalizer $arrayDenormalizer */
        $arrayDenormalizer = $app->make(ArrayDenormalizer::class);

        /** @var ClassMetadataFactory $classMetadataFactory */
        $classMetadataFactory = $app->make(ClassMetadataFactory::class, ['loader' => $attributeLoader]);

        /** @var CamelCaseToSnakeCaseNameConverter $camelCaseToSnakeCaseNameConverter */
        $camelCaseToSnakeCaseNameConverter = $app->make(CamelCaseToSnakeCaseNameConverter::class);

        /** @var MetadataAwareNameConverter $metadataAwareNameConverter */
        $metadataAwareNameConverter = $app->make(MetadataAwareNameConverter::class, [
            'metadataFactory' => $classMetadataFactory,
            'fallbackNameConverter' => $camelCaseToSnakeCaseNameConverter,
        ]);

        /** @var PhpDocExtractor $phpDocExtractor */
        $phpDocExtractor = $app->make(PhpDocExtractor::class);

        /** @var ReflectionExtractor $reflectionExtractor */
        $reflectionExtractor = $app->make(ReflectionExtractor::class);

        /** @var PropertyInfoExtractor $propertyInfoExtractor */
        $propertyInfoExtractor = $app->make(PropertyInfoExtractor::class, ['typeExtractors' => [$phpDocExtractor, $reflectionExtractor]]);

        /** @var PropertyAccessor $propertyAccessor */
        $propertyAccessor = $app->make(PropertyAccessor::class);

        /** @var ObjectNormalizer $objectNormalizer */
        $objectNormalizer = $app->make(ObjectNormalizer::class, [
            'classMetadataFactory' => $classMetadataFactory,
            'nameConverter' => $metadataAwareNameConverter,
            'propertyAccessor' => $propertyAccessor,
            'propertyTypeExtractor' => $propertyInfoExtractor,
        ]);

        /** @var JsonEncoder $jsonEncoder */
        $jsonEncoder = $app->make(JsonEncoder::class);

        return new RequestDataSerializer(
            normalizers: [
                $dateTimeNormalizer,
                $arrayDenormalizer,
                $objectNormalizer,
            ],
            encoders: [
                $jsonEncoder,
            ],
        );
    }
}
