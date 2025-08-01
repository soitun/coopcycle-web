<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Sylius\TaxCategory;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType as BaseTaxCategoryChoiceType;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductTaxCategoryChoiceLoader implements ChoiceLoaderInterface
{
    private static $serviceTaxCategories = [
        'SERVICE',
        'SERVICE_TAX_EXEMPT',
    ];

    private static $otherTaxCategories = [
        'DRINK',
        'DRINK_ALCOHOL',
        'FOOD',
        'FOOD_TAKEAWAY',
        'JEWELRY',
        'BASE_STANDARD',
        'BASE_INTERMEDIARY',
        'BASE_REDUCED',
        'BASE_EXEMPT',
    ];

    public function __construct(
        private EntityRepository $taxCategoryRepository,
        private TaxRateResolverInterface $taxRateResolver,
        private ProductVariantFactoryInterface $productVariantFactory,
        private string $country,
        private bool $legacyTaxes = true)
    {}

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList(?callable $value = null): ChoiceListInterface
    {
        $qb = $this->taxCategoryRepository->createQueryBuilder('c');
        $qb->andWhere($qb->expr()->notIn('c.code', self::$serviceTaxCategories));

        if ($this->legacyTaxes) {
            $qb->andWhere($qb->expr()->notIn('c.code', self::$otherTaxCategories));
        } else {
            $qb->andWhere($qb->expr()->in('c.code', self::$otherTaxCategories));
        }

        $categories = $qb->getQuery()->getResult();

        if ($this->legacyTaxes) {

            return new ArrayChoiceList($categories, $value);
        }

        // Remove tax categories when tax rate can't be resolved
        $categories = array_filter($categories, function (TaxCategory $c) {

            $variant = $this->productVariantFactory->createNew();
            $variant->setTaxCategory($c);

            $rate = $this->taxRateResolver->resolve($variant, [
                'country' => strtolower($this->country)
            ]);

            return $rate !== null;
        });

        return new ArrayChoiceList($categories, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, ?callable $value = null): array
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, ?callable $value = null): array
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
