<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Validator\Constraints\UserWithSameEmailNotExists as AssertUserWithSameEmailNotExists;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints as Assert;

class BusinessAccountType extends AbstractType
{
    private $authorizationChecker;
    private $objectManager;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        EntityManagerInterface $objectManager)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->objectManager = $objectManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'registration.step.company.name']);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('restaurants', CollectionType::class, [
                    'entry_type' => EntityType::class,
                    'entry_options' => [
                        'label' => false,
                        'class' => LocalBusiness::class,
                        'choice_label' => 'name',
                    ],
                    'label' => 'form.business_account.restaurants.label',
                    'allow_add' => true,
                    'allow_delete' => true,
                    'by_reference' => false,
                ]);
        } else {
            $builder
                ->add('address', AddressType::class, [
                    'with_widget' => true,
                    'with_description' => false,
                    'label' => 'registration.company.address',
                ])
                ->add('differentAddressForBilling', CheckboxType::class, [
                    'label' => 'registration.company.address.different.for.billing',
                    'required' => false,
                ])
                ->add('billingAddress', AddressType::class, [
                    'with_widget' => true,
                    'with_description' => false,
                    'label' => 'registration.company.billing.address',
                ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $businessAccount = $event->getData();
            $form = $event->getForm();

            if (null !== $businessAccount->getId()) {
                if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $businessAccountInvitation = $this->objectManager->getRepository(BusinessAccountInvitation::class)
                        ->findOneBy([
                            'businessAccount' => $businessAccount,
                        ]);
                    if (null !== $businessAccountInvitation) {
                        $form->add('managerEmail', EmailType::class, [
                            'label' => 'form.business_account.manager.email.label',
                            'help' => 'form.business_account.manager.email_sent.help',
                            'disabled' => true,
                            'required'=> false,
                            'mapped'=> false,
                            'data' => $businessAccountInvitation->getInvitation()->getEmail(),
                        ]);
                    }
                }
            } else {
                if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $form->add('managerEmail', EmailType::class, [
                        'constraints' => [
                            new Assert\NotBlank(),
                            new Assert\Email([
                                'mode' => Assert\Email::VALIDATION_MODE_STRICT,
                            ]),
                            new AssertUserWithSameEmailNotExists(),
                        ],
                        'label' => 'form.business_account.manager.email.label',
                        'help' => 'form.business_account.manager.email.help',
                        'required'=> true,
                        'mapped'=> false,
                    ]);
                }
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $businessAccount = $form->getData();

            if (!$businessAccount->getDifferentAddressForBilling()) {
                $businessAccount->setBillingAddress(null);
            }
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if (isset($view['restaurants'])) {
            usort($view['restaurants']->children, function (FormView $a, FormView $b) {

                /** @var LocalBusiness $objectA */
                $objectA = $a->vars['data'];
                /** @var LocalBusiness $objectB */
                $objectB = $b->vars['data'];

                return ($objectA->getName() < $objectB->getName()) ? -1 : 1;
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => BusinessAccount::class,
        ));
    }

    public function getBlockPrefix() {
		return 'company';
	}
}
