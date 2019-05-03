<?php

namespace App\Repository;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, User::class);
    }

    // /**
    //  * @return User[] Returns an array of User objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
     */

    /*
    public function findOneBySomeField($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
     */

    public function loadUserByUsername($usernameOrEmail)
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :query OR u.email = :query')
            ->setParameter('query', $usernameOrEmail)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUsernameOrEmail($username, $email)
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :username OR u.email = :email')
            ->setParameter('username', $username)
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findeOneUser(User $fromUser, User $toUser)
    {
        $em = $this->getEntityManager();

        $latitude = $fromUser->getCoordinates() ? $fromUser->getCoordinates()->getLatitude() : 0;
        $longitude = $fromUser->getCoordinates() ? $fromUser->getCoordinates()->getLongitude() : 0;

        $user = $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.description',
                '(DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age',
                'u.gender',
                'u.orientation',
                'u.pronoun',
                'u.relationship',
                'u.status',
                'u.lovegender',
                'u.connection',
                'u.location',
                'u.hide_location',
                'u.block_messages',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ))
            ->andWhere('u.id = :id')
            ->andWhere('u.active = 1')
            ->setParameters(array(
                'id' => $toUser->getId()
            ))
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_null($user)) {
            $user['age'] = (int)$user['age'];
            $user['distance'] = round($user['distance'], 0, PHP_ROUND_HALF_UP);

            $user['location'] = (!$toUser->getHideLocation() && !empty($toUser->getLocation())) ? $toUser->getLocation() : null;
            $user['tags'] = $toUser->getTags();
            $user['avatar'] = $toUser->getAvatar() ?: null;
            $user['match'] = $this->getMatchIndex($fromUser->getTags(), $toUser->getTags());
            $user['like'] = !empty($em->getRepository('App:LikeUser')->findOneBy([
                'from_user' => $fromUser,
                'to_user' => $toUser
            ])) ? true : false;
            $user['from_like'] = !empty($em->getRepository('App:LikeUser')->findOneBy([
                'from_user' => $toUser,
                'to_user' => $fromUser
            ])) ? true : false;
            $user['block'] = !empty($em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) ? true : false;

            if (!$user['block']) {
                return $user;
            } else {
                throw new Exception('Usuario bloqueado');
            }
        } else {
            throw new Exception('Usuario no encontrado');
        }
    }

    public function getUsersByDistance(User $user, int $ratio)
    {
        $latitude = $user->getCoordinates() ? $user->getCoordinates()->getLatitude() : 0;
        $longitude = $user->getCoordinates() ? $user->getCoordinates()->getLongitude() : 0;

        $ratio = $latitude && $longitude ? $ratio : 0;

        $users = $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.description',
                '(DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age',
                'u.location',
                'u.hide_location',
                'u.block_messages',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ))
            ->andHaving($ratio ? 'distance <= :ratio' : 'distance >= :ratio')
            ->andHaving('age BETWEEN :minage AND :maxage')
            ->andWhere($user->getLovegender() ? 'u.gender IN (:lovegender)' : 'u.gender <> :lovegender OR u.gender IS NULL')
            // ->andWhere('u.connection IN (:connection)')
            ->andWhere('u.id <> :id')
            ->andWhere("u.roles NOT LIKE '%ROLE_ADMIN%'")
            ->andWhere('u.active = 1')
            ->orderBy('distance', 'ASC')
            ->setParameters(array(
                'ratio' => $ratio,
                'minage' => $user->getMinage() ?: 18,
                'maxage' => $user->getMaxage() ?: 99,
                'id' => $user->getId(),
                'lovegender' => $user->getLovegender() ?: 1,
                // 'connection' => $user->getConnection()
            ))
            // ->setFirstResult($offset)
            // ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->enhanceUsers($users, $user);
    }

    public function searchUsers(string $search, User $user): ?array
    {
        $latitude = $user->getCoordinates()->getLatitude();
        $longitude = $user->getCoordinates()->getLongitude();

        $dql = "SELECT u.id,
            u.username,
            u.description,
            (DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age,
            u.location,
            u.hide_location,
            u.block_messages,
            (GLength(
                    LineStringFromWKB(
                        LineString(
                            u.coordinates,
                            GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                        )
                    )
                ) * 100) distance
            FROM App:User u WHERE u.id IN
            (SELECT IDENTITY(t.user) FROM App:Tag t WHERE t.name LIKE '%$search%')
            AND u.roles NOT LIKE '%ROLE_ADMIN%' AND u.id <> '" . $user->getId() . "'
            AND u.active = 1";
        $query = $this->getEntityManager()->createQuery($dql);

        $users = $query->getResult();

        return $this->enhanceUsers($users, $user);
    }

    public function enhanceUsers($users, $fromUser)
    {
        $em = $this->getEntityManager();

        foreach ($users as $key => $u) {
            $toUser = $this->findOneBy(array('id' => $u['id']));
            $users[$key]['age'] = (int)$u['age'];
            $users[$key]['distance'] = round($u['distance'], 0, PHP_ROUND_HALF_UP);
            $users[$key]['location'] = !$u['hide_location'] ? $u['location'] : null;
            $users[$key]['avatar'] = $toUser->getAvatar() ?: null;
            $users[$key]['match'] = $this->getMatchIndex($fromUser->getTags(), $toUser->getTags());
            $user['block'] = !empty($em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) ? true : false;

            if ($user['block']) {
                unset($users[$key]);
            }
        }

        return $users;
    }

    private function getMatchIndex($tagsA, $tagsB)
    {
        $a = $b = [];

        foreach ($tagsA as $tag) {
            $a[$tag->getCategory()->getName()][] = $tag->getName();
        }
        foreach ($tagsB as $tag) {
            $b[$tag->getCategory()->getName()][] = $tag->getName();
        }

        $matches = 0;
        foreach ($a as $category => $tags) {
            foreach ($tags as $name) {
                if (isset($b[$category]) && in_array($name, $b[$category])) {
                    $matches++;
                }
            }
        }

        $matchIndexA = count($tagsA) ? $matches / count($tagsA) : 0;
        $matchIndexB = count($tagsB) ? $matches / count($tagsB) : 0;

        if ($matchIndexA && $matchIndexB) {
            $maxIndex = max($matchIndexA, $matchIndexB);
            $minIndex = min($matchIndexA, $matchIndexB);

            // $afinity = round($maxIndex * 100, 1); /*Algoritmo A*/
            // $afinity = round(($minIndex / $maxIndex) * 100, 1);  /*Algoritmo B*/
            $afinity = round((($maxIndex * 0.3) + ($minIndex / $maxIndex * 0.7)) * 100, 1); /*Algoritmo C*/
            return $afinity < 100 ? $afinity : 100;
        } else {
            return 0;
        }
    }
}
