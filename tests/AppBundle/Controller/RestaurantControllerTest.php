<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Controller\RestaurantController;
use AppBundle\Domain\Order\Event;
use AppBundle\Entity\Address;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Security\OrderAccessTokenManager;
use AppBundle\Service\NullLoggingUtils;
use AppBundle\Service\TimingRegistry;
use AppBundle\Sylius\Cart\RestaurantResolver;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Utils\OptionsPayloadConverter;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\RestaurantFilter;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository as SyliusEntityRepository;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class RestaurantControllerTest extends WebTestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        // FIXME
        // Find out why env is not test sometimes
        self::bootKernel(['environment' => 'test']);

        $this->objectManager = $this->prophesize(EntityManagerInterface::class);
        $this->uploaderHelper = $this->prophesize(UploaderHelper::class);
        $this->validator = $this->prophesize(ValidatorInterface::class);
        $this->productRepository = $this->prophesize(SyliusEntityRepository::class);
        $this->orderItemRepository = $this->prophesize(RepositoryInterface::class);
        $this->orderItemFactory = $this->prophesize(FactoryInterface::class);
        $this->productVariantResolver = $this->prophesize(LazyProductVariantResolverInterface::class);
        $this->optionsPayloadConverter = $this->prophesize(OptionsPayloadConverter::class);
        $this->orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $this->orderModifier = $this->prophesize(OrderModifierInterface::class);
        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);
        $this->restaurantResolver = $this->prophesize(RestaurantResolver::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $this->restaurantFilter = $this->prophesize(RestaurantFilter::class);
        $this->timingRegistry = $this->prophesize(TimingRegistry::class);
        $this->orderAccessTokenManager = $this->prophesize(OrderAccessTokenManager::class);

        $this->localBusinessRepository = $this->prophesize(LocalBusinessRepository::class);

        $this->objectManager
            ->getRepository(LocalBusiness::class)
            ->willReturn($this->localBusinessRepository->reveal());

        // Use the "real" serializer
        $this->serializer = self::getContainer()->get('serializer');

        $this->eventDispatcher
            ->dispatch(Argument::type('object'), Argument::type('string'))
            ->will(function ($args) {

                return $args[0];
            });

        $container = $this->prophesize(ContainerInterface::class);

        $parameterBag = $this->prophesize(ParameterBagInterface::class);
        $parameterBag->get('country_iso')->willReturn('fr');
        $parameterBag->get('sylius_cart_restaurant_session_key_name')->willReturn('foo');

        $container
            ->has('parameter_bag')
            ->willReturn(true);
        $container
            ->get('parameter_bag')
            ->willReturn($parameterBag->reveal());

        $eventBus = $this->prophesize(MessageBusInterface::class);
        $eventBus
            ->dispatch(Argument::type(Event::class))
            ->will(function ($args) {
                return new Envelope($args[0]);
        });


        $jwtTokenManager = $this->prophesize(JWTTokenManagerInterface::class);

        $this->controller = new RestaurantController(
            $this->objectManager->reveal(),
            $this->validator->reveal(),
            $this->productRepository->reveal(),
            $this->orderItemRepository->reveal(),
            $this->orderItemFactory->reveal(),
            $this->productVariantResolver->reveal(),
            $this->orderItemQuantityModifier->reveal(),
            $this->orderModifier->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->serializer,
            $this->restaurantFilter->reveal(),
            $this->restaurantResolver->reveal(),
            $eventBus->reveal(),
            $jwtTokenManager->reveal(),
            $this->timingRegistry->reveal(),
            $this->orderAccessTokenManager->reveal(),
            new NullLogger(),
            new NullLoggingUtils(),
            'test',
        );

        $this->controller->setContainer($container->reveal());
    }

    private function setId($object, $id)
    {
        $property = new \ReflectionProperty($object, 'id');
        $property->setAccessible(true);
        $property->setValue($object, $id);
    }

    public function testAddProductToCartAction(): void
    {
        $productCode = Uuid::uuid4()->toString();
        $productOptionValueCode = Uuid::uuid4()->toString();

        $session = new Session(new MockArraySessionStorage());

        $request = Request::create('/restaurant/{id}/cart/product/{code}', 'POST', [
            'options' => [
                [
                    'code' => $productOptionValueCode,
                    'quantity' => 3,
                ]
            ]
        ]);
        $request->setSession($session);

        $restaurantAddress = new Address();
        $restaurantAddress->setGeo(new GeoCoordinates(48.856613, 2.352222));
        $this->setId($restaurantAddress, 1);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);
        $restaurant->setContract(new Contract());
        $this->setId($restaurant, 1);

        // Don't use a mock for the cart
        // because annotation reader won't work (for serialization)
        // https://github.com/doctrine/annotations/issues/186
        $cart = new Order();
        $cart->setRestaurant($restaurant);

        $this->restaurantResolver
            ->resolve()
            ->willReturn($restaurant);
        $this->restaurantResolver
            ->accept(Argument::type(OrderInterface::class))
            ->willReturn(true);

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);
        $product->hasOptions()->willReturn(true);

        $restaurant->getProducts()->add($product->reveal());

        $this->localBusinessRepository->find(1)->willReturn($restaurant);

        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext
            ->getCart()
            ->willReturn($cart);

        $this->orderTimeHelper
            ->getTimeInfo(Argument::type(OrderInterface::class))
            ->willReturn([]);

        $this->optionsPayloadConverter->convert($product->reveal(), [
                [
                    'code' => $productOptionValueCode,
                    'quantity' => 3,
                ]
            ])
            ->willReturn(new \SplObjectStorage());

        $this->productRepository
            ->findOneBy([
                'code' => $productCode,
            ])
            ->willReturn($product->reveal());

        $orderItem = $this->prophesize(OrderItemInterface::class);

        $this->orderItemFactory
            ->createNew()
            ->willReturn($orderItem->reveal());

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getPrice()->willReturn(900);

        $this->productVariantResolver
            ->getVariantForOptionValues($product->reveal(), Argument::type(\SplObjectStorage::class))
            ->willReturn($variant->reveal());

        $errors = $this->prophesize(ConstraintViolationListInterface::class);
        $errors->count()->willReturn(0);
        $errors->rewind()->shouldBeCalled();
        $errors->valid()->shouldBeCalled();

        $this->validator
            ->validate(Argument::type('object'), Argument::any())
            ->will(function ($args) use ($cart, $errors) {

                if ($args[0] === $cart) {

                    return $errors->reveal();
                }

                return $errors->reveal();
            });

        $response = $this->controller->addProductToCartAction(1, $productCode, $request,
            $cartContext->reveal(),
            $this->optionsPayloadConverter->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('restaurantTiming', $data);
        $this->assertArrayHasKey('cart', $data);
        $this->assertNotNull($data['cartTiming']);
        $this->assertArrayHasKey('errors', $data);

        $this->assertArrayHasKey('vendor', $data['cart']);

        $vendor = $data['cart']['vendor'];

        $this->assertEquals(['latlng' => [48.856613, 2.352222]], $vendor['address']);
        $this->assertEquals(['delivery'], $vendor['fulfillmentMethods']);
        $this->assertFalse($vendor['variableCustomerAmountEnabled']);

        $this->objectManager->persist($cart)->shouldHaveBeenCalled();
        $this->objectManager->flush()->shouldHaveBeenCalled();
    }

    public function testAddProductToCartActionWithRestaurantMismatch(): void
    {
        $productCode = Uuid::uuid4()->toString();
        $productOptionValueCode = Uuid::uuid4()->toString();

        $session = new Session(new MockArraySessionStorage());

        $request = Request::create('/restaurant/{id}/cart/product/{code}', 'POST', [
            'options' => [
                [
                    'code' => $productOptionValueCode,
                    'quantity' => 3,
                ]
            ]
        ]);
        $request->setSession($session);

        $restaurantAddress = new Address();
        $restaurantAddress->setGeo(new GeoCoordinates(48.856613, 2.352222));
        $this->setId($restaurantAddress, 1);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);
        $restaurant->setContract(new Contract());
        $this->setId($restaurant, 1);

        $otherRestaurant = new Restaurant();
        $otherRestaurant->setAddress($restaurantAddress);
        $this->setId($otherRestaurant, 2);

        // Don't use a mock for the cart
        // because annotation reader won't work (for serialization)
        // https://github.com/doctrine/annotations/issues/186
        $cart = new Order();
        $cart->setRestaurant($otherRestaurant);

        $this->restaurantResolver
            ->resolve()
            ->willReturn($otherRestaurant);
        $this->restaurantResolver
            ->accept(Argument::type(OrderInterface::class))
            ->willReturn(false);

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);
        $product->hasOptions()->willReturn(true);

        $restaurant->getProducts()->add($product->reveal());

        $this->localBusinessRepository->find(1)->willReturn($restaurant);

        $cartContext = $this->prophesize(CartContextInterface::class);
        $cartContext
            ->getCart()
            ->willReturn($cart);

        $this->orderTimeHelper
            ->getTimeInfo(Argument::type(OrderInterface::class))
            ->willReturn([]);

        $this->productRepository
            ->findOneBy([
                'code' => $productCode,
            ])
            ->willReturn($product->reveal());

        $errors = $this->prophesize(ConstraintViolationListInterface::class);

        $this->validator
            ->validate(Argument::type('object'), Argument::any())
            ->will(function ($args) use ($cart, $errors) {

                if ($args[0] === $cart) {

                    return $errors->reveal();
                }

                $errs = new ConstraintViolationList();
                $errs->add(new ConstraintViolation('Restaurant mismatch', null, [], '', 'restaurant', null));

                return $errs;
            });

        $response = $this->controller->addProductToCartAction(1, $productCode, $request,
            $cartContext->reveal(),
            $this->optionsPayloadConverter->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('restaurantTiming', $data);
        $this->assertArrayHasKey('cart', $data);
        $this->assertNull($data['cartTiming']);
        $this->assertArrayHasKey('errors', $data);

        $this->assertArrayHasKey('restaurant', $data['errors']);
        $this->assertCount(1, $data['errors']['restaurant']);
        $this->assertEquals('Restaurant mismatch', $data['errors']['restaurant'][0]['message']);
    }
}
