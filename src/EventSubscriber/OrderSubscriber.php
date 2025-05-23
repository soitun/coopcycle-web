<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Exception\LoopeatInsufficientStockException;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Validator\Constraints\LoopeatStock as AssertLoopeatStock;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Validator\ValidatorInterface as BaseValidatorInterface;

final class OrderSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private OrderTimeHelper $orderTimeHelper,
        private OrderProcessorInterface $orderProcessor,
        private BaseValidatorInterface $baseValidator,
        private LoggerInterface $checkoutLogger
    ) { }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['validateAccept', EventPriorities::POST_DESERIALIZE],
            ],
            KernelEvents::VIEW => [
                ['preValidate', EventPriorities::PRE_VALIDATE],
                ['process', EventPriorities::PRE_WRITE],
            ],
        ];
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    public function validateAccept(RequestEvent $event)
    {
        $request = $event->getRequest();

        // PUT /api/orders/{id}/accept
        if ($request->attributes->get('_route') === '_api_/orders/{id}/accept_put') {

            $order = $request->attributes->get('data');

            $violations = $this->baseValidator->validate(
                $order->getItems(),
                new All([ new AssertLoopeatStock(true) ])
            );

            if (count($violations) > 0) {
                throw new LoopeatInsufficientStockException($violations);
            }
        }
    }

    public function preValidate(ViewEvent $event)
    {
        $request = $event->getRequest();
        $result = $event->getControllerResult();

        if (!($result instanceof Order && Request::METHOD_POST === $request->getMethod())) {
            return;
        }

        $order = $result;

        // // Convert date to DateTime
        // if (!$delivery->getDate() instanceof \DateTime) {
        //     $delivery->setDate(new \DateTime($delivery->getDate()));
        // }

        $user = $this->getUser();

        // Make sure customer is set
        if (null === $order->getCustomer() && null !== $user) {
            $order->setCustomer($this->getUser()->getCustomer());
        }

        if ($request->attributes->get('_route') === '_api_/orders.{_format}_post'
            && $order->hasVendor() && null === $order->getId() && null === $order->getShippingTimeRange()) {
            $shippingTimeRange = $this->orderTimeHelper->getShippingTimeRange($order);
            $order->setShippingTimeRange($shippingTimeRange);
        }

        $event->setControllerResult($order);
    }

    public function process(ViewEvent $event)
    {
        $resource = $event->getControllerResult();
        $request = $event->getRequest();
        $method = $request->getMethod();

        if (!$resource instanceof Order || Request::METHOD_PUT !== $method) {
            return;
        }

        if ($resource->getState() !== Order::STATE_CART) {
            return;
        }

        $this->checkoutLogger->info(sprintf('Order #%d | OrderSubscriber | started orderProcessor->process | request: %s | %s',
            $resource->getId(), $method, $request->getRequestUri()));

        $this->orderProcessor->process($resource);
    }
}
