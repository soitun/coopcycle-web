<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Delivery;
use Symfony\Component\Routing\Router;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ItemNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data =  $this->normalizer->normalize($object, $format, $context);

        unset($data['items']);

        $data['totalExcludingTax'] = $object->getTotalExcludingTax();
        $data['totalTax'] = $object->getTotalTax();
        $data['totalIncludingTax'] = $object->getTotalIncludingTax();
        $data['courier'] = $object->getCourier() !== null ? $object->getCourier()->getUsername() : null;

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Delivery;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type instanceof Delivery;
    }
}
