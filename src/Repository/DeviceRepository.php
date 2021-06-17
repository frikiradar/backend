<?php

namespace App\Repository;

use App\Entity\Device;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * @method Device|null find($id, $lockMode = null, $lockVersion = null)
 * @method Device|null findOneBy(array $criteria, array $orderBy = null)
 * @method Device[]    findAll()
 * @method Device[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Device::class);
        $this->em = $entityManager;
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

    public function set(User $user, string $id, string $name, string $token = "", string $platform = null)
    {
        $device = $this->findOneBy(array('token' => $token, 'user' => $user));

        if (empty($device)) {
            $device = $this->findOneBy(array('device_name' => $name, 'user' => $user));
        }

        if (empty($device)) {
            $device = new Device();
            $device->setDeviceName($name);
            $device->setUser($user);
        } else {
            $device->setDeviceName($name);
        }

        if (!empty($token)) {
            $device->setToken($token);
        }

        if (!empty($platform)) {
            $device->setPlatform($platform);
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
