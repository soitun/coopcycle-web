<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Form\DeliveryType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class EmbedController extends Controller
{
    private function createDeliveryForm(Delivery $delivery)
    {
        return $this->createForm(DeliveryType::class, $delivery, [
            'free_pricing' => false,
        ]);
    }

    private function getPricingRuleSet()
    {
        $pricingRuleSet = null;
        try {
            $pricingRuleSetId = $this->get('craue_config')->get('embed.delivery.pricingRuleSet');
            if ($pricingRuleSetId) {
                $pricingRuleSet = $this->getDoctrine()
                    ->getRepository(PricingRuleSet::class)
                    ->find($pricingRuleSetId);
            }
        } catch (\RuntimeException $e) {}

        return $pricingRuleSet;
    }

    /**
     * @Route("/embed/delivery", name="embed_delivery")
     * @Template
     */
    public function deliveryAction()
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $delivery = new Delivery();
        $delivery->setDate(new \DateTime('+1 day'));

        $form = $this->createDeliveryForm($delivery);

        return $this->render('@App/Embed/Delivery/stepOne.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/embed/delivery/account", name="embed_delivery_account")
     * @Template
     */
    public function deliveryAccountAction(Request $request)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $deliveryManager = $this->get('coopcycle.delivery.manager');

        $delivery = new Delivery();
        $form = $this->createDeliveryForm($delivery);

        $form->handleRequest($request);

        $price = 0;

        if ($form->isSubmitted()) {

            $delivery = $form->getData();

            $data = $this->get('routing_service')->getRawResponse(
                $delivery->getOriginAddress()->getGeo(),
                $delivery->getDeliveryAddress()->getGeo()
            );

            $distance = $data['routes'][0]['distance'];
            $duration = $data['routes'][0]['duration'];

            // var_dump('VALID = ' . var_export($form->isValid(), true));

            $delivery->setDistance((int) $distance);
            $delivery->setDuration((int) $duration);
            $price = $deliveryManager->getPrice($delivery, $pricingRuleSet);

            // var_dump('DISTANCE = ' . $distance);
            // var_dump('PRICE = ' . $price);

            if (!$form->isValid()) {
                foreach ($form->getErrors() as $error) {
                    var_dump($error->getMessage());
                }
            }
        }

        // var_dump($price);

        return $this->render('@App/Embed/Delivery/stepTwo.html.twig', [
            'form' => $form->createView(),
            'price' => $price,
            'delivery' => $delivery,
        ]);
    }
}
