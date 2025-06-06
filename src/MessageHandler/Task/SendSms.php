<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Message\Sms;
use AppBundle\Service\SettingsManager;
use Hashids\Hashids;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler()]
class SendSms
{
    public function __construct(
        private SettingsManager $settingsManager,
        private OrderRepository $orderRepository,
        private MessageBusInterface $messageBus,
        private PhoneNumberUtil $phoneNumberUtil,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private string $secret)
    {}

    public function __invoke(TaskStarted $event)
    {
        if (!$this->settingsManager->canSendSms()) {
            return;
        }

        $task = $event->getTask();

        // Skip if this is related to foodtech
        if ($order = $this->orderRepository->findOneByTask($task)) {
            if ($order->hasVendor()) {
                return;
            }
        }

        $telephone = $task->getAddress()->getTelephone();

        if (!$telephone) {
            return;
        }

        $telephone = $this->phoneNumberUtil->format($telephone, PhoneNumberFormat::E164);

        $delivery = $task->getDelivery();

        if (null !== $delivery) {

            $hashids = new Hashids($this->secret, 8);

            $trackingUrl = $this->urlGenerator->generate('public_delivery', [
                'hashid' => $hashids->encode($delivery->getId())
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $text = $this->translator->trans('sms.with_tracking', [
                '%address%' => $task->getAddress()->getStreetAddress(),
                '%link%' => $trackingUrl
            ]);

        } else {
            $text = $this->translator->trans('sms.simple', [
                '%address%' => $task->getAddress()->getStreetAddress()
            ]);
        }

        $this->messageBus->dispatch(
            new Sms($text, $telephone)
        );
    }
}
