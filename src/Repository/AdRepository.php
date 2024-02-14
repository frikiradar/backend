<?php

namespace App\Repository;

use App\Entity\Ad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ad>
 *
 * @method Ad|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ad|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ad[]    findAll()
 * @method Ad[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ad::class);
    }

    //    /**
    //     * @return Ad[] Returns an array of Ad objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Ad
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function save(Ad $ad): void
    {
        $this->_em->persist($ad);
        $this->_em->flush();
    }

    public function remove(Ad $ad): void
    {
        $this->_em->remove($ad);
        $this->_em->flush();
    }

    public function getActiveAds(String $country): array
    {
        // cogemos todos los anuncios activos, para eso vemos que
        // start_date sea menor o igual que hoy y end_date sea mayor o igual que hoy
        // también puede ser que end_date sea null, en ese caso no hay fecha de fin
        // o que start_date y end_date sea null, en ese caso el anuncio está siempre activo

        return $this->createQueryBuilder('a')
            ->andWhere('a.start_date <= :today OR a.start_date IS NULL')
            ->andWhere('a.end_date >= :today OR a.end_date IS NULL')
            ->andWhere('a.country = :country OR a.country IS NULL')
            ->setParameter('today', new \DateTime())
            ->setParameter('country', $country)
            ->getQuery()
            ->getResult();
    }
}
