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
        // $storiesPerPage = 15; // Número de historias por página
        // $firstResult = ($page - 1) * $storiesPerPage; // Calcula el primer resultado de la página actual

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
            $dql = "SELECT s FROM App:Story s 
            LEFT JOIN App:User u WITH s.user = u.id
            LEFT JOIN App:BlockUser ba WITH (
                (s.user = ba.block_user AND ba.from_user = :currentUser) OR
                (s.user = ba.from_user AND ba.block_user = :currentUser)
            )
            WHERE s.time_creation > :yesterday 
            AND s.type = 'story'
            AND s.slug = :slug
            AND (u.banned != 1 AND u.roles NOT LIKE '%ROLE_DEMO%')
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('yesterday', $yesterday)
                ->setParameter('slug', $slug)
                ->setParameter('currentUser', $user->getId());
            // ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
            // ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        } else {
            $dql = "SELECT s FROM App:Story s
            WHERE s.user IN (SELECT u.id FROM App:User u WHERE u.roles LIKE '%ROLE_DEMO%')
            AND s.type = 'story'
            AND s.slug = :slug
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('slug', $slug);
            // ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
            // ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        }

        $stories = $query->getResult();

        foreach ($stories as $story) {
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
        }

        return $stories;
    }

    public function findOneStory($id)
    {
        /** @var User $user */
        $me = $this->security->getUser();
        $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));

        $dql = "SELECT s FROM App:Story s
            WHERE s.id = :id
            AND s.time_creation > :yesterday
            AND s.type = 'story'";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $id)
            ->setParameter('yesterday', $yesterday);

        $story = $query->getOneOrNullResult();

        if ($story) {
            $story->setLike($story->isLikedByUser($me));
            $story->setViewed($story->isViewedByUser($me));
        }

        return $story;
    }

    public function getUserStories(User $user)
    {
        /** @var User $user */
        $me = $this->security->getUser();
        $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));

        $dql = "SELECT s FROM App:Story s
            WHERE s.user = :id
            AND s.time_creation > :yesterday
            AND s.type = 'story'
            ORDER BY s.time_creation DESC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $user->getId())
            ->setParameter('yesterday', $yesterday);

        $stories = $query->getResult();

        foreach ($stories as $story) {
            $story->setLike($story->isLikedByUser($me));
            $story->setViewed($story->isViewedByUser($me));
        }

        return $stories;
    }


    public function getStories($page = 1)
    {
        /** @var User $user */
        $user = $this->security->getUser();
        // $storiesPerPage = 15; // Número de historias por página
        // $firstResult = ($page - 1) * $storiesPerPage; // Calcula el primer resultado de la página actual

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
            $dql = "SELECT s FROM App:Story s 
            LEFT JOIN App:User u WITH s.user = u.id
            LEFT JOIN App:BlockUser ba WITH (
                (s.user = ba.block_user AND ba.from_user = :currentUser) OR
                (s.user = ba.from_user AND ba.block_user = :currentUser)
            )
            WHERE s.time_creation > :yesterday 
            AND s.type = 'story'
            AND (u.banned != 1 AND u.roles NOT LIKE '%ROLE_DEMO%')
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('yesterday', $yesterday)
                ->setParameter('currentUser', $user->getId());
            // ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
            // ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        } else {
            $dql = "SELECT s FROM App:Story s
            WHERE s.user IN (SELECT u.id FROM App:User u WHERE u.roles LIKE '%ROLE_DEMO%')
            AND s.type = 'story'
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql);
            // ->setFirstResult($firstResult) // Establece el primer resultado de la consulta
            // ->setMaxResults($storiesPerPage); // Limita el número de resultados a $storiesPerPage
        }

        $stories = $query->getResult();

        foreach ($stories as $story) {
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
        }

        return $stories;
    }

    public function getPosts($page = 1, $filter = 'show-all')
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $page = max(1, $page); // Asegura que el número de página sea al menos 1
        $postsPerPage = 15; // Número de posts por página
        $firstResult = ($page - 1) * $postsPerPage; // Calcula el primer resultado de la página actual

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $queryBuilder = $this->getEntityManager()->createQueryBuilder();

            $queryBuilder->select('s')
                ->from('App:Story', 's')
                ->leftJoin('App:User', 'u', 'WITH', 's.user = u.id')
                ->leftJoin('App:BlockUser', 'ba', 'WITH', '(s.user = ba.block_user AND ba.from_user = :currentUser) OR (s.user = ba.from_user AND ba.block_user = :currentUser)')
                ->where('s.type = :postType')
                ->andWhere('u.banned != 1')
                ->andWhere('u.roles NOT LIKE :roleDemo')
                ->andWhere('ba.id IS NULL')
                ->setParameter('postType', 'post')
                ->setParameter('roleDemo', '%ROLE_DEMO%')
                ->setParameter('currentUser', $user)
                ->setFirstResult($firstResult)
                ->setMaxResults($postsPerPage)
                ->orderBy('s.time_creation', 'DESC');

            if ($filter === "for-you") {
                $subQuery = $this->getEntityManager()->createQueryBuilder()
                    ->select('ut.slug')
                    ->from('App:Tag', 'ut')
                    ->where('ut.user = :currentUser')
                    ->getDQL();

                $queryBuilder->leftJoin('App:LikeUser', 'lu', 'WITH', 'lu.to_user = s.user AND lu.from_user = :currentUser')
                    ->andWhere('lu.id IS NOT NULL OR s.user = :currentUser OR s.slug IN (' . $subQuery . ')');
            }

            $query = $queryBuilder->getQuery();
            $posts = $query->getResult();
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
            LEFT JOIN App:BlockUser ba WITH (
                (s.user = ba.block_user AND ba.from_user = :currentUser) OR
                (s.user = ba.from_user AND ba.block_user = :currentUser)
            )
            WHERE s.type = 'post'
            AND s.slug = :slug
            AND (u.banned != 1 AND u.roles NOT LIKE '%ROLE_DEMO%')
            ORDER BY s.time_creation DESC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('slug', $slug)
                ->setParameter('currentUser', $user->getId())
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

    public function getUserPosts(User $user, $page = 1)
    {
        /** @var User $user */
        $me = $this->security->getUser();

        $page = max(1, $page);
        $postsPerPage = 15;
        $firstResult = ($page - 1) * $postsPerPage;

        $dql = "SELECT s FROM App:Story s
            WHERE s.user = :id
            AND s.type = 'post'
            ORDER BY s.time_creation DESC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $user->getId())
            ->setFirstResult($firstResult)
            ->setMaxResults($postsPerPage);

        $posts = $query->getResult();

        foreach ($posts as $post) {
            $post->setLike($post->isLikedByUser($me));
            $post->setViewed($post->isViewedByUser($me));
        }

        return $posts;
    }
}
