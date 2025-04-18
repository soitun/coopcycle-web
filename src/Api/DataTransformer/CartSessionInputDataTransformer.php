<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Api\Resource\CartSession;
use AppBundle\Sylius\Order\OrderFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CartSessionInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly OrderFactory $orderFactory,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $checkoutLogger
    )
    {
    }

    private function getCartFromSession()
    {
        if (null !== $token = $this->tokenStorage->getToken()) {
            if (($token instanceof JWTUserToken || $token instanceof JWTPostAuthenticationToken) && $token->hasAttribute('cart')) {
                return $token->getAttribute('cart');
            }
        }
    }

    private function getUserFromToken()
    {
        if (null !== $token = $this->tokenStorage->getToken()) {
            if (($token instanceof JWTUserToken || $token instanceof JWTPostAuthenticationToken) && is_object($token->getUser())) {
                return $token->getUser();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $session = new CartSession();

        if ($cart = $this->getCartFromSession()) {
            $cart->setRestaurant($data->restaurant);
        } else {
            $cart = $this->orderFactory->createForRestaurant($data->restaurant);

            $this->checkoutLogger->info(sprintf('Order (cart) object created (created_at = %s) | CartSessionInputDataTransformer',
                $cart->getCreatedAt()->format(\DateTime::ATOM)));
        }

        // TODO When in business context
        // - Associate to the business account with setBusinessAccount
        // - Set default address if not set

        if (null === $cart->getCustomer() && $user = $this->getUserFromToken()) {
            $cart->setCustomer($user->getCustomer());
        }

        if ($data->shippingAddress) {
            $isNewAddress = null === $data->shippingAddress->getId();

            // When this is an existing address,
            // make sure it belongs to the customer, if any
            $addressBelongsToCustomer = !$isNewAddress
                && (null !== $cart->getCustomer() && $cart->getCustomer()->hasAddress($data->shippingAddress));

            if ($isNewAddress || $addressBelongsToCustomer) {
                $cart->setShippingAddress($data->shippingAddress);
            }
        }

        $session->cart = $cart;

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof CartSession) {
          return false;
        }

        return CartSession::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
