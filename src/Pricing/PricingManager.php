<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\PricingStrategy;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Recurr\Rule;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * FIXME: Should we merge this class into the OrderManager class?
 */
class PricingManager
{
    public function __construct(
        private readonly DeliveryManager $deliveryManager,
        private readonly OrderManager $orderManager,
        private readonly OrderFactory $orderFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizerInterface $normalizer,
        private readonly LoggerInterface $logger
    )
    {}

    /**
     * @return OrderInterface|null
     */
    public function createOrder(Delivery $delivery, array $optionalArgs = []): ?OrderInterface
    {
        // Defining a default value in the method signature fails in the phpunit tests
        // even though it seems that it was fixed: https://github.com/sebastianbergmann/phpunit/commit/658d8decbec90c4165c0b911cf6cfeb5f6601cae
        $defaults = [
            'pricingStrategy' => new UsePricingRules(),
            'throwException' => false,
            'persist' => true,
        ];
        $optionalArgs+= $defaults;

        $pricingStrategy = $optionalArgs['pricingStrategy'];
        $throwException = $optionalArgs['throwException'];
        $persist = $optionalArgs['persist'];

        if (null === $pricingStrategy) {
            $pricingStrategy = new UsePricingRules();
        }

        $store = $delivery->getStore();

        if (null !== $store && $store->getCreateOrders()) {

            $order = null;

            if ($pricingStrategy instanceof UsePricingRules) {
                $price = $this->deliveryManager->getPrice($delivery, $store->getPricingRuleSet());

                if (null === $price) {

                    if ($throwException) {
                        throw new NoRuleMatchedException();
                    }

                    $this->logger->error('Price could not be calculated');

                    return null;
                }

                $price = (int) $price;
                $order = $this->orderFactory->createForDelivery($delivery, new PricingRulesBasedPrice($price));

            } elseif ($pricingStrategy instanceof UseArbitraryPrice) {
                $order = $this->orderFactory->createForDelivery($delivery, $pricingStrategy->getArbitraryPrice());

            } else {
                if ($throwException) {
                    throw new \InvalidArgumentException('Unsupported pricing config');
                }
            }

            if ($persist) {
                // We need to persist the order first,
                // because an auto increment is needed to generate a number
                $this->entityManager->persist($order);
                $this->entityManager->flush();

                $this->orderManager->onDemand($order);

                $this->entityManager->flush();
            }

            return $order;
        }

        return null;
    }

    public function duplicateOrder($store, $orderId): array | null
    {
        $previousOrder = $this->entityManager
            ->getRepository(Order::class)
            ->find($orderId);

        if (null === $previousOrder) {
            return null;
        }

        $previousDelivery = $previousOrder->getDelivery();

        if (null === $previousDelivery) {
            return null;
        }

        if ($store !== $previousDelivery->getStore()) {
            return null;
        }

        // Keep the original objects untouched, creating new ones instead
        $newTasks = array_map(function($task){
            return $task->duplicate();
        }, $previousDelivery->getTasks());

        $delivery = Delivery::createWithTasks(...$newTasks);
        $delivery->setStore($store);

        $orderItem = $previousOrder->getItems()->first();
        $productVariant = $orderItem->getVariant(); // @phpstan-ignore method.nonObject

        $previousArbitraryPrice = null;

        if (str_starts_with($productVariant->getCode(), 'CPCCL-ODDLVR')) {
            // price based on the PricingRuleSet; will be recalculated based on the latest rules
        } else {
            // arbitrary price
            $previousArbitraryPrice = new ArbitraryPrice($productVariant->getName(), $orderItem->getUnitPrice());
        }

        return [
            'delivery' => $delivery,
            'previousArbitraryPrice' => $previousArbitraryPrice,
        ];
    }

    public function createRecurrenceRule(Store $store, Delivery $delivery, Rule $rule, PricingStrategy $pricingStrategy): ?RecurrenceRule
    {
        $recurrenceRule = new RecurrenceRule();
        $recurrenceRule->setStore($store);

        $this->setData($recurrenceRule, $delivery, $rule, $pricingStrategy);

        $this->entityManager->persist($recurrenceRule);
        $this->entityManager->flush();

        return $recurrenceRule;
    }

