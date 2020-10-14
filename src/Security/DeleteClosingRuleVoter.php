<?php

namespace AppBundle\Security;

use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\LocalBusiness;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DeleteClosingRuleVoter extends Voter
{
    // these strings are just invented: you can use anything
    const DELETE = 'delete';

    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, [self::DELETE])) {
            return false;
        }

        // only vote on `ClosingRule` objects
        if (!$subject instanceof ClosingRule) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return false;
        }

        $qb = $this->objectManager->getRepository(LocalBusiness::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.closingRules', 'cr')
            ->where('cr.id = :closing_rule')
            ->setParameter('closing_rule', $subject)
            ->setMaxResults(1);

        $restaurant = $qb->getQuery()->getOneOrNullResult();

        if ($restaurant) {

            return $user->ownsRestaurant($restaurant);
        }

        return false;
    }
}
