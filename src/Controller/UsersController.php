<?php

// src/Controller/UsersController.php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Entity\Tag;
use App\Entity\Category;
use App\Service\FileUploaderService;
use App\Entity\BlockUser;
use App\Entity\Config;
use App\Entity\HideUser;
use App\Entity\ViewUser;
use App\Service\GeolocationService;
use App\Service\NotificationService;
use App\Service\RequestService;
use App\Service\AccessCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class UsersController
 *
 * @Route(path="/api")
 */
class UsersController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        RequestService $request,
        NotificationService $notification,
        AccessCheckerService $accessChecker
    ) {
        $this->userRepository = $userRepository;
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->request = $request;
        $this->notification = $notification;
        $this->accessChecker = $accessChecker;
    }

    // USER URI's

    /**
     * @Route("/login", name="user_login", methods={"POST"})
     */
    public function getLoginAction()
    {
    }

    /**
     * @Route("/register", name="user_register", methods={"POST"})
     */
    public function registerAction(Request $request, UserPasswordEncoderInterface $encoder, \Swift_Mailer $mailer)
    {
        $email = $this->request->get($request, 'email');
        $username = $this->request->get($request, 'username');
        $password = $this->request->get($request, 'password');
        $birthday = \DateTime::createFromFormat('Y-m-d', explode('T', $this->request->get($request, 'birthday'))[0]);

        if (is_null($this->em->getRepository('App:User')->findOneByUsernameOrEmail($username, $email))) {
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setName($username);
            $user->setPassword($encoder->encodePassword($user, $password));
            $user->setBirthday($birthday);
            $user->setGender($this->request->get($request, 'gender') ?: null);
            $user->setLovegender($this->request->get($request, 'lovegender') ?: null);
            $user->setRegisterDate();
            $user->setRegisterIp();
            $user->setActive(false);
            $user->setBanned(false);
            $user->setPublic(true);
            $user->setHideLikes(true);
            $user->setTwoStep(false);
            $user->setVerified(false);
            $user->setMeet($this->request->get($request, 'meet') ?: null);
            $user->setReferral($this->request->get($request, 'referral') ?: null);
            $user->setMailing($this->request->get($request, 'mailing', false) ?: true);
            $user->setVerificationCode();
            $user->setRoles(['ROLE_USER']);
            try {

                $this->em->persist($user);

                $message = (new \Swift_Message($user->getVerificationCode() . ' es tu código de activación de FrikiRadar'))
                    ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setTo($user->getEmail())
                    ->setBody(
                        $this->renderView(
                            "emails/registration.html.twig",
                            [
                                'username' => $user->getUsername(),
                                'code' => $user->getVerificationCode()
                            ]
                        ),
                        'text/html'
                    );

                if (0 === $mailer->send($message)) {
                    throw new HttpException(400, "La dirección de email introducida no es válida");
                }

                $this->em->flush();

                return new Response($this->serializer->serialize($user, "json", ['datetime_format' => 'Y-m-d']));
            } catch (Exception $ex) {
                $message = (new \Swift_Message('Error de registro de usuario'))
                    ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setBody("Datos del usuario:<br>" . $this->serializer->serialize($user, "json", ['groups' => 'default']) . "<br>" . $ex->getMessage());

                $mailer->send($message);
                throw new HttpException(400, "Error: Ha ocurrido un error al registrar el usuario. Vuelve a intentarlo en unos minutos.");
            }
        } else {
            throw new HttpException(400, "Error: Ya hay un usuario registrado con estos datos.");
        }
    }


    /**
     * @Route("/v1/user", name="get_user", methods={"GET"})
     */
    public function getAction()
    {
        $user = $this->getUser();
        $user->setImages($user->getImages());

        $user->setLastLogin();
        $this->em->persist($user);
        $this->em->flush();

        return new Response($this->serializer->serialize($user, "json", ['groups' => ['default', 'tags']]));
    }


    /**
     * @Route("/v1/user/{id}", name="get_user_id", methods={"GET"})
     */
    public function getUserAction($id)
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        $cache = new FilesystemAdapter();

        if (!is_numeric($id)) {
            $username = $id;
            $user = $this->em->getRepository('App:User')->findOneBy(array('username' => $username));
            $id = $user->getId();
        }

        try {
            $userCache = $cache->getItem('users.get.' . $fromUser->getId() . '.' . $id);
            if (!$userCache->isHit()) {
                $userCache->expiresAfter(5 * 60);
                $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));
                $user = $this->em->getRepository('App:User')->findOneUser($fromUser, $toUser);
                if ($user['active']) {
                    $user['images'] = $toUser->getImages();
                }

                $radar = $this->em->getRepository('App:Radar')->isRadarNotified($toUser, $fromUser);
                if (!is_null($radar)) {
                    $radar->setTimeRead(new \DateTime);
                    $this->em->persist($radar);
                    $this->em->flush();
                }

                $user = $this->serializer->serialize($user, "json", ['groups' => ['default', 'tags'], AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
                $userCache->set($user);
                $cache->save($userCache);
            } else {
                $user = $userCache->get();
            }

            return new Response($user);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener el usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/public-user/{id}", name="get_public_user", methods={"GET"})
     */
    public function getPublicUserAction($id)
    {
        $cache = new FilesystemAdapter();

        if (!is_numeric($id)) {
            $username = $id;
            $user = $this->em->getRepository('App:User')->findOneBy(array('username' => $username));
            $id = $user->getId();
        }

        try {
            $userCache = $cache->getItem('users.get.' . $id);
            if (!$userCache->isHit()) {
                $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));
                if (!is_null($toUser)) {
                    $user = $this->em->getRepository('App:User')->findPublicUser($toUser);
                    $user = $this->serializer->serialize($user, "json", ['groups' => ['default', 'tags'], AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
                    $userCache->set($user);
                    $cache->save($userCache);
                } else {
                    throw new HttpException(400, "Usuario no encontrado");
                }
            } else {
                $user = $userCache->get();
            }

            return new Response($user);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener el usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/username/{username}", name="check_username", methods={"GET"})
     */
    public function checkUsernameAction(string $username)
    {
        try {
            $user = $this->em->getRepository('App:User')->findOneBy(array('username' => $username));

            if (empty($user)) {
                return new Response($this->serializer->serialize($username, "json"));
            } else {
                $username = $this->em->getRepository('App:User')->getSuggestionUsername($username);
                return new Response($this->serializer->serialize($username, "json"));
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al comprobar el nombre de usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/user", name="update_user", methods={"PUT"})
     */
    public function putAction(Request $request)
    {
        /**
         * @var User
         */
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            if ($this->request->get($request, 'id') == $this->getUser()->getId()) {
                $cache = new FilesystemAdapter();
                $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
                $cache->deleteItem('users.get.' . $user->getId());
                $user->setName($this->request->get($request, 'name') ?: $this->request->get($request, 'username'));
                $user->setDescription($this->request->get($request, 'description'));
                $user->setLocation($this->request->get($request, 'location') ?: $user->getLocation());
                $user->setBirthday(\DateTime::createFromFormat('Y-m-d', explode('T', $this->request->get($request, 'birthday'))[0]));
                $user->setGender($this->request->get($request, 'gender'));
                $user->setOrientation($this->request->get($request, 'orientation'));
                $user->setPronoun($this->request->get($request, 'pronoun'));
                $user->setRelationship($this->request->get($request, 'relationship'));
                $user->setStatus($this->request->get($request, 'status'));
                $user->setMinage($this->request->get($request, 'minage'));
                $user->setMaxage($this->request->get($request, 'maxage'));
                $user->setLovegender($this->request->get($request, 'lovegender'));
                $user->setConnection($this->request->get($request, 'connection'));
                $user->setHideLocation($this->request->get($request, 'hide_location'));
                $user->setBlockMessages($this->request->get($request, 'block_messages'));
                $user->setTwoStep($this->request->get($request, 'two_step'));
                $user->setHideConnection($this->request->get($request, 'hide_connection'));
                $user->setHideLikes($this->request->get($request, 'hide_likes'));
                $user->setPublic($this->request->get($request, 'public'));

                $user->setMailing($this->request->get($request, 'mailing'));

                $this->em->persist($user);
                $this->em->flush();

                return new Response($this->serializer->serialize($user, "json", ['groups' => ['default', 'tags'], 'datetime_format' => 'Y-m-d']));
            } else {
                throw new HttpException(401, "El usuario no eres tu, ¿intentando hacer trampa?");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al actualizar la información del usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/coordinates", name="coordinates", methods={"PUT"})
     */
    public function putCoordinatesAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $geolocation = new GeolocationService();
            $coords = $geolocation->geolocate($user->getIP(), $this->request->get($request, 'latitude'), $this->request->get($request, 'longitude'));
            $user->setCoordinates($coords);
            $this->em->persist($user);
            $this->em->flush();

            /*$location = $geolocation->getLocationName($coords->getLatitude(), $coords->getLongitude());
            if ($location) {
                $user->setLocation($location["locality"]);
                $user->setCountry($location["country"]);
                $this->em->persist($user);
                $this->em->flush();
            }*/

            return new Response($this->serializer->serialize($coords, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al registrar coordenadas - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/avatar", name="avatar", methods={"POST"})
     */
    public function uploadAvatarAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $avatar = $request->files->get('avatar');

        $id = $user->getId();
        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.get.' . $id . '.' . $id);
        $cache->deleteItem('users.get.' . $id);

        $filename = date('YmdHis');
        $uploader = new FileUploaderService("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/", $filename);
        $image = $uploader->uploadImage($avatar);

        $uploader = new FileUploaderService("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/", $filename . '-128px');
        $thumbnail = $uploader->uploadImage($avatar, true, 70, 128);

        if (isset($thumbnail) && isset($image)) {
            // $server = "https://$_SERVER[HTTP_HOST]";
            $server = "https://app.frikiradar.com";

            $thumbnails = glob("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/*-128px.jpg");
            usort($thumbnails, function ($a, $b) {
                return basename($b) <=> basename($a);
            });
            foreach ($thumbnails as $key => $file) {
                if ($key > 9) {
                    unlink($file);
                }
            }
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $thumbnail);
            $user->setThumbnail($src);
            $this->em->persist($user);

            $files = glob("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/*.jpg");
            usort($files, function ($a, $b) {
                return basename($b) <=> basename($a);
            });
            foreach ($files as $key => $file) {
                if ($key > 9) {
                    unlink($file);
                }
            }
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            $user->setAvatar($src);
            $this->em->persist($user);
            $this->em->flush();

            $user->setImages($user->getImages());

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } else {
            throw new HttpException(400, "Error al subir la imagen");
        }
    }


    /**
     * @Route("/v1/avatar", name="update_avatar", methods={"PUT"})
     */
    public function updateAvatarAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
            $cache->deleteItem('users.get.' . $user->getId());

            $server = "https://app.frikiradar.com";
            $src = $this->request->get($request, 'avatar');

            $file = basename($src);
            $file = explode(".", $file);
            $filename = $file[0] . '-128px.' . $file[1];
            $thumbnail = $server . "/images/avatar/" . $user->getId() . "/" . $filename;

            $user->setThumbnail($thumbnail);
            $user->setAvatar($src);
            $this->em->persist($user);
            $this->em->flush();

            $user->setImages($user->getImages());

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al actualizar el avatar - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/delete-avatar", name="delete_avatar", methods={"PUT"})
     */
    public function deleteAvatarAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
            $cache->deleteItem('users.get.' . $user->getId());

            $src = $this->request->get($request, 'avatar');

            $filename = basename($src);
            $file = explode(".", $filename);
            $thumbnail = $file[0] . '-128px.' . $file[1];

            $avatar = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $user->getId() . "/" . $filename;
            unlink($avatar);

            $thumbnail = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $user->getId() . "/" . $thumbnail;
            unlink($thumbnail);

            if (strpos($user->getAvatar(), $filename) !== false) {
                $user->setAvatar(null);
                $user->setThumbnail(null);
            }
            $user->setImages($user->getImages());

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al borrar el avatar - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/radar", name="radar", methods={"PUT"})
     */
    public function getRadarUsers(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        ini_set('max_execution_time', 60);
        ini_set('memory_limit', '512M');
        // $cache = new FilesystemAdapter();

        $page = $this->request->get($request, "page");
        $ratio = $this->request->get($request, "ratio") ?: -1;
        try {
            /*$usersCache = $cache->getItem('users.radar.' . $user->getId() . $page . $ratio);
            if (!$usersCache->isHit()) {
                $usersCache->expiresAfter(60 * 5);*/
            $users = $this->em->getRepository('App:User')->getRadarUsers($user, $page, $ratio);
            /*$usersCache->set($users);
                $cache->save($usersCache);
            } else {
                $users = $usersCache->get();
            }*/

            return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los usuarios - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/search", name="search", methods={"POST"})
     */
    public function searchAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        // $cache = new FilesystemAdapter();
        $page = $this->request->get($request, "page");
        $order = $this->request->get($request, "order");
        $query = $this->request->get($request, "query");

        try {
            /*$searchCache = $cache->getItem('users.search.' . $user->getId() . $page . $order . $query);
            if (!$searchCache->isHit()) {
                $searchCache->expiresAfter(60);*/
            $users = $this->em->getRepository('App:User')->searchUsers($query, $user, $order, $page);
            /*$searchCache->set($users);
                $cache->save($searchCache);
            } else {
                $users = $searchCache->get();
            }*/

            return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los resultados de búsqueda - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/search-usernames/{query}", name="search_usernames", methods={"GET"})
     */
    public function searchUsernames($query)
    {
        try {
            $usernames = $this->em->getRepository('App:User')->searchUsernames($query);

            return new Response($this->serializer->serialize($usernames, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al buscar nombres de usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/activation", name="activation-email", methods={"GET"})
     */
    public function activationEmailAction(\Swift_Mailer $mailer)
    {
        try {
            $user = $this->getUser();
            $user->setVerificationCode();
            $this->em->persist($user);
            $this->em->flush();

            $message = (new \Swift_Message($this->getUser()->getVerificationCode() . ' es tu código de activación de FrikiRadar'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo($this->getUser()->getEmail())
                ->setBody(
                    $this->renderView(
                        "emails/registration.html.twig",
                        [
                            'username' => $this->getUser()->getUsername(),
                            'code' => $this->getUser()->getVerificationCode()
                        ]
                    ),
                    'text/html'
                );

            if (0 === $mailer->send($message)) {
                throw new HttpException(400, "Error al enviar el email de activación");
            }

            return new Response($this->serializer->serialize("Email enviado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar el email de activación - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/activation", name="activation", methods={"PUT"})
     */
    public function activationAction(Request $request)
    {
        $verificationCode = $this->request->get($request, "verification_code");
        $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId(), 'verificationCode' => $verificationCode));
        if (!is_null($user)) {
            $user->setActive(true);
            $user->setVerificationCode(null);
            $this->em->persist($user);
            $this->em->flush();

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } else {
            throw new HttpException(400, "Error al activar la cuenta");
        }
    }


    /**
     * @Route("/recover", name="recover-email", methods={"POST"})
     */
    public function requestEmailAction(Request $request, \Swift_Mailer $mailer)
    {
        if (preg_match('#^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $this->request->get($request, 'username'))) {
            $user = $this->em->getRepository('App:User')->findOneBy(array('email' => $this->request->get($request, 'username')));
        } else {
            $user = $this->em->getRepository('App:User')->findOneBy(array('username' => $this->request->get($request, 'username')));
        }

        if (!is_null($user)) {
            $user->setVerificationCode();
            $this->em->persist($user);
            $this->em->flush();

            try {

                $message = (new \Swift_Message($user->getVerificationCode() . ' es el código para recuperar tu contraseña de FrikiRadar'))
                    ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setTo($user->getEmail())
                    ->setBody(
                        $this->renderView(
                            "emails/recover.html.twig",
                            [
                                'username' => $user->getUsername(),
                                'code' => $user->getVerificationCode()
                            ]
                        ),
                        'text/html'
                    );

                if (0 === $mailer->send($message)) {
                    throw new HttpException(400, "Error al enviar el email de recuperación");
                }

                return new Response($this->serializer->serialize("Email enviado correctamente", "json"));
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al enviar el email de recuperación - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "No hay ningú usuario registrado con estos datos");
        }
    }


    /**
     * @Route("/recover", name="recover-password", methods={"PUT"})
     */
    public function recoverPasswordAction(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $verificationCode = $this->request->get($request, "verification_code");

        if (preg_match('#^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $this->request->get($request, 'username'))) {
            $user = $this->em->getRepository('App:User')->findOneBy(array('email' => $this->request->get($request, 'username'), 'verificationCode' => $verificationCode));
        } else {
            $user = $this->em->getRepository('App:User')->findOneBy(array('username' => $this->request->get($request, 'username'), 'verificationCode' => $verificationCode));
        }

        if (!is_null($user)) {
            $user->setPassword($encoder->encodePassword($user, $this->request->get($request, 'password')));
            $user->setVerificationCode(null);
            $this->em->persist($user);
            $this->em->flush();

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } else {
            throw new HttpException(400, "Error al recuperar la cuenta");
        }
    }


    /**
     * @Route("/v1/password", name="change-password", methods={"PUT"})
     */
    public function changePasswordAction(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $user = $this->getUser();

        if ($user->getPassword() == $encoder->encodePassword($user, $this->request->get($request, "old_password"))) {
            $user->setPassword($encoder->encodePassword($user, $this->request->get($request, 'new_password')));

            $this->em->persist($user);
            $this->em->flush();

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } else {
            throw new HttpException(400, "La contraseña actual no es válida");
        }
    }


    /**
     * @Route("/v1/email", name="change-email", methods={"PUT"})
     */
    public function changeEmailAction(Request $request)
    {
        $user = $this->getUser();

        if ($user->getEmail() == $this->request->get($request, "old_email")) {
            $user->setEmail($this->request->get($request, 'new_email'));

            $this->em->persist($user);
            $this->em->flush();

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } else {
            throw new HttpException(400, "El email actual no es válido");
        }
    }


    /**
     * @Route("/v1/username", name="change-username", methods={"PUT"})
     */
    public function changeUsernameAction(Request $request)
    {
        try {
            $user = $this->getUser();
            $user->setUsername($this->request->get($request, 'new_username'));

            $this->em->persist($user);
            $this->em->flush();

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Ya hay un usuario con ese nombre - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/block", name="block", methods={"PUT"})
     */
    public function putBlockAction(Request $request, \Swift_Mailer $mailer)
    {
        try {
            $blockUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $this->request->get($request, 'user')));

            $newBlock = new BlockUser();
            $newBlock->setDate(new \DateTime);
            $newBlock->setFromUser($this->getUser());
            $newBlock->setBlockUser($blockUser);
            $newBlock->setNote($this->request->get($request, 'note', false));
            $this->em->persist($newBlock);
            $this->em->flush();

            if (!empty($newBlock->getNote())) {
                // Enviar email al administrador informando del motivo
                $message = (new \Swift_Message('Nuevo usuario bloqueado'))
                    ->setFrom([$this->getUser()->getEmail() => $this->getUser()->getUsername()])
                    ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setBody("El usuario " . $this->getUser()->getUsername() . " ha bloqueado al usuario <a href=https://frikiradar.app/" . $blockUser->getUsername() . "'>" . $blockUser->getUsername() . "</a> por el siguiente motivo: " . $newBlock->getNote(), 'text/html');

                if (0 === $mailer->send($message)) {
                    // throw new HttpException(400, "Error al enviar el email con motivo del bloqueo");
                }
            }

            return new Response($this->serializer->serialize("Usuario bloqueado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al bloquear usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/block/{id}", name="unblock", methods={"DELETE"})
     */
    public function removeBlockAction(int $id)
    {
        try {
            $blockUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));

            $block = $this->em->getRepository('App:BlockUser')->findOneBy(array('block_user' => $blockUser, 'from_user' => $this->getUser()));
            $this->em->remove($block);
            $this->em->flush();

            $users = $this->em->getRepository('App:BlockUser')->getBlockUsers($this->getUser());

            return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desbloquear el usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/blocks", name="blocks", methods={"GET"})
     */
    public function getBlocksAction()
    {
        $users = $this->em->getRepository('App:BlockUser')->getBlockUsers($this->getUser());

        return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
    }

    /**
     * @Route("/v1/report", name="report", methods={"PUT"})
     */
    public function putReportAction(Request $request, \Swift_Mailer $mailer)
    {
        try {
            $reportUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $this->request->get($request, 'user')));
            $note = $this->request->get($request, 'note', false);

            if (!empty($note)) {
                // Enviar email al administrador informando del motivo
                $message = (new \Swift_Message('Nuevo usuario reportado'))
                    ->setFrom([$this->getUser()->getEmail() => $this->getUser()->getUsername()])
                    ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setBody("El usuario " . $this->getUser()->getUsername() . " ha reportado al usuario <a href='https://frikiradar.app/" . $reportUser->getUsername() . "'>" . $reportUser->getUsername() . "</a> por el siguiente motivo: " . $note, 'text/html');

                if (0 === $mailer->send($message)) {
                    throw new HttpException(400, "Error al enviar el email con motivo del reporte");
                }
            }

            return new Response($this->serializer->serialize("Usuario reportado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al reportar usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/hide", name="hide", methods={"PUT"})
     */
    public function putHideAction(Request $request)
    {
        try {
            $fromUser = $this->getUser();
            $hideUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $this->request->get($request, 'user')));

            if (empty($this->em->getRepository('App:HideUser')->isHide($fromUser, $hideUser))) {
                $newHide = new HideUser();
                $newHide->setDate(new \DateTime);
                $newHide->setFromUser($fromUser);
                $newHide->setHideUser($hideUser);
                $this->em->persist($newHide);
                $this->em->flush();

                return new Response($this->serializer->serialize("Usuario ocultado correctamente", "json"));
            } else {
                throw new HttpException(400, "Error al ocultar usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al ocultar usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/hide/{id}", name="unhide", methods={"DELETE"})
     */
    public function removeHideAction(int $id)
    {
        try {
            $hideUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));

            $hide = $this->em->getRepository('App:HideUser')->findOneBy(array('hide_user' => $hideUser, 'from_user' => $this->getUser()));
            $this->em->remove($hide);
            $this->em->flush();

            $users = $this->em->getRepository('App:HideUser')->getHideUsers($this->getUser());

            return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desocultar el usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/hides", name="hides", methods={"GET"})
     */
    public function getHidesAction()
    {
        $users = $this->em->getRepository('App:HideUser')->getHideUsers($this->getUser());

        return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
    }


    /**
     * @Route("/v1/view", name="view", methods={"PUT"})
     */
    public function putViewAction(Request $request)
    {
        try {
            $fromUser = $this->getUser();
            if ($fromUser->getUsername() !== 'frikiradar') {
                $viewUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $this->request->get($request, 'user')));

                $newView = new ViewUser();
                $newView->setDate(new \DateTime);
                $newView->setFromUser($fromUser);
                $newView->setToUser($viewUser);
                $this->em->persist($newView);
                $this->em->flush();
            }

            $data = [
                'code' => 200,
                'message' => "Usuario visto correctamente",
            ];
            return new JsonResponse($data, 200);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al ver usuario - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/two-step", name="two_step", methods={"GET"})
     */
    public function twoStepAction(\Swift_Mailer $mailer)
    {
        try {
            $user = $this->getUser();
            $user->setVerificationCode();
            $this->em->persist($user);
            $this->em->flush();

            $message = (new \Swift_Message($this->getUser()->getVerificationCode() . ' es el código para verificar tu inicio de sesión'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo($this->getUser()->getEmail())
                ->setBody(
                    $this->renderView(
                        "emails/two-step.html.twig",
                        [
                            'username' => $this->getUser()->getUsername(),
                            'code' => $this->getUser()->getVerificationCode()
                        ]
                    ),
                    'text/html'
                );

            if (0 === $mailer->send($message)) {
                throw new \RuntimeException('Unable to send email');
            }

            $response = [
                'code' => 200,
                'error' => false,
                'data' => "Email enviado correctamente",
            ];
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al enviar el email de verificación - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($this->serializer->serialize($response, "json"));
    }


    /**
     * @Route("/v1/two-step", name="verify_session", methods={"PUT"})
     */
    public function verifyLoginAction(Request $request)
    {
        $verificationCode = $this->request->get($request, "verification_code");
        $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId(), 'verificationCode' => $verificationCode));
        if (!is_null($user)) {
            try {
                $user->setVerificationCode(null);
                $this->em->persist($user);
                $this->em->flush();

                return new Response($this->serializer->serialize($this->getUser(), "json", ['groups' => 'default']));
            } catch (Exception $e) {
                throw new HttpException(400, "Error al verificar tu sesión: " . $e);
            }
        } else {
            throw new HttpException(400, "El código de verificación no es válido.");
        }
    }

    /**
     * @Route("/v1/link-patreon", name="link_patreon", methods={"PUT"})
     */
    public function linkToPatreon(Request $request)
    {
        $oauthCode = $this->request->get($request, "oauth_code", false);
        $user = $this->getUser();
        try {
            if ($oauthCode) {
                $tokens = $this->em->getRepository('App:User')->getPatreonTokens($oauthCode);
                $user->setPatreon($tokens);

                // Ahora queda averiguar si es miembro del patreon de frikiradar
                if ($this->em->getRepository('App:User')->checkPatreonMembership($tokens['access_token'])) {
                    $user->addRol('ROLE_PATREON');
                }

                $this->em->persist($user);
                $this->em->flush();

                $data = [
                    'code' => 200,
                    'message' => "Cuenta vinculada con patreon correctamente",
                ];
                return new JsonResponse($data, 200);
            } else {
                throw new HttpException(400, "Error al recibir el oauth_code de patreon.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al vincular cuenta con patreon - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/patreon-webhook", name="patreon_webhook", methods={"POST"})
     */
    public function patreonWebhook()
    {
        $webhook = $this->em->getRepository('App:User')->patreonWebhook();

        $data = [
            'code' => 200,
            'message' => json_encode($webhook),
        ];
        return new JsonResponse($data, 200);
    }

    /**
     * @Route("/v1/disable", name="disable", methods={"PUT"})
     */
    public function disableAction(Request $request, \Swift_Mailer $mailer, UserPasswordEncoderInterface $encoder)
    {
        $user = $this->getUser();

        if ($user->getPassword() == $encoder->encodePassword($user, $this->request->get($request, "password"))) {
            try {
                // ponemos usuario en disable
                $user->setActive(false);
                // borramos sus dispositivos
                $user->removeDevices();

                $this->em->persist($user);
                $this->em->flush();

                if (!empty($this->request->get($request, 'note'))) {
                    // Enviar email al administrador informando del motivo
                    $message = (new \Swift_Message($user->getUsername() . ' ha desactivado su cuenta.'))
                        ->setFrom([$user->getEmail() => $user->getUsername()])
                        ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                        ->setBody("El usuario " . $user->getUsername() . " ha desactivado su cuenta por el siguiente motivo: " . $this->request->get($request, 'note'), 'text/html');

                    if (0 === $mailer->send($message)) {
                        // throw new HttpException(400, "Error al enviar el email con motivo de la desactivación");
                    }
                }

                return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al desactivar la cuenta - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "La contraseña no es correcta");
        }
    }

    /**
     * @Route("/v1/remove-account", name="remove_account", methods={"PUT"})
     */
    public function removeAccountAction(Request $request, \Swift_Mailer $mailer, UserPasswordEncoderInterface $encoder)
    {
        $user = $this->getUser();

        if ($user->getPassword() == $encoder->encodePassword($user, $this->request->get($request, "password"))) {
            try {
                // borramos archivos de chat
                $this->em->getRepository('App:Chat')->deleteChatsFiles($user);

                // borramos archivos de historias
                $folder = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/stories/" . $user->getId() . "/";
                foreach (glob($folder . "*.*") as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
                if (file_exists($folder)) {
                    rmdir($folder);
                }

                // borramos carpeta del usuario imagenes y thumbnails
                $folder = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $user->getId() . "/";
                foreach (glob($folder . "*.*") as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
                if (file_exists($folder)) {
                    rmdir($folder);
                }

                $username = $user->getUsername();
                // Eliminamos usuario
                $this->em->remove($user);
                $this->em->flush();

                if (!empty($this->request->get($request, 'note'))) {
                    // Enviar email al administrador informando del motivo
                    $message = (new \Swift_Message($username . ' ha eliminado su cuenta.'))
                        ->setFrom([$user->getEmail() => $username])
                        ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                        ->setBody("El usuario " . $username . " ha eliminado su cuenta por el siguiente motivo: " . $this->request->get($request, 'note'), 'text/html');

                    if (0 === $mailer->send($message)) {
                        // throw new HttpException(400, "Error al enviar el email con motivo de la desactivación");
                    }
                }

                $data = [
                    'code' => 200,
                    'message' => "Usuario eliminado correctamente",
                ];
                return new JsonResponse($data, 200);
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al eliminar la cuenta - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "La contraseña no es correcta");
        }
    }
}
