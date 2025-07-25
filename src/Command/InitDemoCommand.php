<?php

namespace AppBundle\Command;

use AppBundle\DataType\TsRange;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\MessageHandler\Order\CreateTasks;
use AppBundle\Entity;
use AppBundle\Faker\AddressProvider;
use AppBundle\Faker\RestaurantProvider;
use AppBundle\Service\Geocoder;
use Craue\ConfigBundle\Util\Config;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Faker;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Nucleos\UserBundle\Util\UserManipulator;
use Geocoder\Provider\Addok\Addok as AddokProvider;
use Geocoder\Provider\Chain\Chain as ChainProvider;
use Geocoder\Provider\Photon\Photon as PhotonProvider;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Http\Adapter\Guzzle7\Client;
use libphonenumber\PhoneNumberUtil;
use League\Geotools\Coordinate\Coordinate;
use Redis;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

class InitDemoCommand extends Command
{
    private $ormPurger;
    private $lockFactory;
    private $batchSize = 10;
    private $excludedTables = [
        'craue_config_setting',
        'migration_versions',
        'sylius_locale',
        'sylius_channel',
        'sylius_payment_method',
        'sylius_tax_category',
        'sylius_tax_rate',
    ];

    private $createdUsers = [];
    private $createdRestaurants = [];
    private $createdStores = [];

    private static $users = [
        'admin' => [
            'password' => 'admin',
            'roles' => ['ROLE_ADMIN']
        ],
        'dispatcher' => [
            'password' => 'dispatcher',
            'roles' => ['ROLE_DISPATCHER']
        ],
    ];

    private static $parisFranceCoords = [
        48.857498,
        2.335402,
    ];

    private static $usersToCreate = 50;
    private static $couriersToCreate = 50;
    private static $restaurantsToCreate = 50;
    private static $storesToCreate = 25;
    private static $foodTechOrdersToCreate = 25;
    //TODO: implement
    private static $packageDeliveryOrdersToCreate = 0;

    protected function configure()
    {
        $this
            ->setName('coopcycle:demo:init')
            ->setDescription('Initialize CoopCycle demo.')
            ->addOption(
                'no-create-admin',
                mode: InputOption::VALUE_NONE
            )
            ->addOption(
                'latlng',
                mode: InputOption::VALUE_REQUIRED
            );
    }

    public function __construct(
        private ManagerRegistry $doctrine,
        private EntityManagerInterface $entityManager,
        private UserManipulator $userManipulator,
        private LoaderInterface $fixturesLoader,
        private Faker\Generator $faker,
        private Redis $redis,
        private Config $craueConfig,
        private string $configEntityName,
        private FactoryInterface $taxonFactory,
        private PhoneNumberUtil $phoneNumberUtil,
        private RepositoryInterface $taxCategoryRepository,
        private Geocoder $geocoder,
        private OrderProcessorInterface $orderProcessor,
        private CreateTasks $createTasks,
        private string $country,
        private string $defaultLocale)
    {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $providerClass = 'AppBundle\\Faker\\' . $this->defaultLocale . '\\RestaurantProvider';
        if (!class_exists($providerClass, true)) {
            $providerClass = RestaurantProvider::class;
        }

        $restaurantProvider = new $providerClass($this->faker);

        $this->faker->addProvider($restaurantProvider);

        $this->ormPurger = new ORMPurger($this->entityManager, $this->excludedTables);
        // $this->ormPurger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);

        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $createSuperUsers = !$input->getOption('no-create-admin');
        $lock = $this->lockFactory->createLock('orm-purger');

        if ($lock->acquire()) {

            $output->writeln('Verifying database config…');
            $this->handleCraueConfig($input);

            $output->writeln('Purging database…');
            $this->ormPurger->purge();

            $output->writeln('Resetting sequences…');
            $this->resetSequences();

            if ($createSuperUsers) {
                $output->writeln('Creating super users…');
                foreach (self::$users as $username => $params) {
                    $this->createUser($username, $params);
                }
            }

            $output->writeln('Creating users…');
            for ($i = 1; $i <= self::$usersToCreate; $i++) {
                $username = "user_{$i}";
                $user = $this->createUser($username, ['password' => $username]);
                $user->addAddress($this->faker->randomAddress);
            }
            $this->doctrine->getManagerForClass(Entity\User::class)->flush();
            $this->createdUsers = $this->doctrine->getRepository(Entity\User::class)
                ->createQueryBuilder('u')
                ->where("u.username LIKE 'user_%'")
                ->getQuery()
                ->getResult();

            $output->writeln('Creating couriers…');
            for ($i = 1; $i <= self::$couriersToCreate; $i++) {
                $this->createCourier("bot_{$i}");
            }

            $output->writeln('Creating restaurants…');
            $this->createRestaurants($output);
            $this->createdRestaurants = $this->doctrine->getRepository(Entity\LocalBusiness::class)->findAll();

            $output->writeln('Creating stores…');
            $this->createStores();
            $this->createdStores = $this->doctrine->getRepository(Entity\Store::class)->findAll();

            $output->writeln('Creating orders…');
            $this->createOrders();

            $output->writeln('Removing data from Redis…');
            $keys = $this->redis->keys('*');
            foreach ($keys as $key) {
                $this->redis->del($key);
            }

            $lock->release();
        }

        return 0;
    }

