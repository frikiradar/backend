<?php

namespace App\Repository;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Bridge\Doctrine\RegistryInterface;
use App\Service\NotificationService;
use App\Entity\Radar;
use Symfony\Component\Security\Core\Security;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    private $security;

    public function __construct(RegistryInterface $registry, Security $security)
    {
        parent::__construct($registry, User::class);
        $this->security = $security;
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
                'u.name',
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
                'u.last_login',
                'u.hide_connection',
                'u.verified',
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
            $user['age'] = (int) $user['age'];
            $user['distance'] = round($user['distance'], 0, PHP_ROUND_HALF_UP);

            $user['location'] = (!$toUser->getHideLocation() && !empty($toUser->getLocation())) ? $toUser->getLocation() : null;
            $user['last_login'] = (!$toUser->getHideConnection() && !empty($toUser->getLastLogin())) ? $toUser->getLastLogin() : null;
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
            $user['chat'] = !empty($em->getRepository('App:Chat')->isChat($fromUser, $toUser)) ? true : false;

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

        $dql = $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.name',
                'u.description',
                '(DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age',
                'u.location',
                'u.last_login',
                'u.hide_location',
                'u.block_messages',
                'u.hide_connection',
                'u.gender',
                'u.avatar',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ));
        if (!$this->security->isGranted('ROLE_DEMO')) {
            if ($ratio > 1000) {
                $lastLogin = 3;
            } elseif ($ratio >= 500) {
                $lastLogin = 5;
            } else {
                $lastLogin = 7;
            }

            $dql
                ->andHaving($ratio ? 'distance <= :ratio' : 'distance >= :ratio')
                ->andHaving('age BETWEEN :minage AND :maxage')
                ->andWhere($user->getLovegender() ? 'u.gender IN (:lovegender)' : 'u.gender <> :lovegender OR u.gender IS NULL')
                // ->andWhere('u.connection IN (:connection)')
                ->andWhere(
                    $user->getOrientation() == "Homosexual" ?
                        'u.orientation IN (:orientation)' : ($user->getOrientation() ?
                            'u.orientation IN (:orientation) OR u.orientation IS NULL' : 'u.orientation <> :orientation OR u.orientation IS NULL')
                )
                ->andWhere('u.id <> :id')
                ->andWhere("u.roles NOT LIKE '%ROLE_DEMO%'")
                ->andWhere('u.active = 1')
                ->andWhere('DATE_DIFF(CURRENT_DATE(), u.last_login) <= :lastlogin')
                ->orderBy('distance', 'ASC')
                ->setParameters(array(
                    'ratio' => $ratio,
                    'minage' => $user->getMinage() ?: 18,
                    'maxage' => ($user->getMaxage() ?: 150) + 0.9999,
                    'id' => $user->getId(),
                    'lovegender' => $user->getLovegender() ?: 1,
                    // 'connection' => $user->getConnection()
                    'orientation' => $user->getOrientation() ? $this->orientation2Genre($user->getOrientation()) : 1,
                    'lastlogin' => $lastLogin
                ));
        } else {
            $dql
                ->andHaving($ratio ? 'distance <= :ratio' : 'distance >= :ratio')
                ->andWhere("u.roles LIKE '%ROLE_DEMO%'")
                ->setParameters(array(
                    'ratio' => $ratio
                ));
        }
        $users = $dql->getQuery()
            ->getResult();

        return $this->enhanceUsers($users, $user);
    }

    public function searchUsers(string $search, User $user)
    {
        $latitude = $user->getCoordinates() ? $user->getCoordinates()->getLatitude() : 0;
        $longitude = $user->getCoordinates() ? $user->getCoordinates()->getLongitude() : 0;

        $dql = "SELECT u.id,
            u.username,
            u.name,
            u.description,
            (DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age,
            u.location,
            u.hide_location,
            u.hide_connection,
            u.last_login,
            u.block_messages,
            u.avatar,
            (GLength(
                    LineStringFromWKB(
                        LineString(
                            u.coordinates,
                            GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                        )
                    )
                ) * 100) distance
            FROM App:User u WHERE u.id IN
            (SELECT IDENTITY(t.user) FROM App:Tag t WHERE t.name LIKE '%$search%')";
        if (!$this->security->isGranted('ROLE_DEMO')) {
            $dql .= " AND u.roles NOT LIKE '%ROLE_DEM0%' AND u.id <> '" . $user->getId() . "' AND u.active = 1";
        } else {
            $dql .= " AND u.roles LIKE '%ROLE_DEMO%'";
        }
        $query = $this->getEntityManager()->createQuery($dql);

        $users = $query->getResult();

        return $this->enhanceUsers($users, $user, 'search');
    }

    public function enhanceUsers($users, User $fromUser, $type = 'radar')
    {
        $em = $this->getEntityManager();

        foreach ($users as $key => $u) {
            $toUser = $this->findOneBy(array('id' => $u['id']));
            $users[$key]['avatar'] = $u['avatar'] ?: "https://app.frikiradar.com/images/layout/default.jpg";
            $users[$key]['age'] = (int) $u['age'];
            $users[$key]['distance'] = round($u['distance'], 0, PHP_ROUND_HALF_UP);
            $users[$key]['location'] = !$u['hide_location'] ? $u['location'] : null;
            $users[$key]['last_login'] = !$u['hide_connection'] ? $u['last_login'] : null;
            $users[$key]['match'] = $this->getMatchIndex($fromUser->getTags(), $toUser->getTags());
            $user['block'] = !empty($em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) ? true : false;

            if ($user['block']) {
                unset($users[$key]);
            }

            $users[$key]['radar'] = !empty($em->getRepository('App:Radar')->isRadarNotified($toUser, $fromUser)) ? true : false;

            // Si distance es <= 50 y afinidad >= 80 y entonces enviamos notificacion
            if (!$this->security->isGranted('ROLE_DEMO') && $toUser->isPremium()) {
                if ($type == 'radar' && $users[$key]['distance'] <= 10 && $users[$key]['match'] >= 70) {
                    if (empty($em->getRepository('App:Radar')->findOneBy(array('fromUser' => $fromUser, 'toUser' => $toUser)))) {
                        $radar = new Radar();
                        $radar->setFromUser($fromUser);
                        $radar->setToUser($toUser);
                        $em->persist($radar);
                        $em->flush();

                        $notification = new NotificationService();
                        $title = $fromUser->getUsername();
                        $text = "💓Doki doki ¡El FrikiRadar ha detectado a alguien interesante cerca!";
                        $url = "/profile/" . $fromUser->getId();
                        $notification->push($fromUser, $toUser, $title, $text, $url, "radar");
                    }
                }
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
            $fakeIndex = 1.5;
            $scaleIndex = 0.1;
            // $afinity = round($maxIndex * 100, 1); /*Algoritmo A*/
            // $afinity = round(($minIndex / $maxIndex) * 100, 1);  /*Algoritmo B*/
            // $afinity = round((($minIndex) + ($minIndex / $maxIndex)) / 2 * $fakeIndex * 100, 1); /*Algoritmo C*/
            $afinity = ((count($tagsA) + count($tagsB)) * $scaleIndex) >= $matches ? round((($minIndex) + ($minIndex / $maxIndex)) / 2 * $fakeIndex * 100, 1) : round($maxIndex * 100, 1); /*Algoritmo D*/
            return $afinity < 100 ? $afinity : 100;
        } else {
            return 0;
        }
    }

    private function orientation2Genre($orientation)
    {
        switch ($orientation) {
            case "Heterosexual":
                return ["Heterosexual", "Bisexual", "Pansexual", "Queer", "Demisexual", "Sapiosexual", "Asexual"];
                break;

            case "Homosexual":
                return ["Homosexual", "Bisexual", "Pansexual"];
                break;

            default:
                return ["Heterosexual", "Homosexual", "Bisexual", "Pansexual", "Queer", "Demisexual", "Sapiosexual", "Asexual"];
        }
    }

    public function getSuggestionUsername($username)
    {
        $random = rand(1, 9999);
        $user = $this->findOneBy(array('username' => $username . $random));
        if (empty($user)) {
            return $username . $random;
        } else {
            $this->getSuggestionUsername($username);
        }
    }

    public function getUsersByLastLogin(int $days)
    {
        $today = date("Y-m-d");
        $fromDate = date('Y-m-d', strtotime('-' . $days . ' days', strtotime($today)));
        $toDate = date('Y-m-d', strtotime('-' . $days + 1 . ' days', strtotime($today)));

        $dql = "SELECT u FROM App:User u WHERE u.last_login >= :fromDate
        AND u.last_login < :toDate AND u.active = 1 AND u.mailing = 1";

        $query = $this->getEntityManager()->createQuery($dql)->setParameters(["fromDate" => $fromDate, "toDate" => $toDate]);
        return $query->getResult();
    }

    public function getUsersWithoutCredits()
    {
        $today = date("Y-m-d");
        $dql = "SELECT u FROM App:User u WHERE (u.credits = 0 OR u.credits IS NULL)
        AND u.active = 1 AND (u.premium_expiration IS NULL OR u.premium_expiration < :today)";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter("today", $today);
        return $query->getResult();
    }
}
