<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    // /**
    //  * @return Payment[] Returns an array of Payment objects
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
    public function findOneBySomeField($value): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function setPayment(String $title, String $description, String $orderId, String $token, String $signature, String $type, User $user, DateTime $date, Float $amount, String $currency, String $json = null)
    {
        $em = $this->getEntityManager();

        $payment = new Payment();
        $payment->setTitle($title);
        $payment->setDescription($description);
        $payment->setOrderId($orderId);
        $payment->setToken($token);
        $payment->setSignature($signature);
        $payment->setType($type);
        $payment->setUser($user);
        $payment->setPaymentDate($date);
        $payment->setAmount($amount);
        $payment->setCurrency($currency);
        $payment->setJson($json);

        $em->persist($payment);
        $em->flush();
    }
}
