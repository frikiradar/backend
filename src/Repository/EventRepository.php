<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Event::class);
        $this->em = $entityManager;
    }

    // /**
    //  * @return Event[] Returns an array of Event objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findUserEvents(User $user)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.creator = :user')
            ->setParameter('user', $user)
            ->orderBy('e.date', 'asc')
            ->getQuery()
            ->getResult();
    }

    public function findSuggestedEvents(User $user)
    {
        $today = new \DateTime;
        $frikiradar = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
        $officialEvents = $this->createQueryBuilder('e')
            ->where('e.creator = :user')
            ->andWhere('e.date > :today')
            ->setParameter('user', $frikiradar)
            ->setParameter('today', $today)
            ->orderBy('e.date', 'asc')
            ->getQuery()
            ->getResult();

        $tags = $user->getTags();
        $slugs = [];
        foreach ($tags as $tag) {
            if ($tag->getSlug()) {
                $slugs[] = $tag->getSlug();
            }
        }

        $likeEvents = $this->createQueryBuilder('e')
            ->where('e.slug IN (:slugs)')
            ->andWhere('e.date > :today')
            ->orderBy('e.date', 'asc')
            ->setParameter('slugs', $slugs)
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        return [...$officialEvents, ...$likeEvents];
    }

    public function findOnlineEvents(User $user)
    {
        $today = new \DateTime;

        return $this->createQueryBuilder('e')
            ->where('e.date > :today')
            ->andWhere("e.type = 'online'")
            ->orderBy('e.date', 'asc')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }

    public function findNearEvents(User $user)
    {
        $today = new \DateTime;

        return $this->createQueryBuilder('e')
            ->where('e.country = :country')
            ->orWhere('e.city LIKE :city')
            ->andWhere('e.date > :today')
            ->orderBy('e.date', 'asc')
            ->setParameter('today', $today)
            ->setParameter('city', '%' . $user->getCity() . '%')
            ->setParameter('country', $user->getCountry())
            ->getQuery()
            ->getResult();
    }
}
