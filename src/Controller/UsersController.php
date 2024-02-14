<?php

// src/Controller/UsersController.php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\BlockUserRepository;
use App\Repository\HideUserRepository;
use App\Repository\ViewUserRepository;
use App\Repository\RadarRepository;
use App\Repository\ChatRepository;
use App\Service\FileUploaderService;
use App\Entity\BlockUser;
use App\Entity\HideUser;
use App\Entity\ViewUser;
use App\Service\GeolocationService;
use App\Service\RequestService;
use App\Service\AccessCheckerService;
use App\Service\NotificationService;
use DateInterval;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route(path: '/api')]
class UsersController extends AbstractController
{
    private $serializer;
    private $userRepository;
    private $blockUserRepository;
    private $hideUserRepository;
    private $viewUserRepository;
    private $radarRepository;
    private $chatRepository;
    private $request;
    private $jwtManager;
    private $notification;
    private $geoService;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepository,
        BlockUserRepository $blockUserRepository,
        HideUserRepository $hideUserRepository,
        ViewUserRepository $viewUserRepository,
        RadarRepository $radarRepository,
        ChatRepository $chatRepository,
        NotificationService $notification,
        GeolocationService $geoService
    ) {
        $this->serializer = $serializer;
        $this->request = $request;
        $this->jwtManager = $jwtManager;
        $this->userRepository = $userRepository;
        $this->blockUserRepository = $blockUserRepository;
        $this->hideUserRepository = $hideUserRepository;
        $this->viewUserRepository = $viewUserRepository;
        $this->radarRepository = $radarRepository;
        $this->chatRepository = $chatRepository;
        $this->notification = $notification;
        $this->geoService = $geoService;
    }

    // USER URI's

    #[Route('/login', name: 'user_login', methods: ['POST'])]
    public function getLoginAction()
    {
    }

    #[Route('/login/{provider}', name: 'user_login_provider', methods: ['POST'])]
    public function getLoginProviderAction(Request $request, String $provider)
    {
        $credential = $this->request->get($request, 'credential');

        $client = new \Google\Client();
        $payload = $client->verifyIdToken($credential);
        $google_id = $payload['sub'];

        // Buscamos un usuario con el id de google y si no hay buscamos por el email
        $user = $this->userRepository->findOneBy(['google_id' => $google_id]);
        if (is_null($user)) {
            $user = $this->userRepository->findOneBy(['email' => $payload['email']]);
            // si ya existía le seteamos el id de google
            if (!is_null($user)) {
                $user->setGoogleId($google_id);
            }
        }

        // el login con credential devuelve un objeto con el parámetro token que es la credencial
        if (!is_null($user)) {
            $user->setLastLogin();

            $token = $this->jwtManager->create($user);

            return new JsonResponse(['token' => $token]);
        } else {
            throw new HttpException(400, "Error: No existe ningún usuario con estos datos.");
        }
    }

    #[Route('/register', name: 'user_register', methods: ['POST'])]
    public function registerAction(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer)
    {
        // throw new HttpException(400, "Error: Ha ocurrido un error al registrar el usuario. Vuelve a intentarlo en unos minutos.");

        $email = $this->request->get($request, 'email');
        $username = $this->request->get($request, 'username');
        $password = $this->request->get($request, 'password');
        $gender = $this->request->get($request, 'gender', false);
        $lovegender = $this->request->get($request, 'lovegender', false);
        $meet = $this->request->get($request, 'meet', false);
        $referral = $this->request->get($request, 'referral', false);
        $mailing = $this->request->get($request, 'mailing', false);
        $birthday = \DateTime::createFromFormat('Y-m-d', explode('T', $this->request->get($request, 'birthday'))[0]);
        $provider = $this->request->get($request, 'provider', false);
        $credential = $this->request->get($request, 'credential', false);

        if (is_null($this->userRepository->findOneByUsernameOrEmail($username, $email))) {
            $user = new User();
            $user->setUsername($username);
            $user->setName($username);
            $user->setBirthday($birthday);
            $user->setGender($gender);
            $user->setLovegender(!empty($lovegender) ? $lovegender : []);
            $user->setRegisterDate();
            $user->setRegisterIp();
            $user->setBanned(false);
            $user->setPublic(true);
            $user->setHideLikes(true);
            $user->setTwoStep(false);
            $user->setVerified(false);
            $user->setMeet($meet);
            $user->setReferral($referral);
            $user->setMailing($mailing ?? true);
            $user->setMailingCode();
            $user->setRoles(['ROLE_USER']);
            $user->setLanguages(["es"]);
            if (empty($provider)) {
                $user->setEmail($email);
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setActive(false);
                $user->setVerificationCode();
            } else {
                $user->setPassword(password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT));
                switch ($provider) {
                    case 'google':
                        $client = new \Google\Client();
                        $payload = $client->verifyIdToken($credential);

                        if ($payload['email'] == $email) {
                            $user->setEmail($email);
                            $user->setGoogleId($payload['sub']);
                            $user->setActive(true);
                        } else {
                            throw new HttpException(400, "Error: Ha ocurrido un error al registrar el usuario. Vuelve a intentarlo en unos minutos.");
                        }
                        break;
                }
            }
            try {

                $this->userRepository->save($user);

                if (empty($provider)) {
                    $email = (new Email())
                        ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                        ->to(new Address($user->getEmail(), $user->getUsername()))
                        ->subject($user->getVerificationCode() . ' es tu código de activación de frikiradar')
                        ->html($this->renderView(
                            "emails/registration.html.twig",
                            [
                                'username' => $user->getUsername(),
                                'code' => $user->getVerificationCode()
                            ]
                        ));

                    $mailer->send($email);
                }

                return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default', 'datetime_format' => 'Y-m-d']), Response::HTTP_OK, [], true);
            } catch (Exception $ex) {
                $email = (new Email())
                    ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                    ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                    ->subject('Error de registro de usuario')
                    ->html("Datos del usuario:<br>" . $this->serializer->serialize($user, "json", ['groups' => 'default']) . "<br>" . $ex->getMessage());

                $mailer->send($email);
                throw new HttpException(400, "Error: Ha ocurrido un error al registrar el usuario. Vuelve a intentarlo en unos minutos.");
            }
        } else {
            throw new HttpException(400, "Error: Ya hay un usuario registrado con estos datos.");
        }
    }


    #[Route('/v1/user', name: 'get_user', methods: ['GET'])]
    public function getAction()
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $user->setLastLogin();
        $oldIp = $user->getLastIp();
        $user->setLastIP();
        $newIp = $user->getLastIp();
        if ($oldIp != $newIp) {
            $ipCountry = $this->geoService->getIpCountry($newIp);
            $user->setIpCountry($ipCountry ?? 'ES');
        }
        $this->userRepository->save($user);

        $user->setImages($user->getImages());

        return new JsonResponse($this->serializer->serialize($user, 'json', ['groups' => ['default', 'tags']]), Response::HTTP_OK, [], true);
    }


    #[Route('/v1/user/{id}', name: 'get_user_id', methods: ['GET'])]
    public function getUserAction($id)
    {
        $fromUser = $this->getUser();
        $cache = new FilesystemAdapter();

        if (!is_numeric($id)) {
            $username = $id;
            $username = str_replace('+', ' ', $username);
            $user = $this->userRepository->findOneBy(array('username' => $username));
            $id = $user->getId();
        }

        try {
            /** @var \App\Entity\User $fromUser */
            $userCache = $cache->getItem('users.get.' . $fromUser->getId() . '.' . $id);
            if (!$userCache->isHit()) {
                $userCache->expiresAfter(5 * 60);
                $toUser = $this->userRepository->findOneBy(array('id' => $id));
                $block = !empty($this->blockUserRepository->isBlocked($fromUser, $toUser)) ? true : false;
                if (!$block) {
                    $user = $this->userRepository->findOneUser($fromUser, $toUser);
                    if ($user['active']) {
                        $user['images'] = $toUser->getImages();
                    }

                    $radar = $this->radarRepository->isRadarNotified($toUser, $fromUser);
                    if (!is_null($radar)) {
                        $radar->setTimeRead(new \DateTime);
                        $this->radarRepository->save($radar);
                    }

                    $user = $this->serializer->serialize($user, "json", ['groups' => ['default'], AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
                } else {
                    $user = $this->userRepository->findBlockUser($toUser);
                    $user = $this->serializer->serialize($user, "json", ['groups' => ['default'], AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
                }

                $userCache->set($user);
                $cache->save($userCache);
            } else {
                $user = $userCache->get();
            }

            return new JsonResponse($user, Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener el usuario - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/public-user/{id}', name: 'get_public_user', methods: ['GET'])]
    public function getPublicUserAction($id)
    {
        $cache = new FilesystemAdapter();

        if (!is_numeric($id)) {
            $username = $id;
            $user = $this->userRepository->findOneBy(array('username' => $username));
            $id = $user->getId();
        }

        try {
            $userCache = $cache->getItem('users.get.' . $id);
            if (!$userCache->isHit()) {
                $toUser = $this->userRepository->findOneBy(array('id' => $id));
                if (!is_null($toUser)) {
                    $user = $this->userRepository->findPublicUser($toUser);
                    $user = $this->serializer->serialize($user, "json", ['groups' => ['default', 'tags'], AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
                    $userCache->set($user);
                    $cache->save($userCache);
                } else {
                    throw new HttpException(400, "Usuario no encontrado");
                }
            } else {
                $user = $userCache->get();
            }

            return new JsonResponse($user, Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener el usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/username/{username}', name: 'check_username', methods: ['GET'])]
    public function checkUsernameAction(string $username)
    {
        try {
            $user = $this->userRepository->findOneBy(array('username' => $username));

            if (empty($user)) {
                return new JsonResponse($this->serializer->serialize($username, "json"), Response::HTTP_OK, [], true);
            } else {
                $username = $this->userRepository->getSuggestionUsername($username);
                return new JsonResponse($this->serializer->serialize($username, "json"), Response::HTTP_OK, [], true);
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al comprobar el nombre de usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/check-login/{login}', name: 'check_login', methods: ['GET'])]
    public function checkLoginAction(string $login)
    {
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            $user = $this->userRepository->findOneBy(['email' => $login]);
        } else {
            $user = $this->userRepository->findOneBy(['username' => $login]);
        }

        if (empty($user)) {
            throw new HttpException(400, "No existe ningún usuario con este nombre o email");
        }

        return new JsonResponse($this->serializer->serialize($login, "json"), Response::HTTP_OK, [], true);
    }


    #[Route('/v1/user', name: 'update_user', methods: ['PUT'])]
    public function putAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            if ($this->request->get($request, 'id') == $user->getId()) {
                $cache = new FilesystemAdapter();
                $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
                $cache->deleteItem('users.get.' . $user->getId());
                $user->setName($this->request->get($request, 'name') ?: $this->request->get($request, 'username'));
                $user->setDescription($this->request->get($request, 'description'));
                $user->setLocation($this->request->get($request, 'location') ?: $user->getLocation());
                $user->setCountry($this->request->get($request, 'country') ?: $user->getCountry());
                $city = $this->request->get($request, 'city') ?: $user->getCity();
                if (in_array(strtolower($city), ['cdmx', 'df'])) {
                    $city = "Ciudad de México";
                }
                $user->setCity($city);
                $user->setBirthday(\DateTime::createFromFormat('Y-m-d', explode('T', $this->request->get($request, 'birthday'))[0]));
                $user->setLanguages($this->request->get($request, 'languages'));
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

                $this->userRepository->save($user);

                return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => ['default', 'tags'], 'datetime_format' => 'Y-m-d']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(401, "El usuario no eres tu, ¿intentando hacer trampa?");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al actualizar la información del usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/coordinates', name: 'coordinates', methods: ['PUT'])]
    public function putCoordinatesAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $latitude = $this->request->get($request, 'latitude');
            $longitude = $this->request->get($request, 'longitude');

            $coords = $this->geoService->geolocate($latitude, $longitude);
            $user->setCoordinates($coords);
            $this->userRepository->save($user);

            return new JsonResponse($this->serializer->serialize($coords, "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al registrar coordenadas - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/manual-geolocation', name: 'manual_geolocation', methods: ['PUT'])]
    public function putManualGeolocationAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $city = $this->request->get($request, 'city');
            $country = $this->request->get($request, 'country');

            if ($city === null || $country === null) {
                throw new HttpException(400, "El nombre de la ciudad y el país son obligatorios");
            }

            if ($user->getCountry() == $country && $user->getCity() == $city) {
                $coords = $user->getCoordinates();
            } else {
                $coords = $this->geoService->manualGeolocate($city, $country);
                $user->setCountry($country);
                $user->setCity($city);
                $user->setCoordinates($coords);
                $this->userRepository->save($user);
            }

            return new JsonResponse($this->serializer->serialize($coords, "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al registrar coordenadas - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/avatar', name: 'avatar', methods: ['POST'])]
    public function uploadAvatarAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
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

            $files = glob("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/*.jpg");
            usort($files, function ($a, $b) {
                return basename($b) <=> basename($a);
            });
            $count = 0;
            foreach ($files as $file) {
                if (!strpos($file, '-128px')) {
                    $count++;
                }
                if ($count > 9) {
                    unlink($file);
                }
            }
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            $user->setAvatar($src);

            $this->userRepository->save($user);

            $user->setImages($user->getImages());

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } else {
            throw new HttpException(400, "Error al subir la imagen");
        }
    }


    #[Route('/v1/avatar', name: 'update_avatar', methods: ['PUT'])]
    public function updateAvatarAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
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
            $this->userRepository->save($user);

            $user->setImages($user->getImages());

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al actualizar el avatar - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/delete-avatar', name: 'delete_avatar', methods: ['PUT'])]
    public function deleteAvatarAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
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
                $this->userRepository->save($user);
            }
            $user->setImages($user->getImages());

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al borrar el avatar - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/radar', name: 'radar', methods: ['PUT'])]
    public function getRadarUsers(Request $request)
    {
        $user = $this->getUser();
        ini_set('max_execution_time', 60);
        ini_set('memory_limit', '512M');

        $page = $this->request->get($request, "page");
        $ratio = $this->request->get($request, "ratio") ?: -1;
        $options = $this->request->get($request, 'options', false);
        $location = $this->request->get($request, 'location', false);
        try {
            $users = $this->userRepository->getRadarUsers($user, $page, $ratio, $options, $location);

            return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los usuarios - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/search', name: 'search', methods: ['POST'])]
    public function searchAction(Request $request)
    {
        $user = $this->getUser();
        $page = $this->request->get($request, "page");
        $order = $this->request->get($request, "order");
        $query = $this->request->get($request, "query");

        try {
            $users = $this->userRepository->searchUsers($query, $user, $order, $page);

            return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los resultados de búsqueda - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/search-by-slug', name: 'search_by_slug', methods: ['POST'])]
    public function searchBySlugAction(Request $request)
    {
        $user = $this->getUser();
        $page = $this->request->get($request, "page");
        $order = $this->request->get($request, "order");
        $slug = $this->request->get($request, "slug");

        try {
            $users = $this->userRepository->searchUsers($slug, $user, $order, $page, true);
            return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los resultados de búsqueda - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/search-usernames/{query}', name: 'search_usernames', methods: ['GET'])]
    public function searchUsernames($query)
    {
        try {
            $usernames = $this->userRepository->searchUsernames($query);

            return new JsonResponse($this->serializer->serialize($usernames, "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al buscar nombres de usuario - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/activation', name: 'activation', methods: ['PUT'])]
    public function activationAction(Request $request)
    {
        $verificationCode = $this->request->get($request, "verification_code");
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($verificationCode == $user->getVerificationCode()) {
            $user->setActive(true);
            $user->setVerificationCode(null);
            $this->userRepository->save($user);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } else {
            throw new HttpException(400, "Error al activar la cuenta");
        }
    }


    #[Route('/recover', name: 'recover-email', methods: ['POST'])]
    public function requestEmailAction(Request $request, MailerInterface $mailer)
    {
        if (preg_match('#^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $this->request->get($request, 'username'))) {
            $user = $this->userRepository->findOneBy(array('email' => $this->request->get($request, 'username')));
        } else {
            $user = $this->userRepository->findOneBy(array('username' => $this->request->get($request, 'username')));
        }

        if (!is_null($user)) {
            $user->setVerificationCode();
            $this->userRepository->save($user);

            try {
                $email = (new Email())
                    ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                    ->to(new Address($user->getEmail(), $user->getUsername()))
                    ->replyTo(new Address('hola@frikiradar.com', 'frikiradar'))
                    ->subject($user->getVerificationCode() . ' es el código para generar una nueva contraseña de frikiradar')
                    ->html($this->renderView(
                        "emails/recover.html.twig",
                        [
                            'username' => $user->getUsername(),
                            'code' => $user->getVerificationCode()
                        ]
                    ));

                $mailer->send($email);

                return new JsonResponse($this->serializer->serialize("Email enviado correctamente", "json"), Response::HTTP_OK, [], true);
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al enviar el email de recuperación - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "No hay ningú usuario registrado con estos datos");
        }
    }


    #[Route('/recover', name: 'recover-password', methods: ['PUT'])]
    public function recoverPasswordAction(Request $request, UserPasswordHasherInterface $passwordHasher)
    {
        $verificationCode = $this->request->get($request, "verification_code");

        if (preg_match('#^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $this->request->get($request, 'username'))) {
            $user = $this->userRepository->findOneBy(array('email' => $this->request->get($request, 'username'), 'verificationCode' => $verificationCode));
        } else {
            $user = $this->userRepository->findOneBy(array('username' => $this->request->get($request, 'username'), 'verificationCode' => $verificationCode));
        }

        if (!is_null($user)) {
            $user->setPassword($passwordHasher->hashPassword($user, $this->request->get($request, 'password')));
            $user->setVerificationCode(null);
            $this->userRepository->save($user);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } else {
            throw new HttpException(400, "Error al recuperar la cuenta");
        }
    }


    #[Route('/v1/password', name: 'change-password', methods: ['PUT'])]
    public function changePasswordAction(Request $request, UserPasswordHasherInterface $passwordHasher)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($passwordHasher->isPasswordValid($user, $this->request->get($request, "old_password"))) {
            $user->setPassword($passwordHasher->hashPassword($user, $this->request->get($request, 'new_password')));

            $this->userRepository->save($user);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } else {
            throw new HttpException(400, "La contraseña actual no es válida");
        }
    }


    #[Route('/v1/email', name: 'change-email', methods: ['PUT'])]
    public function changeEmailAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getEmail() == $this->request->get($request, "old_email")) {
            $user->setEmail($this->request->get($request, 'new_email'));

            $this->userRepository->save($user);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } else {
            throw new HttpException(400, "El email actual no es válido");
        }
    }


    #[Route('/v1/username', name: 'change-username', methods: ['PUT'])]
    public function changeUsernameAction(Request $request)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $user->setUsername($this->request->get($request, 'new_username'));

            $this->userRepository->save($user);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Ya hay un usuario con ese nombre - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/block', name: 'block', methods: ['PUT'])]
    public function putBlockAction(Request $request, MailerInterface $mailer)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $blockUser = $this->userRepository->findOneBy(array('id' => $this->request->get($request, 'user')));

            $newBlock = new BlockUser();
            $newBlock->setDate(new \DateTime);
            $newBlock->setFromUser($user);
            $newBlock->setBlockUser($blockUser);
            $newBlock->setNote($this->request->get($request, 'note', false));
            $this->blockUserRepository->save($newBlock);

            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $user->getId() . '.' . $blockUser->getId());

            if (!empty($newBlock->getNote())) {
                // Enviar email al administrador informando del motivo
                $email = (new Email())
                    ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                    ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                    ->subject('Nuevo usuario bloqueado')
                    ->html("El usuario " . $user->getUserIdentifier() . " ha bloqueado al usuario <a href='https://frikiradar.app/" . urlencode($blockUser->getUsername()) . "'>" . $blockUser->getUsername() . "</a> por el siguiente motivo: " . $newBlock->getNote());

                $mailer->send($email);
            }

            return new JsonResponse($this->serializer->serialize("Usuario bloqueado correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al bloquear usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/block/{id}', name: 'unblock', methods: ['DELETE'])]
    public function removeBlockAction(int $id)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $blockUser = $this->userRepository->findOneBy(array('id' => $id));

            $block = $this->blockUserRepository->findOneBy(array('block_user' => $blockUser, 'from_user' => $this->getUser()));
            $this->blockUserRepository->remove($block);

            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $user->getId() . '.' . $blockUser->getId());

            $users = $this->blockUserRepository->getBlockUsers($user);

            return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desbloquear el usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/blocks', name: 'blocks', methods: ['GET'])]
    public function getBlocksAction()
    {
        $users = $this->blockUserRepository->getBlockUsers($this->getUser());

        return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/report', name: 'report', methods: ['PUT'])]
    public function putReportAction(Request $request, MailerInterface $mailer)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $reportUser = $this->userRepository->findOneBy(array('id' => $this->request->get($request, 'user')));
            $note = $this->request->get($request, 'note', false);

            if (!empty($note)) {
                // Enviar email al administrador informando del motivo
                $email = (new Email())
                    ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                    ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                    ->subject('Nuevo usuario reportado')
                    ->html("El usuario " . $user->getUserIdentifier() . " ha reportado al usuario <a href='https://frikiradar.app/" . urlencode($reportUser->getUsername()) . "'>" . $reportUser->getUsername() . "</a> por el siguiente motivo: " . $note);

                $mailer->send($email);
            }

            return new JsonResponse($this->serializer->serialize("Usuario reportado correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al reportar usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/hide', name: 'hide', methods: ['PUT'])]
    public function putHideAction(Request $request)
    {
        try {
            $fromUser = $this->getUser();
            $hideUser = $this->userRepository->findOneBy(array('id' => $this->request->get($request, 'user')));

            if (empty($this->hideUserRepository->isHide($fromUser, $hideUser))) {
                $newHide = new HideUser();
                $newHide->setDate(new \DateTime);
                $newHide->setFromUser($fromUser);
                $newHide->setHideUser($hideUser);
                $this->hideUserRepository->save($newHide);

                return new JsonResponse($this->serializer->serialize("Usuario ocultado correctamente", "json"), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "Error al ocultar usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al ocultar usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/hide/{id}', name: 'unhide', methods: ['DELETE'])]
    public function removeHideAction(int $id)
    {
        try {
            $hideUser = $this->userRepository->findOneBy(array('id' => $id));

            $hide = $this->hideUserRepository->findOneBy(array('hide_user' => $hideUser, 'from_user' => $this->getUser()));
            $this->hideUserRepository->remove($hide);

            $users = $this->hideUserRepository->getHideUsers($this->getUser());

            return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desocultar el usuario - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/hides', name: 'hides', methods: ['GET'])]
    public function getHidesAction()
    {
        $users = $this->hideUserRepository->getHideUsers($this->getUser());

        return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
    }


    #[Route('/v1/view', name: 'view', methods: ['PUT'])]
    public function putViewAction(Request $request)
    {
        try {
            $fromUser = $this->getUser();
            if ($fromUser->getUserIdentifier() !== 'frikiradar') {
                $viewUser = $this->userRepository->findOneBy(array('id' => $this->request->get($request, 'user')));

                $newView = new ViewUser();
                $newView->setDate(new \DateTime);
                $newView->setFromUser($fromUser);
                $newView->setToUser($viewUser);
                $this->viewUserRepository->save($newView);
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


    #[Route('/v1/two-step', name: 'two_step', methods: ['GET'])]
    public function twoStepAction(MailerInterface $mailer)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $user->setVerificationCode();
            $this->userRepository->save($user);

            $subject = $user->getVerificationCode() . ' es el código para verificar tu inicio de sesión';

            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject($subject)
                ->html($this->renderView(
                    "emails/verification-code.html.twig",
                    [
                        'subject' => $subject,
                        'username' => $user->getUserIdentifier(),
                        'code' => $user->getVerificationCode()
                    ]
                ));

            $mailer->send($email);

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

        return new JsonResponse($this->serializer->serialize($response, "json"), Response::HTTP_OK, [], true);
    }


    #[Route('/v1/two-step', name: 'verify_session', methods: ['PUT'])]
    public function verifyLoginAction(Request $request)
    {
        $verificationCode = $this->request->get($request, "verification_code");
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getVerificationCode() == $verificationCode) {
            try {
                $user->setVerificationCode(null);
                $this->userRepository->save($user);

                return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } catch (Exception $e) {
                throw new HttpException(400, "Error al verificar tu sesión: " . $e);
            }
        } else {
            throw new HttpException(400, "El código de verificación no es válido.");
        }
    }

    #[Route('/v1/verify', name: 'send_verification', methods: ['GET'])]
    public function sendVerificationAction(MailerInterface $mailer)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $user->setVerificationCode();
            $this->userRepository->save($user);

            $subject = $user->getVerificationCode() . ' es el código para verificar tu cuenta';

            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject($subject)
                ->html($this->renderView(
                    "emails/verification-code.html.twig",
                    [
                        'subject' => $subject,
                        'username' => $user->getUserIdentifier(),
                        'code' => $user->getVerificationCode()
                    ]
                ));

            $mailer->send($email);

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

        return new JsonResponse($this->serializer->serialize($response, "json"), Response::HTTP_OK, [], true);
    }


    #[Route('/v1/verify', name: 'verify_code', methods: ['PUT'])]
    public function verifyCodeAction(Request $request)
    {
        $verificationCode = $this->request->get($request, "verification_code");
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getVerificationCode() == $verificationCode) {
            try {
                $user->setVerificationCode(null);
                $this->userRepository->save($user);

                return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } catch (Exception $e) {
                throw new HttpException(400, "Error al verificar tu cuenta: " . $e);
            }
        } else {
            throw new HttpException(400, "El código de verificación no es válido.");
        }
    }


    #[Route('/unsubscribe/{code}', name: 'unsubscribe_mailing', methods: ['GET'])]
    public function unsubscribeMailing(string $code)
    {
        $user = $this->userRepository->findOneBy(array('mailing_code' => $code));
        if ($user) {
            $user->setMailing(false);
            $user->setMailingCode();
            $this->userRepository->save($user);
            $data = [
                'code' => 200,
                'message' => "Te has desuscrito correctamente de nuestros emails.",
            ];
            return new JsonResponse($data, 200);
        } else {
            throw new HttpException(400, "El código de desuscripción para mailing no es válido.");
        }
    }

    #[Route('/v1/disable', name: 'disable', methods: ['PUT'])]
    public function disableAction(Request $request, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $verificationCode = $this->request->get($request, "code", false);
        $note = $this->request->get($request, "note", false);

        $checkVerification = false;

        $checkVerification = $user->getVerificationCode() == $verificationCode;

        if ($checkVerification) {
            try {
                // ponemos usuario en disable
                $user->setActive(false);
                // borramos sus dispositivos
                $user->removeDevices();

                $this->userRepository->save($user);

                if (!empty($note)) {
                    // Enviar email al administrador informando del motivo
                    $email = (new Email())
                        ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                        ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                        ->subject($user->getUserIdentifier() . ' ha desactivado su cuenta.')
                        ->html("El usuario " . $user->getUserIdentifier() . " ha desactivado su cuenta por el siguiente motivo: " . $note);

                    $mailer->send($email);
                }

                return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al desactivar la cuenta - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "La contraseña no es correcta");
        }
    }

    #[Route('/v1/remove-account', name: 'remove_account', methods: ['PUT'])]
    public function removeAccountAction(Request $request, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher, AuthorizationCheckerInterface $security)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($security->isGranted('ROLE_ADMIN')) {
            return new HttpException(400, "No puedes eliminar tu cuenta porque eres administrador");
        }

        $verificationCode = $this->request->get($request, "code", false);
        $note = $this->request->get($request, "note", false);

        $checkVerification = false;

        $checkVerification = $user->getVerificationCode() == $verificationCode;

        if ($checkVerification) {
            try {
                // borramos archivos de chat
                $this->chatRepository->deleteChatsFiles($user);

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

                $username = $user->getUserIdentifier();
                // Eliminamos usuario
                $this->userRepository->remove($user);

                if (!empty($note)) {
                    // Enviar email al administrador informando del motivo
                    $email = (new Email())
                        ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                        ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                        ->subject($username . ' ha eliminado su cuenta.')
                        ->html("El usuario " . $username . " ha eliminado su cuenta por el siguiente motivo: " . $note);

                    $mailer->send($email);
                }

                $data = [
                    'code' => 200,
                    'message' => "Usuario eliminado correctamente",
                ];
                return new JsonResponse($data, 200);
            } catch (Exception $ex) {
                // enviamos email al administrador informando del error
                $email = (new Email())
                    ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                    ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                    ->subject($username . ' ha intentado eliminar su cuenta.')
                    ->html("El usuario " . $username . " ha intentado eliminar su cuenta por el siguiente motivo: " . $note . "<br><br>El error ha sido: " . $ex->getMessage());

                $mailer->send($email);

                throw new HttpException(400, "Error al eliminar la cuenta - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "La contraseña no es correcta");
        }
    }

    #[Route('/v1/premium', name: 'premium', methods: ['PUT'])]
    public function setPremiumAction(Request $request)
    {
        $premium_expiration = $this->request->get($request, "premium_expiration", true);
        $premiumExpiration = new \DateTime($premium_expiration);
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $user->setPremiumExpiration($premiumExpiration);
            $user->setVerified(true);
            $this->userRepository->save($user);

            if (count($user->getPayments()) == 0 && $user->getMeet() == "friend") {
                $referralUsername = $user->getReferral();
                if (!empty($referralUsername)) {
                    $referralUsername = ltrim($referralUsername, '@');
                    if (strpos($referralUsername, '@') !== false) {
                        $friend = $this->userRepository->findOneBy(array('email' => $referralUsername));
                    } else {
                        $friend = $this->userRepository->findOneBy(array('username' => $referralUsername));
                    }

                    if ($friend->getPremiumExpiration() >= new \DateTime) {
                        $friendDatetime = $friend->getPremiumExpiration();
                    } else {
                        $friendDatetime = new \DateTime;
                    }

                    $friendDatetime = (new \DateTime())->setTimestamp($friendDatetime->getTimestamp())->add(new DateInterval('P30D'));
                    $friend->setPremiumExpiration($friendDatetime);

                    $this->userRepository->save($friend);

                    $title = "¡Amigo reclutado!";
                    $text = "Has conseguido 1 mes de frikiradar UNLIMITED gratuito. Gracias a tu amigo " . $user->getName() . " ¡Esperamos que lo disfrutes!";
                    $url = "/tabs/radar";
                    $this->notification->push($user, $friend, $title, $text, $url, "premium");
                }
            }

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir los días premium - Error: {$ex->getMessage()}");
        }
    }
}
