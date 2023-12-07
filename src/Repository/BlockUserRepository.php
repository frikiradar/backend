<?php

namespace App\Repository;

use App\Entity\BlockUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method BlockUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method BlockUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method BlockUser[]    findAll()
 * @method BlockUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BlockUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockUser::class);
    }

    // /**
    //  * @return BlockUser[] Returns an array of BlockUser objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BlockUser
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function getBlockUsers(User $user)
    {
        $dql = "SELECT u.id, u.name, u.avatar, u.thumbnail            
            FROM App:User u WHERE u.id IN
            (SELECT IDENTITY(b.block_user) FROM App:BlockUser b WHERE b.from_user = :fromUser)";
        $query = $this->getEntityManager()->createQuery($dql)->setParameter("fromUser", $user);

        return $query->getResult();
    }

    public function isBlocked(User $fromUser, User $blockUser)
    {
        return $this->createQueryBuilder('b')
            ->where('b.from_user = :fromUser AND b.block_user = :blockUser')
            ->orWhere('b.from_user = :blockUser AND b.block_user = :fromUser')
            ->setParameters([
                'fromUser' => $fromUser,
                'blockUser' => $blockUser
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(BlockUser $blockUser): void
    {
        $this->_em->persist($blockUser);
        $this->_em->flush();
    }

    public function remove(BlockUser $blockUser): void
    {
        $this->_em->remove($blockUser);
        $this->_em->flush();
    }
}
