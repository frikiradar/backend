<?php

namespace App\Repository;

use App\Entity\Page;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method Page[]    findAll()
 * @method Page[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Page::class);
        $this->em = $entityManager;
        $this->slugs = [];
    }

    // /**
    //  * @return Page[] Returns an array of Page objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Page
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findPages(User $user)
    {
        $userTags = $user->getTags();
        $userSlugs = [];
        foreach ($userTags as $tag) {
            if ($tag->getSlug()) {
                $userSlugs[] = $tag->getSlug();
            }
        }

        $tags = $this->em->getRepository("App:Tag")->createQueryBuilder('t')
            ->select(array(
                't.slug',
                'COUNT(t) total'
            ))
            ->andWhere('t.slug IN (:slugs)')
            ->groupBy('t.slug')
            ->orderBy('total', 'DESC')
            ->setParameter('slugs', $userSlugs)
            ->getQuery()
            ->getResult();

        $slugs = [];
        foreach ($tags as $tag) {
            $slugs[$tag['slug']] = $tag['total'];
        }
        $this->slugs = $slugs;

        $pages = $this->createQueryBuilder('p')
            ->where('p.slug IN (:slugs)')
            ->setParameter('slugs', array_keys($slugs))
            ->getQuery()
            ->getResult();

        usort($pages, function ($a, $b) {
            return $this->slugs[$b->getSlug()] <=> $this->slugs[$a->getSlug()];
        });

        return $pages;
    }
}
