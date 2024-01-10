<?php

declare(strict_types=1);

namespace Kr0lik\ParamConverter\Converter;

use InvalidArgumentException;
use Kr0lik\ParamConverter\Contract\RequestDtoInterface;
use Kr0lik\ParamConverter\Exception\ValidationException;
use Kr0lik\ParamConverter\Serializer\RequestDataSerializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

class RequestDataConverter implements ParamConverterInterface
{
    public const NAME = 'request_data_converter';
    private const DISABLE_TYPE_ENFORCEMENT = true;

    public function __construct(
        private readonly RequestDataSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeError
     * @throws ValidationException
     */
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        try {
            $dto = $this->serializer->deserialize($request->getContent(), $configuration->getClass(), JsonEncoder::FORMAT, [
                AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => self::DISABLE_TYPE_ENFORCEMENT,
            ]);
        } catch (MissingConstructorArgumentsException $e) {
            if (0 === preg_match_all('#("\$([a-zA-Z0-9_]+)")+#Us', $e->getMessage(), $matches)) {
                throw $e;
            }

            throw new ValidationException(array_map(static function (string $match): array { return [$match => 'is required.']; }, $matches[2] ?? []));
        } catch (InvalidArgumentException|NotNormalizableValueException $e) {
            if (0 === preg_match('#type\sof\sthe\s"([a-zA-Z0-9_]+)"\sattribute\sfor#', $e->getMessage(), $matches)) {
                throw $e;
            }

            $field = $matches[1] ?? '';

            throw new ValidationException([[$field => $e->getMessage()]], $e);
        } catch (TypeError $e) {
            if (0 === preg_match('#__construct\(\):\sArgument\s\#\d\s\(\$([a-zA-Z0-9_]+)\)#', $e->getMessage(), $matches)) {
                throw $e;
            }

            $field = $matches[1] ?? '';

            throw new ValidationException([[$field => $e->getMessage()]], $e);
        }

        $groups = $configuration->getOptions()[AbstractNormalizer::GROUPS] ?? null;
        $errors = $this->validator->validate($dto, null, $groups);
        assert($errors instanceof ConstraintViolationList);

        if ($errors->count() > 0) {
            throw new ValidationException(array_map(
                static function (ConstraintViolation $violation): array {
                    return [$violation->getPropertyPath() => (string) $violation->getMessage()];
                },
                (array) $errors->getIterator()
            ));
        }

        $request->attributes->set($configuration->getName(), $dto);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return is_a($configuration->getClass(), RequestDtoInterface::class, true);
    }
}