    private function resetSequences()
    {
        $connection = $this->doctrine->getConnection();
        $rows = $connection->fetchAllAssociative('SELECT sequence_name FROM information_schema.sequences');
        foreach ($rows as $row) {

            $sequenceName = $row['sequence_name'];
            $tableName = str_replace('_id_seq', '', $sequenceName);

            if (in_array($tableName, $this->excludedTables)) {
                continue;
            }

            try {
                $connection->executeQuery(sprintf('ALTER SEQUENCE %s RESTART WITH 1', $row['sequence_name']));
            } catch (TableNotFoundException $e) {
                // We don't care
            }
        }
    }

    private function loadFixtures($filename, array $objects = [], $parameters = [])
    {
        return $this->fixturesLoader->load(
            [$filename],
            $parameters,
            $objects,
            PurgeMode::createNoPurgeMode()
        );
    }

    private function createCraueConfigSetting($name, $value, $section = 'general')
    {
        $className = $this->configEntityName;

        $setting = new $className();

        $setting->setName($name);
        $setting->setValue($value);
        $setting->setSection($section);

        return $setting;
    }

    private function handleCraueConfig(InputInterface $input)
    {
        $className = $this->configEntityName;
        $em = $this->doctrine->getManagerForClass($className);

        $mapCenterValue = $input->getOption('latlng');
        if (null === $mapCenterValue) {
            // in this case, the option was not passed when running the command
            try {
                $mapCenterValue = $this->craueConfig->get('latlng');
            } catch (\RuntimeException $e) {
                $mapCenterValue = implode(',', self::$parisFranceCoords);
                $mapCenter = $this->createCraueConfigSetting('latlng', $mapCenterValue);
                $em->persist($mapCenter);
            }
        }

        try {
            $this->craueConfig->get('brand_name');
        } catch (\RuntimeException $e) {
            $brandName = $this->createCraueConfigSetting('brand_name', 'CoopCycle');
            $em->persist($brandName);
        }

        $em->flush();

        // We create a custom geocoder chain using free services

        $stack = HandlerStack::create();
        $stack->push(RateLimiterMiddleware::perSecond(2));

        $httpClient  = new GuzzleClient(['handler' => $stack, 'timeout' => 30.0]);
        $httpAdapter = new Client($httpClient);

        $providers = [];

        if ('fr' === $this->country) {
            $providers[] = AddokProvider::withBANServer($httpAdapter);
        }

        // Make sure we use a language supported by Photon
        // "Language it is not supported, supported languages are: default, en, de, fr"
        $geocoderLocale = in_array($this->defaultLocale, ['en', 'de', 'fr']) ? $this->defaultLocale : 'en';

        $providers[] = PhotonProvider::withKomootServer($httpAdapter);

        $statefulGeocoder =
            new StatefulGeocoder(new ChainProvider($providers), $geocoderLocale);

        $this->geocoder->setGeocoder($statefulGeocoder);

        $addressProvider = new AddressProvider($this->faker, $this->geocoder, new Coordinate($mapCenterValue));

        $this->faker->addProvider($addressProvider);
    }

