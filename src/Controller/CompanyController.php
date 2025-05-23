<?php
declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Form\Company\RegistrationType;
use AppBundle\Message\Email;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Class CompanyController
 */
#[Route(path: '/company')]
class CompanyController
{
    public function __construct(
        private MessageBusInterface $bus,
        private FormFactoryInterface $formFactory,
        private Environment $twig,
        private TranslatorInterface $translator,
        private SettingsManager $settingsManager,
        private EmailManager $emailManager,
        private RouterInterface $router)
    {}

    #[Route(path: '/register', name: 'company_registration')]
    public function registerAction(Request $request)
    {

        $form = $this->formFactory->create(RegistrationType::class);
        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $companyRegistration = $form->getData();

            $message = $this->emailManager->createHtmlMessage(
                $this->translator->trans('registration.company', [], 'emails'),
                $this->twig->render('emails/company/request_registration.mjml.twig', [
                    'registration' => $companyRegistration
                ])
            );

            $this->bus->dispatch(new Email(
                $message,
                $this->settingsManager->get('administrator_email')
            ));

            return new RedirectResponse($this->router->generate('company_registration'));
        }

        return new Response($this->twig->render(
            'company/register.html.twig',
            [
                'form' => $form->createView(),
            ]
        ));
    }
}
