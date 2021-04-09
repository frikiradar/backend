<?php

namespace App\Repository;

use App\Entity\Chat;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Config\Definition\Exception\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\NotificationService;
use App\Entity\Radar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry, AuthorizationCheckerInterface $security, EntityManagerInterface $entityManager, NotificationService $notification, \Swift_Mailer $mailer)
    {
        parent::__construct($registry, User::class);
        $this->security = $security;
        $this->em = $entityManager;
        $this->notification = $notification;
        $this->mailer = $mailer;
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

    public function findOneUser(User $fromUser, User $toUser)
    {
        $latitude = $fromUser->getCoordinates() ? $fromUser->getCoordinates()->getLatitude() : 0;
        $longitude = $fromUser->getCoordinates() ? $fromUser->getCoordinates()->getLongitude() : 0;

        $dql = $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.name',
                'u.description',
                'u.active',
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
                'u.hide_likes',
                'u.block_messages',
                'u.last_login',
                'u.hide_connection',
                'u.verified',
                'u.banned',
                'u.ban_end',
                'u.ban_reason',
                'u.avatar',
                'u.thumbnail',
                'u.roles',
                'u.public',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ))
            ->andWhere('u.id = :id');
        if (!$this->security->isGranted('ROLE_DEMO')) {
            $dql->andWhere('u.active = 1');
        }

        if (!$this->security->isGranted('ROLE_MASTER')) {
            $dql->andWhere('u.banned <> 1');
        }

        /**
         * @var User
         */
        $user = $dql->setParameters(array(
            'id' => $toUser->getId()
        ))->getQuery()
            ->getOneOrNullResult();

        if (!is_null($user)) {
            $today = new \DateTime;

            if ($user['banned'] && $this->security->isGranted('ROLE_MASTER')) {
                $user['name'] = $user['name'] . ' (baneado)';
            }

            $user['age'] = (int) $user['age'];
            if (!$user['hide_location']) {
                $user['distance'] = round($user['distance'], 0, PHP_ROUND_HALF_UP);
            } else {
                unset($user['distance']);
            }
            $user['last_login'] = (!$user['hide_connection'] && $today->diff($user['last_login'])->format('%a') <= 7) ? $user['last_login'] : null;
            $user['tags'] = $toUser->getTags();
            $user['stories'] = $toUser->getStories();
            $user['match'] = $this->getMatchIndex($fromUser->getTags(), $toUser->getTags());
            $user['avatar'] = $toUser->getAvatar() ?: null;
            $user['like'] = !empty($this->em->getRepository('App:LikeUser')->findOneBy([
                'from_user' => $fromUser,
                'to_user' => $toUser
            ])) ? true : false;
            $user['from_like'] = !empty($this->em->getRepository('App:LikeUser')->findOneBy([
                'from_user' => $toUser,
                'to_user' => $fromUser
            ])) ? true : false;
            $user['likes']['received'] = count($this->em->getRepository('App:LikeUser')->getLikeUsers($toUser, 'received'));
            $user['likes']['delivered'] = count($this->em->getRepository('App:LikeUser')->getLikeUsers($toUser, 'delivered'));
            $user['block'] = !empty($this->em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) ? true : false;
            $user['chat'] = !empty($this->em->getRepository('App:Chat')->isChat($fromUser, $toUser)) ? true : false;
            /*if ($this->security->isGranted('ROLE_MASTER')) {
                $user['ip'] = $user->getLastIp();
            }*/
            if (!$user['block']) {
                return $user;
            } else {
                throw new Exception('Usuario bloqueado');
            }
        } else {
            throw new Exception('Usuario no encontrado');
        }
    }

    public function findPublicUser(User $toUser)
    {
        $dql = $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.name',
                'u.description',
                'u.active',
                'u.verified',
                'u.banned',
                'u.avatar',
                'u.thumbnail',
                'u.roles'
            ))
            ->andWhere('u.id = :id')
            ->andWhere('u.active = 1')
            ->andWhere('u.public = 1')
            ->andWhere('u.banned <> 1');

        $user = $dql->setParameters(array(
            'id' => $toUser->getId()
        ))->getQuery()
            ->getOneOrNullResult();

        if (!is_null($user)) {
            $user['tags'] = $toUser->getTags();
            $user['avatar'] = $toUser->getAvatar() ?: null;

            return $user;
        } else {
            throw new Exception('Usuario no encontrado');
        }
    }

    public function getRadarUsers(User $user, $page, $ratio)
    {
        $latitude = $user->getCoordinates() ? $user->getCoordinates()->getLatitude() : 0;
        $longitude = $user->getCoordinates() ? $user->getCoordinates()->getLongitude() : 0;
        $limit = 15;
        $offset = ($page - 1) * $limit;

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
                'u.thumbnail',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ));
        if ($ratio > -1) {
            $dql->andHaving($ratio ? 'distance <= ' . $ratio : 'distance >= ' . $ratio);
        }
        if (!$this->security->isGranted('ROLE_DEMO')) {
            // $lastLogin = 14;

            $dql
                ->andHaving('age BETWEEN :minage AND :maxage')
                ->andWhere($user->getLovegender() ? 'u.gender IN (:lovegender)' : 'u.gender <> :lovegender OR u.gender IS NULL')
                // ->andWhere('u.connection IN (:connection)')
                ->andWhere(
                    $user->getOrientation() == "Homosexual" ?
                        'u.orientation IN (:orientation)' : ($user->getOrientation() ?
                            'u.orientation IN (:orientation) OR u.orientation IS NULL' : 'u.orientation <> :orientation OR u.orientation IS NULL')
                )
                ->andWhere('u.id <> :id')
                ->andWhere('u.avatar IS NOT NULL')
                ->andWhere("u.roles NOT LIKE '%ROLE_DEMO%'")
                ->andWhere('u.active = 1')
                ->andWhere('u.banned <> 1')
                ->andWhere("u.id IN (SELECT IDENTITY(t.user) FROM App:Tag t)")
                ->andWhere('u.id NOT IN (SELECT IDENTITY(b.block_user) FROM App:BlockUser b WHERE b.from_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(bu.from_user) FROM App:BlockUser bu WHERE bu.block_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(h.hide_user) FROM App:HideUser h WHERE h.from_user = :id)')
                // ->andWhere('DATE_DIFF(CURRENT_DATE(), u.last_login) <= :lastlogin')
                ->orderBy('distance', 'ASC')
                ->addOrderBy('u.last_login', 'DESC')
                ->setParameters(array(
                    'minage' => $user->getMinage() ?: 18,
                    'maxage' => ($user->getMaxage() ?: 150) + 0.9999,
                    'id' => $user->getId(),
                    'lovegender' => $user->getLovegender() ?: 1,
                    // 'connection' => $user->getConnection()
                    'orientation' => $user->getOrientation() ? $this->orientation2Genre($user->getOrientation()) : 1,
                    // 'lastlogin' => $lastLogin
                ));
        } else {
            $dql
                ->andWhere("u.roles LIKE '%ROLE_DEMO%'")
                ->andWhere('u.id <> :id')
                ->setParameters(array(
                    'id' => $user->getId()
                ));
        }

        if ($ratio === -1) {
            $dql->andWhere('u.id NOT IN (SELECT IDENTITY(v.to_user) FROM App:ViewUser v WHERE v.from_user = :id)');

            $users = $dql->getQuery()
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getResult();

            $users = $this->enhanceUsers($users, $user);
            return array_slice($users, 0);
        } else {
            $users = $dql->getQuery()
                ->getResult();

            $users = $this->enhanceUsers($users, $user);
            usort($users, function ($a, $b) {
                return (isset($b['match']) ? $b['match'] : 0) <=> (isset($a['match']) ? $a['match'] : 0);
            });

            $offset = ($page - 1) * $limit;

            return array_slice($users, $offset, $limit);
        }
    }

    public function searchUsers(string $search, User $user, $order, $page)
    {
        $latitude = $user->getCoordinates() ? $user->getCoordinates()->getLatitude() : 0;
        $longitude = $user->getCoordinates() ? $user->getCoordinates()->getLongitude() : 0;

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
                'u.avatar',
                'u.thumbnail',
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
            if (!$this->security->isGranted('ROLE_MASTER')) {
                $dql
                    ->andHaving('age BETWEEN :minage AND :maxage')
                    ->andWhere($user->getLovegender() ? 'u.gender IN (:lovegender)' : 'u.gender <> :lovegender OR u.gender IS NULL')
                    // ->andWhere('u.connection IN (:connection)')
                    ->andWhere(
                        $user->getOrientation() == "Homosexual" ?
                            'u.orientation IN (:orientation)' : ($user->getOrientation() ?
                                'u.orientation IN (:orientation) OR u.orientation IS NULL' : 'u.orientation <> :orientation OR u.orientation IS NULL')
                    )
                    ->andWhere('u.avatar IS NOT NULL')
                    ->andWhere('u.banned <> 1')
                    ->andWhere('u.active = 1');
            }
            $dql->andWhere('u.id <> :id')
                ->andWhere("u.roles NOT LIKE '%ROLE_DEMO%'")
                ->andWhere('u.id NOT IN (SELECT IDENTITY(b.block_user) FROM App:BlockUser b WHERE b.from_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(bu.from_user) FROM App:BlockUser bu WHERE bu.block_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(h.hide_user) FROM App:HideUser h WHERE h.from_user = :id)')
                ->andWhere("u.id IN (SELECT IDENTITY(t.user) FROM App:Tag t WHERE t.name LIKE '%" . $search . "%') OR u.name LIKE '%$search%' OR u.username LIKE '%$search%'")
                ->orderBy('distance', 'ASC')
                ->addOrderBy('u.last_login', 'DESC')
                ->setParameter('id', $user->getId());
            if (!$this->security->isGranted('ROLE_MASTER')) {
                $dql->setParameter('minage', $user->getMinage() ?: 18)
                    ->setParameter('maxage', ($user->getMaxage() ?: 150) + 0.9999)
                    ->setParameter('lovegender', $user->getLovegender() ?: 1)
                    ->setParameter('orientation', $user->getOrientation() ? $this->orientation2Genre($user->getOrientation()) : 1);
            }
        } else {
            $dql->andWhere("u.roles LIKE '%ROLE_DEMO%'");
        }

        $users = $dql->getQuery()
            ->getResult();

        $users = $this->enhanceUsers($users, $user, 'search');

        switch ($order) {
            case 'match':
                usort($users, function ($a, $b) {
                    return (isset($b['match']) ? $b['match'] : 0) <=> (isset($a['match']) ? $a['match'] : 0);
                });
                break;
            default:
                usort($users, function ($b, $a) {
                    return (isset($b['distance']) ? $b['distance'] : 0) <=> (isset($a['distance']) ? $a['distance'] : 0);
                });
        }

        $limit = 15;
        $offset = ($page - 1) * $limit;

        return array_slice($users, $offset, $limit);
    }

    public function enhanceUsers($users, User $fromUser, $type = 'radar')
    {
        $today = new \DateTime;
        $fromTags = $fromUser->getTags();
        $toTags = $this->getTagsFromUsers($users);

        foreach ($users as $key => $u) {
            $users[$key]['avatar'] = $u['avatar'] ?: "https://app.frikiradar.com/images/layout/default.jpg";
            $users[$key]['age'] = (int) $u['age'];
            if (!$u['hide_location']) {
                $users[$key]['distance'] = round($u['distance'], 0, PHP_ROUND_HALF_UP);
            } else {
                unset($users[$key]['distance']);
            }
            $users[$key]['last_login'] = (!$u['hide_connection'] && $today->diff($u['last_login'])->format('%a') <= 7) ? $u['last_login'] : null;
            // echo $toTags[$u['id']];
            if (isset($toTags[$u['id']])) {
                $users[$key]['match'] = $this->getMatchIndex($fromTags, $toTags[$u['id']]);
                $users[$key]['common_tags'] = $this->getCommonTags($fromTags, $toTags[$u['id']]);
            } else {
                $users[$key]['match'] = 0;
                $users[$key]['common_tags'] = [];
            }

            if (!$this->security->isGranted('ROLE_DEMO')) {
                // Si distance es <= 5 y afinidad >= 90 y entonces enviamos notificacion
                if ($type == 'radar' && isset($users[$key]['distance']) && $users[$key]['distance'] <= 5 && $users[$key]['match'] >= 90) {
                    if (empty($this->em->getRepository('App:Radar')->findById($fromUser->getId(), $u['id']))) {
                        $radar = new Radar();
                        $radar->setFromUser($fromUser);
                        $toUser = $this->findOneBy(array('id' => $u['id']));
                        $radar->setToUser($toUser);
                        $this->em->persist($radar);
                        $this->em->flush();

                        $title = $fromUser->getUsername();
                        $text = "💓Doki doki ¡El FrikiRadar ha detectado a alguien interesante cerca!";
                        $url = "/profile/" . $fromUser->getId();
                        $this->notification->push($fromUser, $toUser, $title, $text, $url, "radar");
                    }
                }
            }
        }

        return $users;
    }

    private function getTagsFromUsers($users)
    {
        $tagUsers = [];
        foreach ($users as $u) {
            $user = new User();
            $user->setId($u['id']);
            $tagUsers[] = $user;
        }

        $dql = "SELECT t FROM App:Tag t WHERE t.user IN (:users)";
        $query = $this->getEntityManager()->createQuery($dql)->setParameter('users', $tagUsers);

        $tags = [];
        foreach ($query->getResult() as $res) {
            $tags[$res->getUser()->getId()][] = $res;
        }

        return $tags;
    }

    private function getMatchIndex($tagsA, $tagsB)
    {
        $a = $b = [];

        foreach ($tagsA as $tag) {
            $a[] = $tag->getName();
        }
        foreach ($tagsB as $tag) {
            $b[] = $tag->getName();
        }

        $matches = 0;
        foreach ($a as $tagA) {
            foreach ($b as $tagB) {
                similar_text($tagA, $tagB, $percent);
                if ($percent > 90) {
                    $matches++;
                }
            }
        }

        $matchIndexA = count($a) ? $matches / count($a) : 0;
        $matchIndexB = count($b) ? $matches / count($b) : 0;

        if ($matchIndexA && $matchIndexB) {
            $index = min($matchIndexA, $matchIndexB);
            $afinity = (($matches * $index) * 100) / 2;
            return $afinity < 100 ? round($afinity, 1) : 100;
        } else {
            return 0;
        }
    }

    private function getCommonTags($tagsA, $tagsB)
    {
        $a = $b = [];

        foreach ($tagsA as $tag) {
            $a[] = $tag->getName();
        }
        foreach ($tagsB as $tag) {
            $b[] = $tag->getName();
        }

        $commonTags = [];
        foreach ($a as $tagA) {
            foreach ($b as $tagB) {
                similar_text($tagA, $tagB, $percent);
                if ($percent > 90) {
                    $commonTags[] = $tagA;
                }
            }
        }
        return array_values(array_unique($commonTags));
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

    public function banUser(User $toUser, $reason, $days, $hours)
    {
        $chat = new Chat();
        $fromUser = $this->findOneBy(array('username' => 'frikiradar'));
        $title = "⚠️ Te hemos baneado";

        if (!is_null($hours) && $hours > 24) {
            $hours = $hours % 24;
            $days = $days + intdiv($hours, 24);
        }

        $date = null;
        if (!is_null($days) || !is_null($hours)) {
            $date = new \DateTime();
            $date->add(new \DateInterval('P' . ($days ?: 0) . 'DT' . ($hours ?: 0) . 'H'));
        }

        $url = "/chat/" . $fromUser->getId();

        $toUser->setBanned(true);
        $toUser->setBanReason($reason);
        $toUser->setBanEnd($date ?: null);
        $this->em->persist($toUser);


        $text = 'Te hemos baneado por el siguiente motivo: ' . $reason . PHP_EOL;
        if (!empty($hours) || !empty($days)) {
            $text = $text . "El castigo terminará en ";
            if (!empty($days)) {
                $text = $text . ($days ?: 0) . ($days == 1 ? " día" : " días") . (!empty($hours) ? ' y ' : '.');
            }
            if (!empty($hours)) {
                $text = $text . ($hours ?: 0) . ($hours == 1 ? " hora." : " horas.");
            }
        } else {
            $text = $text . "Fecha de finalización: Indefinida";
        }

        $chat->setFromuser($fromUser);
        $chat->setTouser($toUser);

        $chat->setText($title . "\r\n\r\n" . $text);
        $chat->setTimeCreation();
        $chat->setConversationId('frikiradar');
        $this->em->persist($chat);
        $this->em->flush();

        $this->notification->push($fromUser, $toUser, $title, $text, $url, "ban");

        // Enviamos email avisando
        $message = (new \Swift_Message('Nuevo usuario baneado'))
            ->setFrom([$fromUser->getEmail() => $fromUser->getUsername()])
            ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
            ->setBody("El usuario <a href='https://frikiradar.app/" . $toUser->getUsername() . "'>" . $toUser->getUsername() . "</a> ha sido baneado por el siguiente motivo: " . $text, 'text/html');

        if (0 === $this->mailer->send($message)) {
            // throw new HttpException(400, "Error al enviar el email avisando el bug");
        }
    }

    public function getBanUsers()
    {
        $fromUser = $this->findOneBy(array('id' => 1));
        $dql = "SELECT u.id, u.name, u.thumbnail, u.ban_reason, u.ban_end            
            FROM App:User u WHERE u.banned = 1";
        $users = $this->getEntityManager()->createQuery($dql)->getResult();
        foreach ($users as $key => $user) {
            $toUser = $this->findOneBy(array('id' => $user['id']));
            $users[$key]['count'] = intval($this->em->getRepository('App:Chat')->countUnreadUser($fromUser, $toUser));
        }

        usort($users, function ($a, $b) {
            return ($b['count'] <=> $a['count']);
        });

        return $users;
    }

    public function searchUsernames($query)
    {
        return $this->createQueryBuilder('u')
            ->select('u.username, u.thumbnail')
            ->where('u.username LIKE :query')
            ->andWhere('u.active = 1')
            ->andWhere('u.banned = 0')
            ->orderBy('u.last_login', 'DESC')
            ->setMaxResults(3)
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getArrayResult();
    }
}
