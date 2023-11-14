<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Config\Definition\Exception\Exception;

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

    public function findPublicEvent(int $id)
    {
        $dql = $this->createQueryBuilder('e')
            ->select(array(
                'e.id',
                'e.title',
                'e.description',
                'e.image',
                'e.date',
                'e.date_end',
                'e.address',
                'e.city',
                'e.contact_email',
                'e.contact_phone',
                'e.minage',
                'e.slug',
                'e.status',
                'e.type',
                'e.url',
            ))
            ->where('e.id = :id');

        $event = $dql->setParameters(array(
            'id' => $id
        ))->getQuery()->getOneOrNullResult();

        if (!is_null($event)) {
            return $event;
        } else {
            throw new Exception('Evento no encontrado');
        }
    }

    public function findUserEvents(User $user)
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.participants', 'p')
            ->where('e.creator = :user')
            ->orWhere('p.id = :id')
            ->orWhere('e.user = :user')
            ->setParameter('user', $user)
            ->setParameter('id', $user->getId())
            ->orderBy('e.date', 'asc')
            ->getQuery()
            ->getResult();
    }

    public function findSuggestedEvents(User $user)
    {
        $today = new \DateTime;
        $frikiradar = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('username' => 'frikiradar'));

        $tags = $user->getTags();
        $slugs = [];
        foreach ($tags as $tag) {
            if ($tag->getSlug()) {
                $slugs[] = $tag->getSlug();
            }
        }

        return $this->createQueryBuilder('e')
            ->where('e.creator = :frikiradar')
            ->orWhere('e.slug IN (:slugs)')
            ->orWhere('e.creator <> :frikiradar AND e.slug IS NULL')
            ->andWhere('e.date > :today')
            ->andWhere("e.status <> 'cancelled'")
            ->andWhere('e.user IS NULL')
            ->setParameter('frikiradar', $frikiradar)
            ->setParameter('today', $today)
            ->setParameter('slugs', $slugs)
            ->orderBy('e.creator', 'asc')
            ->addOrderBy('e.date', 'asc')
            ->getQuery()
            ->getResult();
    }

    public function findOnlineEvents(User $user)
    {
        $today = new \DateTime;

        return $this->createQueryBuilder('e')
            ->where('e.date > :today')
            ->andWhere("e.type = 'online'")
            ->andWhere("e.status <> 'cancelled'")
            ->andWhere('e.user IS NULL')
            ->orderBy('e.date', 'asc')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }

    public function findNearEvents(User $user)
    {
        $today = new \DateTime;

        if ($user->getCountry() || $user->getCity()) {
            return $this->createQueryBuilder('e')
                ->where('e.country = :country')
                ->orWhere('e.city LIKE :city')
                ->andWhere('e.date > :today')
                ->andWhere("e.status <> 'cancelled'")
                ->andWhere('e.user IS NULL')
                ->orderBy('e.date', 'asc')
                ->setParameter('today', $today)
                ->setParameter('city', '%' . $user->getCity() . '%')
                ->setParameter('country', $user->getCountry())
                ->getQuery()
                ->getResult();
        } else {
            return [];
        }
    }

    public function findSlugEvents(string $slug)
    {
        $today = new \DateTime;

        return $this->createQueryBuilder('e')
            ->where('e.slug = :slug')
            ->andWhere('e.date > :today')
            ->andWhere("e.status <> 'cancelled'")
            ->orderBy('e.date', 'asc')
            ->setParameter('today', $today)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getResult();
    }

    public function findNextEvents()
    {
        $today = new \DateTime;
        $tomorrow = $today->modify('+1 day');

        return $this->createQueryBuilder('e')
            ->where('e.date > :today')
            ->andWhere('e.date < :tomorrow')
            ->andWhere("e.status <> 'cancelled'")
            ->orderBy('e.date', 'asc')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getResult();
    }
}
