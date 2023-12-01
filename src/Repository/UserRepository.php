<?php

namespace App\Repository;

use App\Entity\Chat;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Config\Definition\Exception\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    private $security;
    private $em;
    private $notification;
    private $mailer;

    public function __construct(ManagerRegistry $registry, AuthorizationCheckerInterface $security, EntityManagerInterface $entityManager, NotificationService $notification, MailerInterface $mailer)
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

    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :identifier OR u.email = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

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
        $point = 'Point(' . $longitude . ' ' . $latitude . ')';

        $dql = $this->createQueryBuilder('u')
            ->select([
                'u.id',
                'u.username',
                'u.name',
                'u.description',
                'u.active',
                '(DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age',
                'u.languages',
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
                'u.register_date',
                'u.verified',
                'u.banned',
                'u.ban_end',
                'u.ban_reason',
                'u.avatar',
                'u.thumbnail',
                'u.roles',
                'u.public',
                "(SpDistance(
                    u.coordinates,
                    StGeomFromText(:point)
                ) * 111.045) distance"
            ])
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
        $user = $dql->setParameters([
            'id' => $toUser->getId(),
            'point' => $point
        ])->getQuery()
            ->getOneOrNullResult();

        if (!is_null($user)) {
            $today = new \DateTime;

            if ($user['banned'] && $this->security->isGranted('ROLE_MASTER')) {
                $user['name'] = $user['name'] . ' (baneado)';
            }

            $user['age'] = (int) $user['age'];
            if (!$user['hide_location'] && $user['distance']) {
                $user['distance'] = round($user['distance'], 0, PHP_ROUND_HALF_UP);
            } else {
                unset($user['distance']);
            }
            if (!$this->security->isGranted('ROLE_MASTER') && $toUser->getId() != $fromUser->getId()) {
                $user['last_login'] = (!$user['hide_connection'] && $today->diff($user['last_login'])->format('%a') <= 7) ? $user['last_login'] : null;
            }
            if (empty($toUser->getConnection())) {
                $user['connection'] = 'Amistad';
            }
            $user['tags'] = $toUser->getTags();
            $user['stories'] = $toUser->getStories();
            $user['match'] = $this->getMatchIndex($fromUser->getTags(), $toUser->getTags());
            $user['avatar'] = $toUser->getAvatar() ?: null;
            $user['thumbnail'] = $toUser->getThumbnail() ?: null;
            $user['roles'] = $toUser->getRoles();
            $user['like'] = !empty($this->em->getRepository(\App\Entity\LikeUser::class)->findOneBy([
                'from_user' => $fromUser,
                'to_user' => $toUser
            ])) ? true : false;
            $user['from_like'] = !empty($this->em->getRepository(\App\Entity\LikeUser::class)->findOneBy([
                'from_user' => $toUser,
                'to_user' => $fromUser
            ])) ? true : false;
            if (!$toUser->isHideLikes() || $this->security->isGranted('ROLE_MASTER') || $toUser->getId() == $fromUser->getId()) {
                $user['likes']['received'] = $this->em->getRepository(\App\Entity\LikeUser::class)->countLikeUsers($toUser, 'received');
                $user['likes']['delivered'] = $this->em->getRepository(\App\Entity\LikeUser::class)->countLikeUsers($toUser, 'delivered');
            }
            $user['chat'] = !empty($this->em->getRepository(\App\Entity\Chat::class)->isChat($fromUser, $toUser)) ? true : false;
            if ($this->security->isGranted('ROLE_MASTER')) {
                $user['ip'] = $toUser->getLastIp();
            }

            return $user;
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

    public function findBlockUser(User $toUser)
    {
        $dql = $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.name',
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
            $user['avatar'] = $toUser->getAvatar() ?: null;
            $user['block'] = true;
            $user['block_messages'] = true;
            return $user;
        } else {
            throw new Exception('Usuario no encontrado');
        }
    }

    public function getRadarUsers(User $user, $page, $ratio, $options)
    {
        $latitude = $user->getCoordinates() ? $user->getCoordinates()->getLatitude() : 0;
        $longitude = $user->getCoordinates() ? $user->getCoordinates()->getLongitude() : 0;
        $point = 'Point(' . $longitude . ' ' . $latitude . ')';

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
                'u.lovegender',
                'u.connection',
                'u.avatar',
                'u.thumbnail',
                "(SpDistance(
                    u.coordinates,
                    StGeomFromText(:point)
                ) * 111.045) distance"
            ))
            ->setParameter('point', $point);
        if ($ratio > -1) {
            $dql->andHaving($ratio ? 'distance <= ' . $ratio : 'distance >= ' . $ratio);
        }
        if (!$this->security->isGranted('ROLE_DEMO')) {
            $lastLogin = 7;
            $connection = !empty($user->getConnection()) ? $user->getConnection() : ['Amistad'];
            if (!$options || ($options && $options['range'] === true)) {
                $dql
                    ->andHaving('age BETWEEN :minage AND :maxage')
                    ->setParameter('minage', $user->getMinage() ?: 18)
                    ->setParameter('maxage', ($user->getMaxage() ?: 150) + 0.9999);
            } else {
                $dql
                    ->andHaving('age BETWEEN :minage AND :maxage')
                    ->setParameter('minage', 18)
                    ->setParameter('maxage', 150.9999);
            }
            if (!$options || ($options && $options['identity'] === true)) {
                $dql->andWhere($user->getLovegender() ? "u.gender IN (:lovegender) AND (u.lovegender LIKE '%" . $user->getGender() . "%' OR u.lovegender IS NULL)" : 'u.gender <> :lovegender OR u.gender IS NULL')
                    ->setParameter('lovegender', $user->getLovegender() ?: 1);
            }
            if (!$options || ($options && $options['connection'] === true)) {
                $dql->andWhere(
                    in_array('Amistad', $connection) ? "u.connection LIKE '%Amistad%' OR u.connection IS NULL" :
                        "u.connection NOT LIKE '%Amistad%'"
                );
            }
            $dql->andWhere(
                $user->getOrientation() == "Homosexual" && !in_array('Amistad', $connection) ?
                    'u.orientation IN (:orientation)' : ($user->getOrientation() ?
                        'u.orientation IN (:orientation) OR u.orientation IS NULL' : 'u.orientation <> :orientation OR u.orientation IS NULL')
            )
                ->andWhere('u.avatar IS NOT NULL')
                ->andWhere("u.roles NOT LIKE '%ROLE_DEMO%'")
                ->andWhere('u.active = 1')
                ->andWhere('u.banned <> 1')
                ->andWhere('u.coordinates IS NOT NULL')
                ->andWhere("u.id IN (SELECT IDENTITY(t.user) FROM App:Tag t)")
                ->andWhere('u.id NOT IN (SELECT IDENTITY(b.block_user) FROM App:BlockUser b WHERE b.from_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(bu.from_user) FROM App:BlockUser bu WHERE bu.block_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(h.hide_user) FROM App:HideUser h WHERE h.from_user = :id)')
                ->andWhere('DATE_DIFF(CURRENT_DATE(), u.last_login) <= :lastlogin')
                ->setParameter('orientation', $user->getOrientation() ? $this->orientation2Genre($user->getOrientation(), $user->getConnection()) : 1)
                ->setParameter('lastlogin', $lastLogin);
        } else {
            $dql
                ->andWhere("u.roles LIKE '%ROLE_DEMO%'");
        }

        $dql
            ->andWhere("u.id <> :id")
            ->setParameter('id', $user->getId());

        if ($ratio === -1) {
            // $today = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
            $recent = date('Y-m-d H:i:s', strtotime('-12 hours', strtotime(date("Y-m-d H:i:s"))));
            $users = $dql->andWhere('u.id NOT IN (SELECT IDENTITY(v.to_user) FROM App:ViewUser v WHERE v.from_user = :id) OR u.last_login > :recent')
                ->orderBy('distance', 'ASC')
                ->addOrderBy('u.last_login', 'DESC')
                ->getQuery()
                ->setParameter('recent', $recent)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getResult();

            $users = $this->enhanceUsers($users, $user, 'radar-cards');
            // shuffle($users);
            return array_slice($users, 0);
        } else {
            $users = $dql->addOrderBy('u.last_login', 'DESC')
                ->orderBy('distance', 'ASC')
                ->getQuery()
                ->getResult();

            $users = $this->enhanceUsers($users, $user, 'radar-list');
            usort($users, function ($a, $b) {
                return (isset($b['match']) ? $b['match'] : 0) <=> (isset($a['match']) ? $a['match'] : 0);
            });

            $offset = ($page - 1) * $limit;

            return array_slice($users, $offset, $limit);
        }
    }

    public function searchUsers(string $search, User $user, $order, $page, $isSlug = false)
    {
        $latitude = $user->getCoordinates() ? $user->getCoordinates()->getLatitude() : 0;
        $longitude = $user->getCoordinates() ? $user->getCoordinates()->getLongitude() : 0;
        $point = 'Point(' . $longitude . ' ' . $latitude . ')';

        $regex = '/^((saga|trilogia|trilogía|trilogy|series|collection)\s+)|(\s+(saga|trilogia|trilogía|trilogy|series|collection))|(\(\s*(saga|trilogia|trilogía|trilogy|series|collection)\s*\))$/i';
        $search = trim(preg_replace($regex, '', $search));

        $dql = $this->createQueryBuilder('u')
            ->select([
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
                "(SpDistance(
                    u.coordinates,
                    StGeomFromText(:point)
                ) * 111.045) distance"
            ])
            ->setParameter('point', $point);

        if (!$this->security->isGranted('ROLE_DEMO')) {
            $connection = !empty($user->getConnection()) ? $user->getConnection() : ['Amistad'];

            $dql
                ->andHaving('age BETWEEN :minage AND :maxage')
                ->andWhere($user->getLovegender() ? 'u.gender IN (:lovegender)' : 'u.gender <> :lovegender OR u.gender IS NULL')
                ->andWhere(
                    in_array('Amistad', $connection) ? "u.connection LIKE '%Amistad%' OR u.connection IS NULL" :
                        "u.connection NOT LIKE '%Amistad%'"
                )
                ->andWhere(
                    $user->getOrientation() == "Homosexual" && !in_array('Amistad', $connection) ?
                        'u.orientation IN (:orientation)' : ($user->getOrientation() ?
                            'u.orientation IN (:orientation) OR u.orientation IS NULL' : 'u.orientation <> :orientation OR u.orientation IS NULL')
                )
                ->andWhere('u.avatar IS NOT NULL')
                ->andWhere('u.active = 1')
                ->andWhere('u.banned <> 1')
                ->andWhere('u.id <> :id')
                ->andWhere("u.roles NOT LIKE '%ROLE_DEMO%'")
                ->andWhere('u.id NOT IN (SELECT IDENTITY(b.block_user) FROM App:BlockUser b WHERE b.from_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(bu.from_user) FROM App:BlockUser bu WHERE bu.block_user = :id)')
                ->andWhere('u.id NOT IN (SELECT IDENTITY(h.hide_user) FROM App:HideUser h WHERE h.from_user = :id)');

            if (!$isSlug) {
                $dql->andWhere("u.id IN (SELECT IDENTITY(t.user) FROM App:Tag t WHERE t.name LIKE :search) OR u.name LIKE :search OR u.username LIKE :search")
                    ->setParameter('search', '%' . $search . '%');
            } else {
                $dql->andWhere("u.id IN (SELECT IDENTITY(t.user) FROM App:Tag t WHERE t.slug = :search)")
                    ->setParameter('search', $search);
            }

            $dql->addOrderBy('u.last_login', 'DESC')
                ->setParameter('id', $user->getId())
                ->setParameter('minage', $user->getMinage() ?: 18)
                ->setParameter('maxage', ($user->getMaxage() ?: 150) + 0.9999)
                ->setParameter('lovegender', $user->getLovegender() ?: 1)
                ->setParameter('orientation', $user->getOrientation() ? $this->orientation2Genre($user->getOrientation(), $user->getConnection()) : 1);
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

    public function enhanceUsers($users, User $fromUser, $type = 'radar-cards')
    {
        $today = new \DateTime;
        $fromTags = $fromUser->getTags();
        $toTags = $this->getTagsFromUsers($users);

        foreach ($users as $key => $u) {
            $users[$key]['avatar'] = $u['avatar'] ?: $this->getDefaultAvatar($u['username']);
            if ($type == 'radar-cards') {
                $toUser = $this->findOneBy(array('id' => $u['id']));
                $users[$key]['images'] = $toUser->getImages();
            }
            $users[$key]['age'] = (int) $u['age'];
            if (!$u['hide_location']) {
                $users[$key]['distance'] = round($u['distance'], 0, PHP_ROUND_HALF_UP);
            } else {
                unset($users[$key]['distance']);
            }

            if (!$this->security->isGranted('ROLE_MASTER') && $u['id'] != $fromUser->getId()) {
                $users[$key]['last_login'] = (!$u['hide_connection'] && $today->diff($u['last_login'])->format('%a') <= 7) ? $u['last_login'] : null;
            }
            // echo $toTags[$u['id']];
            if (isset($toTags[$u['id']])) {
                $users[$key]['match'] = $this->getMatchIndex($fromTags, $toTags[$u['id']]);
                $users[$key]['common_tags'] = $this->getCommonTags($fromTags, $toTags[$u['id']]);
            } else {
                $users[$key]['match'] = 0;
                $users[$key]['common_tags'] = [];
            }

            /*if (!$this->security->isGranted('ROLE_DEMO')) {
                // Si distance es <= 5 y afinidad >= 90 y entonces enviamos notificacion
                if ($type == 'radar' && isset($users[$key]['distance']) && $users[$key]['distance'] <= 5 && $users[$key]['match'] >= 75 && (in_array($fromUser->getGender(), $u['lovegender']))) {
                    if (empty($this->em->getRepository(\App\Entity\Radar::class)->findById($fromUser->getId(), $u['id']))) {
                        $toUser = $this->findOneBy(array('id' => $u['id']));
                        if (in_array('ROLE_PREMIUM', $toUser->getRoles()) || in_array('ROLE_ADMIN', $toUser->getRoles()) || in_array('ROLE_MASTER', $toUser->getRoles())) {
                            $radar = new Radar();
                            $radar->setFromUser($fromUser);
                            $radar->setToUser($toUser);
                            $this->em->persist($radar);
                            $this->em->flush();

                            $title = $fromUser->getUsername();
                            $text = "💓Doki doki ¡El frikiradar ha detectado a alguien interesante cerca!";
                            $url = "/profile/" . $fromUser->getId();
                            $this->notification->set($fromUser, $toUser, $title, $text, $url, "radar");
                        }
                    }
                }
            }*/
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

    private function orientation2Genre($orientation, $connection)
    {
        if (!is_array($connection)) {
            $connection = [];
        }
        if (in_array('Amistad', $connection)) {
            return ["Heterosexual", "Homosexual", "Bisexual", "Pansexual", "Queer", "Demisexual", "Sapiosexual", "Asexual"];
        } else {
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
        $chat->setConversationId('1_' . $toUser->getId());
        $this->em->persist($chat);
        $this->em->flush();

        $this->notification->set($fromUser, $toUser, $title, $text, $url, "chat");

        // Enviamos email avisando
        $email = (new Email())
            ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
            ->to(new Address('hola@frikiradar.com', 'frikiradar'))
            ->subject('Nuevo usuario baneado')
            ->html("<p>El usuario <a href='https://frikiradar.app/" . urlencode($toUser->getUsername()) . "'>" . $toUser->getUsername() . "</a> ha sido baneado por el siguiente motivo: " . $text . "</p>");

        if (0 === $this->mailer->send($email)) {
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
            $users[$key]['count'] = intval($this->em->getRepository(\App\Entity\Chat::class)->countUnreadUser($fromUser, $toUser));
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

    public function getDefaultAvatar($username)
    {
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'Ñ', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $letter = strtoupper($username[0]);
        if (in_array($letter, $letters)) {
            return "https://api.frikiradar.com/images/avatar/" . $letter . ".png";
        } else {
            return "https://api.frikiradar.com/images/avatar/default.png";
        }
    }

    public function isBannedIpOrDevice(User $user)
    {
        $ip = $user->getLastIp();
        $devices = $user->getDevices();
        $tokens = [];
        foreach ($devices as $device) {
            if ($device->getToken()) {
                $tokens[] = $device->getToken();
            }
        }

        $dql = "SELECT d FROM App:Device d WHERE d.user IN (SELECT u.id FROM App:User u WHERE u.banned = 1 AND u.id <> '" . $user->getId() . "' )";
        $res = $this->getEntityManager()->createQuery($dql)
            ->getResult();

        foreach ($res as $device) {
            if (in_array($device->getToken(), $tokens)) {
                return true;
            }
        }

        $dql = "SELECT u.id FROM App:User u WHERE u.banned = 1 AND u.last_ip = :ip AND u.id <> '" . $user->getId() . "'";
        $res = $this->getEntityManager()->createQuery($dql)
            ->setParameter('ip', $ip)
            ->getOneOrNullResult();

        if ($res) {
            return true;
        } else {
            return false;
        }
    }
}
