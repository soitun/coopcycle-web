<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Exception\NoAvailableTimeSlotException;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PickupTimeResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DeliveryManager
{
    private $expressionLanguage;
    private $routing;
    private $orderTimeHelper;
    private $orderTimelineCalculator;

    public function __construct(
        ExpressionLanguage $expressionLanguage,
        RoutingInterface $routing,
        OrderTimeHelper $orderTimeHelper,
        OrderTimelineCalculator $orderTimelineCalculator,
        LoggerInterface $logger = null)
    {
        $this->expressionLanguage = $expressionLanguage;
        $this->routing = $routing;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->orderTimelineCalculator = $orderTimelineCalculator;
        $this->logger = $logger ?? new NullLogger();
    }

    public function getPrice(Delivery $delivery, PricingRuleSet $ruleSet)
    {
        if ($ruleSet->getStrategy() === 'find') {

            foreach ($ruleSet->getRules() as $rule) {
                if ($rule->matches($delivery, $this->expressionLanguage)) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()));

                    return $rule->evaluatePrice($delivery, $this->expressionLanguage);
                }
            }

            return null;
        }

        if ($ruleSet->getStrategy() === 'map') {

            $totalPrice = 0;
            $matchedAtLeastOne = false;

            foreach ($ruleSet->getRules() as $rule) {
                if ($rule->matches($delivery, $this->expressionLanguage)) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()));

                    $price = $rule->evaluatePrice($delivery, $this->expressionLanguage);
                    $totalPrice += $price;

                    $matchedAtLeastOne = true;
                }
            }

            if ($matchedAtLeastOne) {

                return $totalPrice;
            }
        }

        return null;
    }

    public function createFromOrder(OrderInterface $order)
    {
        if (!$order->hasVendor()) {
            throw new \InvalidArgumentException('Order should reference a vendor');
        }

        $pickupAddress = $order->getPickupAddress();
        $dropoffAddress = $order->getShippingAddress();

        if (null === $dropoffAddress) {
            throw new ShippingAddressMissingException('Order does not have a shipping address');
        }

        $dropoffTimeRange = $order->getShippingTimeRange();
        if (null === $dropoffTimeRange) {
            $dropoffTimeRange =
                $this->orderTimeHelper->getShippingTimeRange($order);
        }

        if (null === $dropoffTimeRange) {
            throw new NoAvailableTimeSlotException('No time slot is avaible');
        }

        $distance = $this->routing->getDistance(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );
        $duration = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $timeline = $this->orderTimelineCalculator->calculate($order, $dropoffTimeRange);
        $pickupTime = $timeline->getPickupExpectedAt();

        $pickupTimeRange = DateUtils::dateTimeToTsRange($pickupTime, 5);

        $delivery = new Delivery();

        $pickup = $delivery->getPickup();
        $pickup->setAddress($pickupAddress);
        $pickup->setAfter($pickupTimeRange->getLower());
        $pickup->setBefore($pickupTimeRange->getUpper());

        $dropoff = $delivery->getDropoff();
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setAfter($dropoffTimeRange->getLower());
        $dropoff->setBefore($dropoffTimeRange->getUpper());

        $delivery->setDistance($distance);
        $delivery->setDuration($duration);

        $delivery->setOrder($order);

        return $delivery;
    }
}
