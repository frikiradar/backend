<?php

namespace App\Repository;

use App\Entity\Story;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @method Story|null find($id, $lockMode = null, $lockVersion = null)
 * @method Story|null findOneBy(array $criteria, array $orderBy = null)
 * @method Story[]    findAll()
 * @method Story[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StoryRepository extends ServiceEntityRepository
{
    private $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        parent::__construct($registry, Story::class);
        $this->security = $security;
    }

    // /**
    //  * @return Story[] Returns an array of Story objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Story
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function save(Story $story): void
    {
        $this->getEntityManager()->persist($story);
        $this->getEntityManager()->flush();
    }

    public function remove(Story $story): void
    {
        $this->getEntityManager()->remove($story);
        $this->getEntityManager()->flush();
    }

    public function getStoriesBySlug(string $slug, $page = 1)
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $storiesPerPage = 15; // Número de historias por página
        $firstResult = ($page - 1) * $storiesPerPage; // Calcula el primer resultado de la página actual

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
            $dql = "SELECT s FROM App:Story s 
            LEFT JOIN App:User u WITH s.user = u.id
            LEFT JOIN App:BlockUser ba WITH s.user = ba.from_user
            LEFT JOIN App:BlockUser bb WITH s.user = bb.block_user
            WHERE s.time_creation > :yesterday 
            AND s.type = 'story'
            AND s.slug = :slug
            AND (u.banned != 1 AND u.roles NOT LIKE '%ROLE_DEMO%')
            AND (ba.block_user != :id OR ba.block_user IS NULL)
            AND (bb.from_user != :id OR bb.from_user IS NULL)
            ORDER BY s.time_creation ASC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('yesterday', $yesterday)
                ->setParameter('slug', $slug)
                ->setParameter('id', $user->getId())
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        } else {
            $dql = "SELECT s FROM App:Story s
            WHERE s.user IN (SELECT u.id FROM App:User u WHERE u.roles LIKE '%ROLE_DEMO%')
            AND s.type = 'story'
            AND s.slug = :slug
            ORDER BY s.time_creation ASC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('slug', $slug)
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        }

        $stories = $query->getResult();

        foreach ($stories as $story) {
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
        }

        return $stories;
    }

    public function getUserStories(User $user)
    {
        $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
        $dql = "SELECT s FROM App:Story s
            WHERE s.user = :id
            AND s.time_creation > :yesterday
            AND s.type = 'story'
            ORDER BY s.time_creation ASC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $user->getId())
            ->setParameter('yesterday', $yesterday);

        $stories = $query->getResult();

        foreach ($stories as $story) {
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
        }

        return $stories;
    }


    public function getStories($page = 1)
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $storiesPerPage = 15; // Número de historias por página
        $firstResult = ($page - 1) * $storiesPerPage; // Calcula el primer resultado de la página actual

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
            $dql = "SELECT s FROM App:Story s 
            LEFT JOIN App:User u WITH s.user = u.id
            LEFT JOIN App:BlockUser ba WITH s.user = ba.from_user
            LEFT JOIN App:BlockUser bb WITH s.user = bb.block_user
            WHERE s.time_creation > :yesterday 
            AND s.type = 'story'
            AND (u.banned != 1 AND u.roles NOT LIKE '%ROLE_DEMO%')
            AND (ba.block_user != :id OR ba.block_user IS NULL)
            AND (bb.from_user != :id OR bb.from_user IS NULL)
            ORDER BY s.time_creation ASC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('yesterday', $yesterday)
                ->setParameter('id', $user->getId())
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        } else {
            $dql = "SELECT s FROM App:Story s
            WHERE s.user IN (SELECT u.id FROM App:User u WHERE u.roles LIKE '%ROLE_DEMO%')
            AND s.type = 'story'
            ORDER BY s.time_creation ASC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        }

        $stories = $query->getResult();

        foreach ($stories as $story) {
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
        }

        return $stories;
    }

    public function getPosts($page = 1)
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $postsPerPage = 15; // Número de posts por página
        $firstResult = ($page - 1) * $postsPerPage; // Calcula el primer resultado de la página actual

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $dql = "SELECT s FROM App:Story s 
            LEFT JOIN App:User u WITH s.user = u.id
            LEFT JOIN App:BlockUser ba WITH s.user = ba.from_user
            LEFT JOIN App:BlockUser bb WITH s.user = bb.block_user
            AND s.type = 'post'
            AND (u.banned != 1 AND u.roles NOT LIKE '%ROLE_DEMO%')
            AND (ba.block_user != :id OR ba.block_user IS NULL)
            AND (bb.from_user != :id OR bb.from_user IS NULL)
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('id', $user->getId())
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($postsPerPage); // Limita el número de resultados a $postsPerPage
        } else {
            $dql = "SELECT s FROM App:Story s
            WHERE s.user IN (SELECT u.id FROM App:User u WHERE u.roles LIKE '%ROLE_DEMO%')
            AND s.type = 'post'
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($postsPerPage); // Limita el número de resultados a $postsPerPage
        }

        $posts = $query->getResult();

        foreach ($posts as $post) {
            $post->setLike($post->isLikedByUser($user));
            $post->setViewed($post->isViewedByUser($user));
        }

        return $posts;
    }

    public function getPostsBySlug(string $slug, $page = 1)
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $postsPerPage = 15; // Número de posts por página
        $firstResult = ($page - 1) * $postsPerPage; // Calcula el primer resultado de la página actual

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $dql = "SELECT s FROM App:Story s 
            LEFT JOIN App:User u WITH s.user = u.id
            LEFT JOIN App:BlockUser ba WITH s.user = ba.from_user
            LEFT JOIN App:BlockUser bb WITH s.user = bb.block_user
            WHERE s.type = 'post'
            AND s.slug = :slug
            AND (u.banned != 1 AND u.roles NOT LIKE '%ROLE_DEMO%')
            AND (ba.block_user != :id OR ba.block_user IS NULL)
            AND (bb.from_user != :id OR bb.from_user IS NULL)
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('slug', $slug)
                ->setParameter('id', $user->getId())
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($postsPerPage); // Limita el número de resultados a $postsPerPage
        } else {
            $dql = "SELECT s FROM App:Story s
            WHERE s.user IN (SELECT u.id FROM App:User u WHERE u.roles LIKE '%ROLE_DEMO%')
            AND s.type = 'post'
            AND s.slug = :slug
            ORDER BY s.time_creation ASC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('slug', $slug)
                ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
                ->setMaxResults($postsPerPage); // Limita el número de resultados a $postsPerPage
        }

        $posts = $query->getResult();

        foreach ($posts as $post) {
            $post->setLike($post->isLikedByUser($user));
            $post->setViewed($post->isViewedByUser($user));
        }

        return $posts;
    }
}
