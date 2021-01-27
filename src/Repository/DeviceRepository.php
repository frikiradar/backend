<?php

namespace App\Repository;

use App\Entity\Device;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Device|null find($id, $lockMode = null, $lockVersion = null)
 * @method Device|null findOneBy(array $criteria, array $orderBy = null)
 * @method Device[]    findAll()
 * @method Device[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    // /**
    //  * @return Device[] Returns an array of Device objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Device
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function set(User $user, string $id, string $name, string $token = "")
    {
        $em = $this->getEntityManager();

        $device = $this->findOneBy(array('deviceName' => $name, 'user' => $user));

        if (empty($device)) {
            $device = new Device();
            $device->setDeviceName($name);
            $device->setUser($user);
        }

        if (!empty($token)) {
            $device->setToken($token);
        }
        $device->setDeviceId($id);
        $device->setActive(true);
        $device->setLastUpdate(new \DateTime);

        try {
            $this->em->persist($device);
            $this->em->flush();

            return $device;
        } catch (Exception $e) {
            return $e;
        }
    }
}
