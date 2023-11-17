<?php
// src/Service/RequestService.php
namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RequestService
{
    /**
     * @return mixed
     */
    public static function get(Request $request, string $fieldName, bool $isRequired = true, bool $isArray = false)
    {
        $requestData = json_decode($request->getContent(), true);

        if ($isArray) {
            $arrayData = self::arrayFlatten($requestData);

            foreach ($arrayData as $key => $value) {
                if ($fieldName === $key) {
                    return is_string($value) ? trim($value) : $value;
                }
            }

            if ($isRequired) {
                throw new BadRequestHttpException(sprintf('Missing field %s', $fieldName));
            }

            return null;
        }

        if (is_array($requestData) && array_key_exists($fieldName, $requestData)) {
            $fieldValue = $requestData[$fieldName];
            return is_string($fieldValue) ? trim($fieldValue) : $fieldValue;
        }

        $requestQuery = $request->query->get($fieldName);
        if (!empty($requestQuery)) {
            return is_string($requestQuery) ? trim($requestQuery) : $requestQuery;
        }

        if ($isRequired) {
            throw new BadRequestHttpException(sprintf('Missing field %s', $fieldName));
        }

        return null;
    }

    public static function arrayFlatten(array $array): array
    {
        $return = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $return = array_merge($return, self::arrayFlatten($value));
            } else {
                $return[$key] = $value;
            }
        }

        return $return;
    }
}