    public function updateRecurrenceRule(RecurrenceRule $recurrenceRule, Delivery $tempDelivery, Rule $rule, PricingStrategy $pricingStrategy): ?RecurrenceRule
    {
        //FIXME; we have to temporary persist the delivery and tasks, because `TaskNormalizer` depends on database ids;
        // we should properly model subscription template to avoid the need for normalization
        $this->persistTempDelivery($tempDelivery);

        $this->setData($recurrenceRule, $tempDelivery, $rule, $pricingStrategy);
        $this->entityManager->flush();

        $this->cleanupTempDelivery($tempDelivery);

        return $recurrenceRule;
    }

    public function cancelRecurrenceRule(RecurrenceRule $recurrenceRule, Delivery $tempDelivery): void
    {
        $this->persistTempDelivery($tempDelivery);

        $this->entityManager->remove($recurrenceRule);
        $this->entityManager->flush();

        $this->cleanupTempDelivery($tempDelivery);
    }

    private function persistTempDelivery(Delivery $tempDelivery): void
    {
        // tempDelivery is added to entity manager by the form
        $tempDelivery->setOrder(null);
        foreach ($tempDelivery->getTasks() as $task) {
            $task->setPrevious(null);
            $task->setNext(null);
        }
        $this->entityManager->flush();
    }

    private function cleanupTempDelivery(Delivery $tempDelivery): void
    {
        foreach ($tempDelivery->getTasks() as $task) {
            $this->entityManager->remove($task);
        }
        $this->entityManager->remove($tempDelivery);
        $this->entityManager->flush();
    }

    private function setData(RecurrenceRule $recurrenceRule, Delivery $delivery, Rule $rule, PricingStrategy $pricingStrategy): void
    {
        $recurrenceRule->setRule($rule);
        $recurrenceRule->setGenerateOrders(true); // make configurable in #4716

        $tasks = $this->normalizer->normalize($delivery->getTasks(), 'jsonld', ['groups' => ['task_create']]);
        $tasks = array_map(function($task) {
            unset($task['@id']);

            // Keep only the time part of the date in the template
            $dateTimeFields = ['after', 'before', 'doneAfter', 'doneBefore'];
            foreach ($dateTimeFields as $field) {
                if (!isset($task[$field])) {
                    continue;
                }
                $task[$field] = (new DateTime($task[$field]))->format('H:i:s');
            }

            //FIXME: figure out why the weight is float sometimes
            if (isset($task['weight'])) {
                $task['weight'] = (int) $task['weight'];
            }

            // Do not store if it's not set (otherwise it breaks the denormalization)
            if (null === $task['ref']) {
                unset($task['ref']);
            }

            if (isset($task['tags'])) {
                $task['tags'] = array_map(
                    fn ($tag) => $tag['slug'],
                    $task['tags']
                );
            }

            return $task;
        }, $tasks);

        $template = [
            '@type' => 'hydra:Collection',
            'hydra:member' => $tasks,
        ];

        if ($pricingStrategy instanceof UseArbitraryPrice) {
            $arbitraryPrice = $pricingStrategy->getArbitraryPrice();
            $arbitraryPriceTemplate = [
                'variantName' => $arbitraryPrice->getVariantName(),
                'variantPrice' => $arbitraryPrice->getValue(),
            ];
            $recurrenceRule->setArbitraryPriceTemplate($arbitraryPriceTemplate);
        } else {
            $recurrenceRule->setArbitraryPriceTemplate(null);
        }

        $recurrenceRule->setTemplate($template);
    }

    public function createOrderFromRecurrenceRule(Task\RecurrenceRule $recurrenceRule, string $startDate, bool $persist = true): ?OrderInterface
    {
        $store = $recurrenceRule->getStore();

        $delivery = $this->deliveryManager->createDeliveryFromRecurrenceRule($recurrenceRule, $startDate, $persist);

        if (null === $delivery) {
            return null;
        }

        $order = null;
        if ($arbitraryPriceTemplate = $recurrenceRule->getArbitraryPriceTemplate()) {
            $order = $this->createOrder($delivery, [
                'pricingStrategy' => new UseArbitraryPrice(new ArbitraryPrice($arbitraryPriceTemplate['variantName'], $arbitraryPriceTemplate['variantPrice'])),
                'persist' => $persist,
            ]);
        } else {
            $order = $this->createOrder($delivery, [
                'persist' => $persist,
            ]);
        }

        if (null !== $order) {
            $order->setSubscription($recurrenceRule);
        }

        if ($persist) {
            $this->entityManager->flush();
        }

        return $order;
    }
}