    private function createUser($username, array $params = [])
    {
        $user = $this->userManipulator->create($username, $params['password'], "{$username}@demo.coopcycle.org", true, false);
        if (isset($params['roles'])) {
            foreach ($params['roles'] as $role) {
                $this->userManipulator->addRole($username, $role);
            }
        }

        return $user;
    }

    private function createCourier($username)
    {
        $this->userManipulator->create($username, $username, "{$username}@demo.coopcycle.org", true, false);
        $this->userManipulator->addRole($username, 'ROLE_COURIER');
    }

    private function createMenuTaxon($appetizers, $dishes, $desserts)
    {
        $menu = $this->taxonFactory->createNew();

        $menu->setCode($this->faker->uuid);
        $menu->setSlug($this->faker->uuid);
        $menu->setName('Default');

        $appetizersTaxon = $this->taxonFactory->createNew();
        $appetizersTaxon->setCode($this->faker->uuid);
        $appetizersTaxon->setSlug($this->faker->uuid);
        $appetizersTaxon->setName('Entrées');
        foreach ($appetizers as $product) {
            $appetizersTaxon->addProduct($product);
        }

        $dishesTaxon = $this->taxonFactory->createNew();
        $dishesTaxon->setCode($this->faker->uuid);
        $dishesTaxon->setSlug($this->faker->uuid);
        $dishesTaxon->setName('Plats');
        foreach ($dishes as $product) {
            $dishesTaxon->addProduct($product);
        }

        $dessertsTaxon = $this->taxonFactory->createNew();
        $dessertsTaxon->setCode($this->faker->uuid);
        $dessertsTaxon->setSlug($this->faker->uuid);
        $dessertsTaxon->setName('Desserts');
        foreach ($desserts as $product) {
            $dessertsTaxon->addProduct($product);
        }

        $menu->addChild($appetizersTaxon);
        $menu->addChild($dishesTaxon);
        $menu->addChild($dessertsTaxon);

        return $menu;
    }

    private function createAppetizers(TaxCategoryInterface $taxCategory)
    {
        $products = [];

        for ($i = 0; $i < 5; $i++) {
            $appetizer = $this->loadFixtures(__DIR__ . '/Resources/appetizer.yml', [
                'taxCategory' => $taxCategory,
            ], [
                'currentLocale' => $this->defaultLocale,
            ]);

            $appetizer['variant']->setName($appetizer['product']->getName());

            $products[] = $appetizer['product'];
        }

        return $products;
    }

    private function createDishes(TaxCategoryInterface $taxCategory)
    {
        $products = [];

        $options = $this->loadFixtures(__DIR__ . '/Resources/product_options.yml', [], [
            'currentLocale' => $this->defaultLocale,
        ]);

        for ($i = 0; $i < 5; $i++) {

            $dish = $this->loadFixtures(__DIR__ . '/Resources/dish.yml', [
                'taxCategory' => $taxCategory,
            ], [
                'currentLocale' => $this->defaultLocale,
            ]);

            $dish['variant']->setName($dish['product']->getName());

            $dish['product']->addOption($options['accompaniments']);
            $dish['product']->addOption($options['drinks']);

            $products[] = $dish['product'];
        }

        return $products;
    }

