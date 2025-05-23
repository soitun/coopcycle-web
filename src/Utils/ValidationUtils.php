<?php

namespace AppBundle\Utils;

use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationUtils
{
    /**
     * Transform a ConstraintViolationList to a JSON-serializable array.
     *
     * @deprecated
     * @return array
     */
    public static function serializeValidationErrors(ConstraintViolationListInterface $errors)
    {
        $validationsArray = [];

        foreach ($errors as $violation) {
            $validationsArray[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $validationsArray;
    }

    public static function serializeViolationList(ConstraintViolationListInterface $violations)
    {
        $data = [];

        foreach ($violations as $violation) {
            $data[$violation->getPropertyPath()][] = [
                'message' => $violation->getMessage(),
                'code' => $violation->getCode()
            ];
        }

        return $data;
    }

    public static function serializeFormError(FormError $error)
    {
        if ($error->getCause() instanceof ConstraintViolationInterface) {

            $violation = $error->getCause();

            return [
                'message' => $violation->getMessage(),
                'code' => $violation->getCode()
            ];
        }

        return [
            'message' => $error->getMessage(),
            'code' => null
        ];
    }

    public static function serializeToString(ConstraintViolationListInterface $violations): string
    {
        $data = ValidationUtils::serializeViolationList($violations);
        return implode('; ', $data);
    }
}
