<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\User;
use AppBundle\Event\BeneficiaryCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class BeneficiaryInitializationSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;
    private $defaultRoles;


    public function __construct(EntityManagerInterface $em, array $defaultRoles)
    {
        $this->em = $em;
        $this->defaultRoles = $defaultRoles;
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::SUBMIT => 'postInitializeMembership',
            FOSUserEvents::REGISTRATION_SUCCESS => 'setupRoles'
        );
    }

    public function onBeforePersist(BeneficiaryCreatedEvent $event)
    {
        $this->makeUser($event->getBeneficiary());
    }

    public function postInitializeMembership(FormEvent $event)
    {
        $this->makeUser($event->getData());
    }

    public function setupRoles(\FOS\UserBundle\Event\FormEvent $event)
    {
        /** @var User $user */
        $user = $event->getForm()->getData();
        $user->setRoles($this->defaultRoles);
    }

    private function makeUser(Beneficiary $beneficiary){
        if ($beneficiary) {
            if (!$beneficiary->getUser()) {
                $user = new User();
                $beneficiary->setUser($user);
            }

            if (!$beneficiary->getUser()->getUsername()) {

                $username = $this->generateUsername($beneficiary);
                $beneficiary->getUser()->setUsername($username);
            }

            if (!$beneficiary->getUser()->getPassword()) {
                $password = User::randomPassword();
                $beneficiary->getUser()->setPassword($password);
            }
        }
    }

    private function generateUsername(Beneficiary $beneficiary)
    {
        if (!$beneficiary->getFirstname() || !$beneficiary->getLastname()) {
            return null;
        }
        $username = User::makeUsername($beneficiary->getFirstname(), $beneficiary->getLastname());
        $qb = $this->em->createQueryBuilder();
        $usernames = $qb->select('u')->from('AppBundle\Entity\User', 'u')
            ->where($qb->expr()->like('u.username', $qb->expr()->literal($username . '%')))
            ->orderBy('u.username', 'DESC')
            ->getQuery()
            ->getResult();

        if (count($usernames)) {
            $count = 1;
            $first = $usernames[0]->getUsername();
            if(preg_match_all('/\d+/', $first, $numbers)) {
                $count = end($numbers[0]) + 1;
            }
            $username = $username . + $count;
        }
        return $username;
    }
}