    private function createDesserts(TaxCategoryInterface $taxCategory)
    {
        $products = [];

        for ($i = 0; $i < 5; $i++) {
            $dessert = $this->loadFixtures(__DIR__ . '/Resources/dessert.yml', [
                'taxCategory' => $taxCategory,
            ], [
                'currentLocale' => $this->defaultLocale,
            ]);

            $dessert['variant']->setName($dessert['product']->getName());

            $products[] = $dessert['product'];
        }

        return $products;
    }

    private function createRandomTimeRange($min, $max)
    {
        [$closingHour, $closingMinute] = explode(':', $max);
        [$openingHour, $openingMinute] = explode(':', $min);

        $closing = new \DateTime();
        $closing->setTime($closingHour, $closingMinute);

        $opening = new \DateTime();
        $opening->setTime($openingHour, $openingMinute);

        $increment = mt_rand(0, 5) * 15;
        $decrement = mt_rand(0, 5) * 15;

        $opening->modify("+{$increment} minutes");
        $closing->modify("-{$decrement} minutes");

        return sprintf('%s-%s', $opening->format('H:i'), $closing->format('H:i'));
    }

    private function createPricingRuleSet(Entity\Store $store)
    {
        $pricingRuleSet = new Entity\Delivery\PricingRuleSet();
        $pricingRuleSet->setName('Tarification de '.$store->getName());

        $distances = [];
        $prices = [];

        for ($i = 1; $i <= 6; $i++) {
            array_push($distances, random_int(1, 20) * 1000);
        }

        for ($i = 1; $i <= 5; $i++) {
            array_push($prices, random_int(500, 2000));
        }

        sort($distances);
        sort($prices);

        foreach ($prices as $k => $price) {
            $pricingRule = new Entity\Delivery\PricingRule();
            $pricingRule->setPosition($k);
            $pricingRule->setExpression('distance in '.$distances[$k].'..'.$distances[$k+1]);
            $pricingRule->setPrice($price);
            $pricingRule->setRuleSet($pricingRuleSet);
            $pricingRuleSet->getRules()->add($pricingRule);
        };


        return $pricingRuleSet;
    }

    private function createStore(Entity\Address $address)
    {
        $shop = new Entity\Store();

        $phoneNumber = $this->phoneNumberUtil->getExampleNumber(strtoupper($this->country));

        $shop->setEnabled(true);
        $shop->setTelephone($phoneNumber);
        $shop->addAddress($address);
        $shop->setAddress($address);
        $shop->setName($this->faker->storeName);

        return $shop;
    }

