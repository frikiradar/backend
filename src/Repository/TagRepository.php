<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    //    /**
    //     * @return Tag[] Returns an array of Tag objects
    //     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
     */

    /*
    public function findOneBySomeField($value): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
     */

    public function searchTags(string $query, string $category)
    {
        $em = $this->getEntityManager();

        return $this->createQueryBuilder('t')
            ->select(array(
                't.name',
                'COUNT(t) total'
            ))
            ->where('t.name LIKE :name')
            ->andWhere('t.category = (SELECT c.id FROM App:Category c WHERE c.name = :category)')
            ->groupBy('t.name')
            ->orderBy('total', 'DESC')
            ->setMaxResults(3)
            ->setParameters(array(
                'name' => '%' . $query . '%',
                'category' => $category
            ))
            ->getQuery()
            ->getResult();
    }

    public function setTagsSlug(Tag $tag, string $slug)
    {
        $dql = "UPDATE App:Tag t SET t.slug = :slug WHERE t.name = :name AND t.category = :category";
        return $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('slug', $slug)
            ->setParameter('name', $tag->getName())
            ->setParameter('category', $tag->getCategory()->getId())
            ->execute();
    }

    public function countTag(string $name, string $category)
    {
        $em = $this->getEntityManager();

        return $this->createQueryBuilder('t')
            ->select(array(
                'COUNT(t) total'
            ))
            ->where('t.name = :name')
            ->andWhere('t.category = (SELECT c.id FROM App:Category c WHERE c.name = :category)')
            ->groupBy('t.name')
            ->orderBy('total', 'DESC')
            ->setMaxResults(1)
            ->setParameters(array(
                'name' => $name,
                'category' => $category
            ))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllGroupedTags()
    {
        $em = $this->getEntityManager();

        return $this->createQueryBuilder('t')
            ->select(array(
                't.name',
                't.slug',
                'c.name category'
            ))
            ->leftJoin('t.category', 'c')
            ->groupBy('t.name')
            ->groupBy('c.name')
            ->where('c.name IN (' . "'films', 'games'" . ')')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
