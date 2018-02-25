<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\InvalidStatusException;
use AppBundle\ExpressionLanguage\ZoneExpressionLanguageProvider;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class DeliveryManager
{
    private $pricingRuleRepository;
    private $taxRateResolver;
    private $calculator;
    private $taxCategoryRepository;
    private $taxCategoryCode;
    private $zoneExpressionLanguageProvider;
    private $routing;

    public function __construct(EntityRepository $pricingRuleRepository,
        TaxRateResolverInterface $taxRateResolver, CalculatorInterface $calculator,
        TaxCategoryRepositoryInterface $taxCategoryRepository, $taxCategoryCode, ZoneExpressionLanguageProvider $zoneExpressionLanguageProvider,
        RoutingInterface $routing)
    {
        $this->pricingRuleRepository = $pricingRuleRepository;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryCode = $taxCategoryCode;
        $this->zoneExpressionLanguageProvider = $zoneExpressionLanguageProvider;
        $this->routing = $routing;
    }

    public function applyTaxes(Delivery $delivery)
    {
        $taxCategory = $this->taxCategoryRepository->findOneBy(['code' => $this->taxCategoryCode]);

        $delivery->setTaxCategory($taxCategory);

        $taxRate = $this->taxRateResolver->resolve($delivery);

        $totalIncludingTax = $delivery->getTotalIncludingTax();
        $totalTax = $this->calculator->calculate($totalIncludingTax, $taxRate);
        $totalExcludingTax = $totalIncludingTax - $totalTax;

        $delivery->setTotalExcludingTax($totalExcludingTax);
        $delivery->setTotalTax($totalTax);
    }

    public function getPrice(Delivery $delivery, PricingRuleSet $ruleSet)
    {
        $language = new ExpressionLanguage();
        $language->registerProvider($this->zoneExpressionLanguageProvider);

        foreach ($ruleSet->getRules() as $rule) {
            if ($rule->matches($delivery, $language)) {
                return $rule->getPrice();
            }
        }
    }

    public function calculate(Delivery $delivery)
    {
        $data = $this->routing->getRawResponse(
            $delivery->getOriginAddress()->getGeo(),
            $delivery->getDeliveryAddress()->getGeo()
        );

        $distance = $data['routes'][0]['distance'];
        $duration = $data['routes'][0]['duration'];

        $delivery->setDistance((int) $distance);
        $delivery->setDuration((int) $duration);
    }
}