    private function createRestaurant(Entity\Address $address, TaxCategoryInterface $taxCategory)
    {
        $contract = new Entity\Contract();
        $contract->setFlatDeliveryPrice(350);
        $contract->setCustomerAmount(350);
        $contract->setFeeRate(0);

        $restaurant = new Entity\LocalBusiness();

        $phoneNumber = $this->phoneNumberUtil->getExampleNumber(strtoupper($this->country));

        $restaurant->setEnabled(true);
        $restaurant->setTelephone($phoneNumber);
        $restaurant->setAddress($address);
        $restaurant->setName($this->faker->restaurantName);
        $restaurant->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('09:30', '14:30'));
        $restaurant->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('19:30', '23:30'));
        $restaurant->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('08:30', '15:30'));
        $restaurant->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('19:00', '01:30'));
        $restaurant->setContract($contract);

        foreach ($restaurant->getFulfillmentMethods() as $fulfillmentMethod) {
            $fulfillmentMethod->setMinimumAmount(1500);
        }

        $appetizers = $this->createAppetizers($taxCategory);
        $dishes = $this->createDishes($taxCategory);
        $desserts = $this->createDesserts($taxCategory);

        foreach ($appetizers as $product) {
            $restaurant->addProduct($product);
            foreach ($product->getOptions() as $productOption) {
                $restaurant->addProductOption($productOption);
            }
        }
        foreach ($dishes as $product) {
            $restaurant->addProduct($product);
            foreach ($product->getOptions() as $productOption) {
                $restaurant->addProductOption($productOption);
            }
        }
        foreach ($desserts as $product) {
            $restaurant->addProduct($product);
            foreach ($product->getOptions() as $productOption) {
                $restaurant->addProductOption($productOption);
            }
        }

        $menuTaxon = $this->createMenuTaxon($appetizers, $dishes, $desserts);

        $restaurant->addTaxon($menuTaxon);
        $restaurant->setMenuTaxon($menuTaxon);

        return $restaurant;
    }

    private function createRestaurants(OutputInterface $output)
    {
        $foodTaxCategory =
            $this->taxCategoryRepository->findOneBy(['code' => 'BASE_REDUCED']);

        $em = $this->doctrine->getManagerForClass(Entity\LocalBusiness::class);

        for ($i = 1; $i <= self::$restaurantsToCreate; $i++) {

            $restaurant = $this->createRestaurant($this->faker->randomAddress, $foodTaxCategory);

            $em->persist($restaurant);

            $username = "resto_{$i}";
            $user = $this->createUser($username, [
                'password' => $username,
                'roles' => ['ROLE_RESTAURANT']
            ]);
            $user->addRestaurant($restaurant);

            if (($i % $this->batchSize) === 0) {

                $output->writeln('Creating restaurants… | Flushing data…');

                $em->flush();
                $em->clear();

                // As we have cleared the whole UnitOfWork, we need to restore the TaxCategory entity
                $foodTaxCategory = $this->taxCategoryRepository->findOneBy(['code' => 'BASE_REDUCED']);
            }
        }

        $em->flush();
    }

    private function createStores()
    {
        $em = $this->doctrine->getManagerForClass(Entity\Store::class);

        for ($i = 1; $i <= self::$storesToCreate; $i++) {
            $store = $this->createStore($this->faker->randomAddress);
            $pricingRuleSet = $this->createPricingRuleSet($store);
            $store->setPricingRuleSet($pricingRuleSet);
            $em->persist($store);
            $em->flush();

            $username = "store_{$i}";
            $user = $this->createUser($username, [
                'password' => $username,
                'roles' => ['ROLE_STORE']
            ]);
            $user->addStore($store);
        }

        $this->doctrine->getManagerForClass(Entity\Store::class)->flush();
    }

    private function createOrders()
    {
        //$user = $this->doctrine->getRepository(Entity\User::class)->findOneBy(['username' => 'user_1']);

        for ($i = 1; $i <= self::$foodTechOrdersToCreate; $i++) {
            $restaurant = $this->createdRestaurants[array_rand($this->createdRestaurants)];
            $user = $this->createdUsers[array_rand($this->createdUsers)];
            // Fetching the user again because if not, it fails with error:
            // "A new entity was found through the relationship 'AppBundle\Entity\User#channel' that was not configured to cascade persist operations for entity: Web."
            $user = $this->doctrine->getRepository(Entity\User::class)->findOneBy(['username' => $user->getUsername()]);

            $this->createFoodTechOrder('00'.$i, $restaurant, $user);
        }

        for ($i = 1; $i <= self::$packageDeliveryOrdersToCreate; $i++) {
            $store = $this->createdStores[array_rand($this->createdStores)];
            //TODO: implement
        }
    }

    private function createFoodTechOrder($id, $restaurant, $user)
    {
        $em = $this->doctrine->getManagerForClass(Entity\Sylius\Order::class);
        $order = new Entity\Sylius\Order();
        $order->setNumber($id);

        $order->setRestaurant($restaurant);

        $order->setCustomer($user->getCustomer());
        $order->setShippingAddress($user->getCustomer()->getAddresses()->first());

        $from = new \DateTime();
        $from->setTime(20, 0);
        $to = new \DateTime();
        $to->setTime(20, 10);
        $order->setShippingTimeRange(TsRange::create($from, $to));

        $this->orderProcessor->process($order);

        $order->setState('new');

        $em->persist($order);
        $em->flush();

        $this->createTasks->__invoke(new OrderAccepted($order));
        $em->flush();
    }
}
