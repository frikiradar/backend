<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository
{
    private $em;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Tag::class);
        $this->em = $entityManager;
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

    public function save(Tag $tag): void
    {
        $this->getEntityManager()->persist($tag);
        $this->getEntityManager()->flush();
    }

    public function remove(Tag $tag): void
    {
        $this->getEntityManager()->remove($tag);
        $this->getEntityManager()->flush();
    }

    public function searchTags(string $query, string $category)
    {
        $dql = $this->createQueryBuilder('t')
            ->select(array(
                't.name',
                't.slug',
                'COUNT(t) total'
            ))
            ->where('t.name LIKE :name')
            ->andWhere('t.category = (SELECT c.id FROM App:Category c WHERE c.name = :category)')
            ->orderBy('total', 'DESC');

        if (in_array($category, ['films', 'games'])) {
            $dql->andWhere('t.slug IS NOT NULL')
                ->groupBy('t.slug');
        } else {
            $dql->groupBy('t.name');
        }
        $tags = $dql
            ->setMaxResults(5)
            ->setParameters(array(
                'name' => '%' . $query . '%',
                'category' => $category
            ))
            ->getQuery()
            ->getResult();

        if (in_array($category, ['films', 'games'])) {
            // buscamos páginas que contengan el tag para tener el nombre y cover
            $pages = $this->em->getRepository(\App\Entity\Page::class)->createQueryBuilder('p')
                ->select(array(
                    'p.name',
                    'p.slug',
                    'p.cover',
                ))
                ->where('p.slug IN (:slugs)')
                ->andWhere('p.category = :category')
                ->andWhere('p.cover IS NOT NULL')
                ->setParameters(array(
                    'slugs' => array_column($tags, 'slug'),
                    'category' => $category
                ))
                ->getQuery()
                ->getResult();

            foreach ($tags as $key => $tag) {
                foreach ($pages as $page) {
                    if ($tag['slug'] == $page['slug']) {
                        $tags[$key]['name'] = $page['name'];
                        $tags[$key]['image'] = $page['cover'];
                        $tags[$key]['slug'] = $page['slug'];
                        $tags[$key]['category'] = $category;
                    }
                }
            }
        } else {
            foreach ($tags as $key => $tag) {
                $tags[$key]['category'] = $category;
            }
        }
        return $tags;
    }

    public function setTagsSlug(Tag $tag, string $slug)
    {
        $dql = "SELECT t FROM App:Tag t WHERE t.name = :name AND t.category = :category";

        $tags = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('name', $tag->getName())
            ->setParameter('category', $tag->getCategory()->getId())
            ->getResult();

        if (empty($tags)) {
            return;
        }

        foreach ($tags as $tag) {
            $tag->setSlug($slug);
            $this->save($tag);
        }
    }

    public function countTag(string $slug, string $name, string $category)
    {
        return $this->createQueryBuilder('t')
            ->select(array(
                'COUNT(t) total'
            ))
            ->where('t.slug = :slug')
            ->orWhere('t.name = :name')
            ->andWhere('t.category = (SELECT c.id FROM App:Category c WHERE c.name = :category)')
            ->groupBy('t.slug')
            ->orderBy('total', 'DESC')
            ->setMaxResults(1)
            ->setParameters(array(
                'slug' => $slug,
                'name' => $name,
                'category' => $category
            ))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllGroupedTags($categories = ['films', 'games'])
    {
        // buscamos todos los tags agrupados por nombre y categoria
        // devolvemos entidad completa
        // solamente necesitamos los tags de las categorias films y games
        return $this->createQueryBuilder('t')
            ->select(array(
                't AS tag',
                'COUNT(t) total'
            ))
            ->andWhere('t.category IN (SELECT c.id FROM App:Category c WHERE c.name IN (:category))')
            ->andWhere('t.slug IS NULL')
            ->groupBy('t.name')
            ->addGroupBy('t.category')
            ->orderBy('total', 'DESC')
            ->setParameters(array(
                'category' => $categories
            ))
            ->getQuery()
            ->getResult();
    }
}
