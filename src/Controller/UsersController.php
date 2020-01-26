<?php

/**
 * UsersController.php
 *
 * Users Controller
 *
 * @category   Controller
 * @package    FrikiRadar
 * @author     Alberto Merino
 * @copyright  2019 frikiradar.com
 */

namespace App\Controller;

use App\Entity\User;
use App\Entity\Tag;
use App\Entity\Category;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcherInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use App\Service\FileUploader;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Entity\BlockUser;
use App\Entity\HideUser;
use App\Service\GeolocationService;
use DateInterval;
use App\Service\NotificationService;

/**
 * Class UsersController
 *
 * @Route("/api")
 */
class UsersController extends FOSRestController
{
    // USER URI's

    /**
     * @Rest\Post("/login", name="user_login")
     *
     * @SWG\Response(
     *     response=200,
     *     description="User was logged in successfully"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="User was not logged in successfully"
     * )
     *
     * @SWG\Parameter(
     *     name="username",
     *     in="body",
     *     type="string",
     *     description="The username",
     *     schema={
     *     }
     * )
     *
     * @SWG\Parameter(
     *     name="password",
     *     in="body",
     *     type="string",
     *     description="The password",
     *     schema={}
     * )
     *
     * @SWG\Tag(name="User")
     */
    public function getLoginAction()
    {
    }

    /**
     * @Rest\Post("/register", name="user_register")
     *
     * @SWG\Response(
     *     response=201,
     *     description="User was successfully registered"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="User was not successfully registered"
     * )
     *
     * @SWG\Parameter(
     *     name="email",
     *     in="body",
     *     type="string",
     *     description="The username",
     *     schema={}
     * )
     *
     * @SWG\Parameter(
     *     name="username",
     *     in="body",
     *     type="string",
     *     description="The username",
     *     schema={}
     * )
     *
     * @SWG\Parameter(
     *     name="birthday",
     *     in="query",
     *     type="string",
     *     description="The birthday"
     * )
     * 
     * @SWG\Parameter(
     *     name="gender",
     *     in="query",
     *     type="string",
     *     description="The gender"
     * )
     * 
     * @SWG\Parameter(
     *     name="password",
     *     in="query",
     *     type="string",
     *     description="The password"
     * )
     *
     * @SWG\Tag(name="User")
     */
    public function registerAction(Request $request, UserPasswordEncoderInterface $encoder, \Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $email = $request->request->get('email');
            $username = $request->request->get('username');
            $password = $request->request->get('password');
            $birthday = \DateTime::createFromFormat('Y-m-d', $request->request->get('birthday'));

            if (is_null($em->getRepository('App:User')->findOneByUsernameOrEmail($username, $email))) {
                $user = new User();
                $user->setEmail($email);
                $user->setUsername($username);
                $user->setName($username);
                $user->setPassword($encoder->encodePassword($user, $password));
                $user->setBirthday($birthday);
                $user->setGender($request->request->get('gender') ?: null);
                $user->setLovegender($request->request->get('lovegender') ?: null);
                $user->setRegisterDate();
                $user->setRegisterIp();
                $user->setActive(false);
                $user->setTwoStep(false);
                $user->setVerified(false);
                $user->setMeet($request->request->get('meet') ?: null);
                $user->setReferral($request->request->get('referral') ?: null);
                $user->setMailing($request->request->get('mailing') ?: false);
                $user->setVerificationCode();
                $user->setRoles(['ROLE_USER']);

                if (date("m-d") == "11-11") {
                    $user->setCredits(5);
                } else {
                    $user->setCredits(3);
                }

                $geolocation = new GeolocationService();
                $coords = $geolocation->geolocate($user->getIP());
                $user->setCoordinates($coords);
                $em->persist($user);

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

                $em->flush();

                return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
            } else {
                throw new HttpException(400, "Error: Ya hay un usuario registrado con estos datos.");
            }
        } catch (Exception $ex) {
            $message = (new \Swift_Message('Error de registro de usuario'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setBody("Datos del usuario:<br>" . $serializer->serialize($user, "json") . "<br>" . $ex->getMessage());

            $mailer->send($message);
            throw new HttpException(400, "Error: Ha ocurrido un error al registrar el usuario. Vuelve a intentarlo en unos minutos.");
        }
    }

    /**
     * @Rest\Get("/v1/user")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Usuario obtenido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener el usuario"
     * )
     * 
     * @SWG\Tag(name="Get User")
     */
    public function getAction()
    {
        $em = $this->getDoctrine()->getManager();
        $serializer = $this->get('jms_serializer');

        $user = $this->getUser();
        $user->setImages($user->getImages());
        $user->setIsPremium($user->isPremium());

        $user->setLastLogin();
        $em->persist($user);
        $em->flush();

        return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default', 'tags'))));
    }

    /**
     * @Rest\Get("/v1/user/{id}")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Usuario obtenido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener el usuario"
     * )
     * 
     */
    public function getUserAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();
        try {
            $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
            $user = $em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);
            $user['images'] = $toUser->getImages();

            $radar = $em->getRepository('App:Radar')->isRadarNotified($toUser, $this->getUser());
            if (!is_null($radar)) {
                $radar->setTimeRead(new \DateTime);
                $em->merge($radar);
                $em->flush();
            }

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default', 'tags'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener el usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/username/{username}")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Nombre de usuario libre"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Nombre de usuario no disponible"
     * )
     * 
     */
    public function checkUsernameAction(string $username)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $em->getRepository('App:User')->findOneBy(array('username' => $username));

            if (empty($user)) {
                return new Response($serializer->serialize($username, "json"));
            } else {
                $username = $em->getRepository('App:User')->getSuggestionUsername($username);
                return new Response($serializer->serialize($username, "json"));
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al comprobar el nombre de usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Put("/v1/user")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Usuario actualizado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar el usuario"
     * )
     * 
     * @ParamConverter("newUser", converter="fos_rest.request_body")
     * @param User $newUser
     */
    public function putAction(User $newUser)
    {
        $serializer = $this->get('jms_serializer');

        try {
            if ($newUser->getId() == $this->getUser()->getId()) {
                $em = $this->getDoctrine()->getManager();

                /**
                 * @var User
                 */
                $user = $this->getUser();
                $user->setName($newUser->getName() ?: $newUser->getUsername());
                $user->setDescription($newUser->getDescription());
                $user->setBirthday($newUser->getBirthday());
                $user->setGender($newUser->getGender());
                $user->setOrientation($newUser->getOrientation());
                $user->setPronoun($newUser->getPronoun());
                $user->setRelationship($newUser->getRelationship());
                $user->setStatus($newUser->getStatus());
                $user->setMinage($newUser->getMinage());
                $user->setMaxage($newUser->getMaxage());
                $user->setLovegender($newUser->getLovegender());
                $user->setConnection($newUser->getConnection());
                $user->setHideLocation($newUser->getHideLocation());
                $user->setBlockMessages($newUser->getBlockMessages());
                $user->setTwoStep($newUser->getTwoStep());
                $user->setHideConnection($newUser->getHideConnection());

                $user->setMailing($newUser->getMailing());
                $user->setIsPremium($user->isPremium());

                foreach ($user->getTags() as $tag) {
                    $em->remove($tag);
                }
                $em->merge($user);
                $em->flush();

                foreach ($newUser->getTags() as $tag) {
                    $category = $em->getRepository('App:Category')->findOneBy(array('name' => $tag->getCategory()->getName()));
                    $oldTag = $em->getRepository('App:Tag')->findOneBy(array('name' => $tag->getName(), 'user' => $user->getId(), 'category' => !empty($category) ? $category->getId() : null));

                    if (empty($oldTag)) {
                        $newTag = new Tag();
                        $newTag->setUser($user);
                        $newTag->setName($tag->getName());

                        if (!empty($category)) {
                            $newTag->setCategory($category);
                        } else {
                            $newCategory = new Category();
                            $newCategory->setName($tag->getCategory()->getName());
                            $newTag->setCategory($newCategory);

                            $em->persist($newCategory);
                        }

                        $user->addTag($newTag);
                    }
                    $em->persist($user);
                    $em->flush();
                }

                if (!$user->getCoordinates()) {
                    $geolocation = new GeolocationService();
                    $coords = $geolocation->geolocate($user->getIP());
                    $user->setCoordinates($coords);
                }

                $em->persist($user);
                $em->flush();
                $user->setAvatar($user->getAvatar()); //TODO: quitar cuando esten todos en db

                return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default', 'tags'))));
            } else {
                throw new HttpException(401, "El usuario no eres tu, ¿intentando hacer trampa?");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al actualizar la información del usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Put("/v1/coordinates", name="coordinates")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Coordenadas actualizadas correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar las coordenadas"
     * )
     * 
     * @SWG\Parameter(
     *     name="latitude",
     *     in="body",
     *     type="string",
     *     description="Latitude",
     *     schema={}
     * )
     *
     * 
     * @SWG\Parameter(
     *     name="longitude",
     *     in="body",
     *     type="string",
     *     description="Longitude",
     *     schema={}
     * )
     *
     * @Rest\View(serializerGroups={"coordinates"})
     */
    public function putCoordinatesAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $this->getUser();

            $geolocation = new GeolocationService();
            $coords = $geolocation->geolocate($user->getIP(), $request->request->get('latitude'), $request->request->get('longitude'));
            $user->setCoordinates($coords);
            $em->persist($user);
            $em->flush();

            $location = $geolocation->getLocationName($coords->getLatitude(), $coords->getLongitude());
            if ($location) {
                $user->setLocation($location["locality"]);
                $user->setCountry($location["country"]);
                $em->persist($user);
                $em->flush();
            }

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al registrar coordenadas - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Post("/v1/avatar")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Avatar actualizado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar el avatar"
     * )
     * 
     * @SWG\Parameter(
     *     name="avatar",
     *     in="formData",
     *     type="file",
     *     description="avatar",
     *     schema={}
     * )
     *
     */
    public function uploadAvatarAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $serializer = $this->get('jms_serializer');
        $avatar = $request->files->get('avatar');

        $user = $this->getUser();

        $id = $user->getId();
        $filename = date('YmdHis');
        $uploader = new FileUploader("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/", $filename);
        $image = $uploader->upload($avatar);

        if (isset($image)) {
            $files = glob("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/*.jpg");
            usort($files, function ($a, $b) {
                return basename($b) <=> basename($a);
            });
            foreach ($files as $key => $file) {
                if ($key > 3) {
                    unlink($file);
                }
            }

            // $server = "https://$_SERVER[HTTP_HOST]";
            $server = "https://app.frikiradar.com";
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);

            $user->setAvatar($src);
            $em->persist($user);
            $em->flush();

            $user->setImages($user->getImages());

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } else {
            throw new HttpException(400, "Error al subir la imagen");
        }
    }

    /**
     * @Rest\Put("/v1/avatar")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Avatar actualizado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar el avatar"
     * )
     * 
     * @SWG\Parameter(
     *     name="avatar",
     *     in="body",
     *     type="string",
     *     description="Src del avatar elegido",
     *     schema={}
     * )
     *
     */
    public function updateAvatarAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $serializer = $this->get('jms_serializer');

        try {
            $src = $request->request->get('avatar');
            $user = $this->getUser();
            $user->setAvatar($src);
            $em->persist($user);
            $em->flush();

            $user->setImages($user->getImages());

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al actualizar el avatar - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Put("/v1/delete-avatar")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Avatar borrado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al borrar el avatar"
     * )
     * 
     * @SWG\Parameter(
     *     name="avatar",
     *     in="body",
     *     type="string",
     *     description="Src del avatar elegido",
     *     schema={}
     * )
     *
     */
    public function deleteAvatarAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $serializer = $this->get('jms_serializer');

        try {
            $src = $request->request->get('avatar');
            $user = $this->getUser();

            $f = explode("/", $src);
            $filename = $f[count($f) - 1];
            $file = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $user->getId() . "/" . $filename;
            unlink($file);

            $user->setImages($user->getImages());

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al borrar el avatar - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/radar/{ratio}")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Coordenadas actualizadas correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar las coordenadas"
     * )
     * 
     * @Rest\QueryParam(
     *     name="page",
     *     default="1",
     *     description="Radar page"
     * )
     * 
     */
    public function getOldRadarUsers(int $ratio, ParamFetcherInterface $params)
    {
        ini_set('max_execution_time', 60);
        ini_set('memory_limit', '512M');

        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $page = $params->get("page");

        try {
            $ratio = $ratio > 25000 ? 25000 : $ratio;
            $users = $em->getRepository('App:User')->getUsersByDistance($this->getUser(), $ratio, $page);

            /* PONDERACIÓN SOBRE 100
            $index = 1 / (max(array_column($users, 'match')) / 100);
            foreach ($users as $key => $rUsers) {
                $users[$key]["match"] = $rUsers["match"] * $index;
            }*/

            return new Response($serializer->serialize($users, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los usuarios - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Put("/v1/radar/{page}")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Coordenadas actualizadas correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar las coordenadas"
     * )
     * 
     * @Rest\QueryParam(
     *     name="page",
     *     default="1",
     *     description="Radar page"
     * )
     * 
     */
    public function getRadarUsers(int $page)
    {
        ini_set('max_execution_time', 60);
        ini_set('memory_limit', '512M');

        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {

            $users = $em->getRepository('App:User')->getRadarUsers($this->getUser(), $page);

            return new Response($serializer->serialize($users, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los usuarios - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Post("/v1/search")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Resultados obtenidos correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al encontrar coincidencias"
     * )
     * 
     * @SWG\Parameter(
     *     name="query",
     *     in="body",
     *     type="string",
     *     description="Query",
     *     schema={}
     * )
     *
     * @SWG\Parameter(
     *     name="order",
     *     in="body",
     *     type="string",
     *     description="Order",
     *     schema={}
     * )
     * 
     * @Rest\QueryParam(
     *     name="page",
     *     default="1",
     *     description="Radar page"
     * )
     */
    public function searchAction(Request $request, ParamFetcherInterface $params)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $page = $params->get("page");
        $order = $request->request->get("order");

        try {
            $users = $em->getRepository('App:User')->searchUsers($request->request->get("query"), $this->getUser(), $order, $page);

            return new Response($serializer->serialize($users, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los resultados de búsqueda - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/activation", name="activation-email")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Email enviado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al enviar el email"
     * )
     * 
     */
    public function activationEmailAction(\Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $this->getUser();
            $user->setVerificationCode();
            $em->persist($user);
            $em->flush();

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

            return new Response($serializer->serialize("Email enviado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar el email de activación - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Put("/v1/activation", name="activation")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Email enviado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al enviar el email"
     * )
     * 
     * @SWG\Parameter(
     *     name="verification_code",
     *     in="body",
     *     type="string",
     *     description="Código de activación",
     *     schema={}
     * )
     * 
     */
    public function activationAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $verificationCode = $request->request->get("verification_code");
        $user = $em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId(), 'verificationCode' => $verificationCode));
        if (!is_null($user)) {
            $user->setActive(true);
            $user->setVerificationCode(null);
            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } else {
            throw new HttpException(400, "Error al activar la cuenta");
        }
    }

    /**
     * @Rest\Post("/recover", name="recover-email")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Email enviado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al enviar el email"
     * )
     * 
     * @SWG\Parameter(
     *     name="username",
     *     in="body",
     *     type="string",
     *     description="Nombre de usuario o contraseña",
     *     schema={}
     * )
     * 
     */
    public function requestEmailAction(Request $request, \Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        if (preg_match('#^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $request->request->get('username'))) {
            $user = $em->getRepository('App:User')->findOneBy(array('email' => $request->request->get('username')));
        } else {
            $user = $em->getRepository('App:User')->findOneBy(array('username' => $request->request->get('username')));
        }

        if (!is_null($user)) {
            $user->setVerificationCode();
            $em->persist($user);
            $em->flush();

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

                return new Response($serializer->serialize("Email enviado correctamente", "json"));
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al enviar el email de recuperación - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "No hay ningú usuario registrado con estos datos");
        }
    }

    /**
     * @Rest\Put("/recover", name="recover-password")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Email enviado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al enviar el email"
     * )
     * 
     * @SWG\Parameter(
     *     name="verification_code",
     *     in="body",
     *     type="string",
     *     description="Código de activación",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="password",
     *     in="query",
     *     type="string",
     *     description="The password",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="username",
     *     in="query",
     *     type="string",
     *     description="The username",
     *     schema={}
     * )
     * 
     */
    public function recoverPasswordAction(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $verificationCode = $request->request->get("verification_code");

        if (preg_match('#^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $request->request->get('username'))) {
            $user = $em->getRepository('App:User')->findOneBy(array('email' => $request->request->get('username'), 'verificationCode' => $verificationCode));
        } else {
            $user = $em->getRepository('App:User')->findOneBy(array('username' => $request->request->get('username'), 'verificationCode' => $verificationCode));
        }

        if (!is_null($user)) {
            $user->setPassword($encoder->encodePassword($user, $request->request->get('password')));
            $user->setVerificationCode(null);
            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } else {
            throw new HttpException(400, "Error al recuperar la cuenta");
        }
    }

    /**
     * @Rest\Put("/v1/password", name="change-password")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Contraseña cambiada correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al cambiar la contraseña"
     * )
     * 
     * 
     * @SWG\Parameter(
     *     name="old_password",
     *     in="query",
     *     type="string",
     *     description="The old password",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="new_password",
     *     in="query",
     *     type="string",
     *     description="The new password",
     *     schema={}
     * )
     * 
     */
    public function changePasswordAction(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();

        if ($user->getPassword() == $encoder->encodePassword($user, $request->request->get("old_password"))) {
            $user->setPassword($encoder->encodePassword($user, $request->request->get('new_password')));

            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } else {
            throw new HttpException(400, "La contraseña actual no es válida");
        }
    }

    /**
     * @Rest\Put("/v1/email", name="change-email")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Email cambiado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al cambiar el email"
     * )
     * 
     * 
     * @SWG\Parameter(
     *     name="old_email",
     *     in="query",
     *     type="string",
     *     description="The old email",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="new_email",
     *     in="query",
     *     type="string",
     *     description="The new email",
     *     schema={}
     * )
     * 
     */
    public function changeEmailAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();

        if ($user->getEmail() == $request->request->get("old_email")) {
            $user->setEmail($request->request->get('new_email'));

            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } else {
            throw new HttpException(400, "El email actual no es válido");
        }
    }

    /**
     * @Rest\Put("/v1/username", name="change-username")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Username cambiado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al cambiar el username"
     * )
     * 
     * @SWG\Parameter(
     *     name="new_username",
     *     in="query",
     *     type="string",
     *     description="The new username",
     *     schema={}
     * )
     * 
     */
    public function changeUsernameAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $this->getUser();
            $user->setUsername($request->request->get('new_username'));

            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Ya hay un usuario con ese nombre - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Put("/v1/block", name="block")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Usuario bloqueado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al bloquear el usuario"
     * )
     * 
     * @SWG\Parameter(
     *     name="user",
     *     in="body",
     *     type="string",
     *     description="To user block",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="note",
     *     in="body",
     *     type="string",
     *     description="Motive of block",
     *     schema={}
     * )
     *
     */
    public function putBlockAction(Request $request, \Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $blockUser = $em->getRepository('App:User')->findOneBy(array('id' => $request->request->get('user')));

            $newBlock = new BlockUser();
            $newBlock->setDate(new \DateTime);
            $newBlock->setFromUser($this->getUser());
            $newBlock->setBlockUser($blockUser);
            $newBlock->setNote($request->request->get('note'));
            $em->persist($newBlock);
            $em->flush();

            if (!empty($newBlock->getNote())) {
                // Enviar email al administrador informando del motivo
                $message = (new \Swift_Message('Nuevo usuario bloqueado'))
                    ->setFrom([$this->getUser()->getEmail() => $this->getUser()->getUsername()])
                    ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setBody("El usuario " . $this->getUser()->getUsername() . " ha bloqueado al usuario <a href='mailto:" . $blockUser->getEmail() . "'>" . $blockUser->getUsername() . "</a> por el siguiente motivo: " . $newBlock->getNote(), 'text/html');

                if (0 === $mailer->send($message)) {
                    // throw new HttpException(400, "Error al enviar el email con motivo del bloqueo");
                }
            }

            return new Response($serializer->serialize("Usuario bloqueado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al bloquear usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Delete("/v1/block/{id}", name="unblock")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Bloqueo eliminado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al eliminar el bloqueo"
     * )
     *
     */
    public function removeBlockAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $blockUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));

            $block = $em->getRepository('App:BlockUser')->findOneBy(array('block_user' => $blockUser, 'from_user' => $this->getUser()));
            $em->remove($block);
            $em->flush();

            $users = $em->getRepository('App:BlockUser')->getBlockUsers($this->getUser());

            foreach ($users as $key => $u) {
                $user = $em->getRepository('App:User')->findOneBy(array('id' => $u['id']));
                $users[$key]['avatar'] = $user->getAvatar() ?: null;
            }

            return new Response($serializer->serialize($users, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desbloquear el usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/blocks")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Usuarios bloqueados obtenidos correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener los usuarios bloqueados"
     * )
     * 
     * @SWG\Tag(name="Get Blocks")
     */
    public function getBlocksAction()
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('App:BlockUser')->getBlockUsers($this->getUser());

        foreach ($users as $key => $u) {
            $user = $em->getRepository('App:User')->findOneBy(array('id' => $u['id']));
            $users[$key]['avatar'] = $user->getAvatar() ?: null;
        }

        return new Response($serializer->serialize($users, "json", SerializationContext::create()->setGroups(array('default'))));
    }

    /**
     * @Rest\Put("/v1/hide", name="hide")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Usuario ocultado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al ocultar el usuario"
     * )
     * 
     * @SWG\Parameter(
     *     name="user",
     *     in="body",
     *     type="string",
     *     description="To user hide",
     *     schema={}
     * )
     *
     */
    public function putHideAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $hideUser = $em->getRepository('App:User')->findOneBy(array('id' => $request->request->get('user')));

            $newHide = new HideUser();
            $newHide->setDate(new \DateTime);
            $newHide->setFromUser($this->getUser());
            $newHide->setHideUser($hideUser);
            $em->persist($newHide);
            $em->flush();

            return new Response($serializer->serialize("Usuario ocultado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al ocultar usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/two-step", name="two step")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Email enviado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al enviar el email"
     * )
     * 
     */
    public function twoStepAction(\Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $this->getUser();
            $user->setVerificationCode();
            $em->persist($user);
            $em->flush();

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

        return new Response($serializer->serialize($response, "json"));
    }

    /**
     * @Rest\Put("/v1/two-step", name="verify session")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Sesión verificada correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al verificar la sesión"
     * )
     * 
     * @SWG\Parameter(
     *     name="verification_code",
     *     in="body",
     *     type="string",
     *     description="Código de verificación",
     *     schema={}
     * )
     * 
     */
    public function verifyLoginAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $verificationCode = $request->request->get("verification_code");
        $user = $em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId(), 'verificationCode' => $verificationCode));
        if (!is_null($user)) {
            try {
                $user->setVerificationCode(null);
                $em->persist($user);
                $em->flush();

                return new Response($serializer->serialize($this->getUser(), "json", SerializationContext::create()->setGroups(array('default'))));
            } catch (Exception $e) {
                throw new HttpException(400, "Error al verificar tu sesión: " . $e);
            }
        } else {
            throw new HttpException(400, "El código de verificación no es válido.");
        }
    }

    /**
     * @Rest\Put("/v1/disable", name="disable")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Usuario desactivado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al desactivar el usuario"
     * )
     * 
     * @SWG\Parameter(
     *     name="password",
     *     in="query",
     *     type="string",
     *     description="The password",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="note",
     *     in="body",
     *     type="string",
     *     description="Motive of disable account",
     *     schema={}
     * )
     * 
     */
    public function disableAction(Request $request, \Swift_Mailer $mailer, UserPasswordEncoderInterface $encoder)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();

        if ($user->getPassword() == $encoder->encodePassword($user, $request->request->get("password"))) {
            try {
                // ponemos usuario en disable
                $user->setActive(false);
                // borramos sus dispositivos
                $user->removeDevices();

                $em->persist($user);
                $em->flush();

                if (!empty($request->request->get('note'))) {
                    // Enviar email al administrador informando del motivo
                    $message = (new \Swift_Message($user->getUsername() . ' ha desactivado su cuenta.'))
                        ->setFrom([$user->getEmail() => $user->getUsername()])
                        ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                        ->setBody("El usuario " . $user->getUsername() . " ha desactivado su cuenta por el siguiente motivo: " . $request->request->get('note'), 'text/html');

                    if (0 === $mailer->send($message)) {
                        // throw new HttpException(400, "Error al enviar el email con motivo de la desactivación");
                    }
                }

                return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al desactivar la cuenta - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "La contraseña no es correcta");
        }
    }

    /**
     * @Rest\Post("/v1/credits", name="add-credits")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Crédito añadido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al añadir los créditos"
     * )
     * 
     * @SWG\Parameter(
     *     name="credits",
     *     in="query",
     *     type="string",
     *     description="Los créditos",
     *     schema={}
     * )
     * 
     */
    public function creditsAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $credits = $request->request->get('credits');

        if ($credits > 0) {
            try {
                $user = $this->getUser();
                $user->setCredits($user->getCredits() + $credits);

                $em->persist($user);
                $em->flush();

                if (count($user->getPayments()) == 0 && $user->getMeet() == "friend") {
                    $referralUsername = $user->getReferral();
                    if (!empty($referralUsername)) {
                        $friend = $em->getRepository('App:User')->findOneBy(array('username' => $referralUsername));
                        $friend->setCredits($friend->getCredits() + 3);

                        $em->persist($friend);
                        $em->flush();

                        $title = "🐲 Ey embajador!";
                        $text = "Has conseguido 3 créditos. Gracias a tu amigo " . $user->getUsername() . " ¡Esperamos que lo disfrutes!";
                        $url = "/tabs/radar";
                        $notification = new NotificationService();
                        $notification->push($user, $friend, $title, $text, $url, "credits");
                    }
                }

                return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al añadir los créditos - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "No hay créditos que añadir");
        }
    }

    /**
     * @Rest\Put("/v1/insertcoin", name="insertcoin")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Crédito pagado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al pagar el crédito"
     * )
     * 
     * @SWG\Parameter(
     *     name="credits",
     *     in="query",
     *     type="string",
     *     description="Los créditos",
     *     schema={}
     * )
     * 
     */
    public function insertCoinAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();
        $credits = $request->request->get('credits');
        if ($user->getCredits() > 0 && $credits > 0) {
            try {
                $user = $this->getUser();
                $credits = $request->request->get('credits');
                $user->setCredits($user->getCredits() - $credits);

                $em->persist($user);
                $em->flush();

                return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al pagar el crédito - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "No tienes suficientes créditos");
        }
    }

    /**
     * @Rest\Post("/v1/premium", name="premium")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Premium añadido"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al añadir premium"
     * )
     * 
     * @SWG\Parameter(
     *     name="days",
     *     in="query",
     *     type="string",
     *     description="days",
     *     schema={}
     * )
     * 
     */
    public function setPremimAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $days = $request->request->get('days');
        if ($days > 0) {
            try {
                $user = $this->getUser();
                if ($user->getPremiumExpiration()) {
                    $datetime = $user->getPremiumExpiration();
                } else {
                    $datetime = new \DateTime;
                }

                $datetime->add(new DateInterval('P' . $days . 'D'));
                $user->setPremiumExpiration($datetime);
                $em->persist($user);
                $em->flush();

                $user->setIsPremium(true);

                if (count($user->getPayments()) == 0 && $user->getMeet() == "friend") {
                    $referralUsername = $user->getReferral();
                    if (!empty($referralUsername)) {
                        $friend = $em->getRepository('App:User')->findOneBy(array('username' => $referralUsername));
                        if ($friend->getPremiumExpiration()) {
                            $friendDatetime = $friend->getPremiumExpiration();
                        } else {
                            $friendDatetime = new \DateTime;
                        }

                        $friendDatetime->add(new DateInterval('P30D'));
                        $friend->setPremiumExpiration($friendDatetime);

                        $em->persist($friend);
                        $em->flush();

                        $title = "🐲 Ey embajador!";
                        $text = "Has conseguido 1 mes FrikiRadar ILIMITADO. Gracias a tu amigo " . $user->getUsername() . " ¡Esperamos que lo disfrutes!";
                        $url = "/tabs/radar";
                        $notification = new NotificationService();
                        $notification->push($user, $friend, $title, $text, $url, "premium");
                    }
                }

                return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
            } catch (Exception $ex) {
                throw new HttpException(400, "Error al añadir los créditos - Error: {$ex->getMessage()}");
            }
        } else {
            throw new HttpException(400, "No hay días que añadir");
        }
    }

    /**
     * @Rest\Post("/v1/payment", name="payment")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Pago añadido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al añadir el pago"
     * )
     * 
     * @SWG\Parameter(
     *     name="title",
     *     in="query",
     *     type="string",
     *     description="title",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="description",
     *     in="query",
     *     type="string",
     *     description="description",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="orderId",
     *     in="query",
     *     type="string",
     *     description="orderId",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="token",
     *     in="query",
     *     type="string",
     *     description="token",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="signature",
     *     in="query",
     *     type="string",
     *     description="signature",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="type",
     *     in="query",
     *     type="string",
     *     description="type",
     *     schema={}
     * )
     * 
     */
    public function paymentAction(Request $request, \Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();

        try {
            $em->getRepository('App:Payment')->setPayment(
                $request->request->get('title'),
                $request->request->get('description'),
                $request->request->get('order_id'),
                $request->request->get('token'),
                $request->request->get('signature'),
                $request->request->get('type'),
                $user,
                new \DateTime,
                $request->request->get('amount'),
                $request->request->get('currency')
            );

            $user->setVerified(true);
            $em->persist($user);
            $em->flush();

            // Enviar email al administrador informando del motivo
            $message = (new \Swift_Message($user->getUsername() . ' ha realizado un pago de ' . $request->request->get('amount') . " " . $request->request->get('currency')))
                ->setFrom([$user->getEmail() => $user->getUsername()])
                ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setBody("<p>Usuario: <a href='mailto:" . $user->getEmail() . "'>" . $user->getUsername() . "</a></p>
                            <p>Order Id: " . $request->request->get('order_id') . "</p>
                            <p>Title: " . $request->request->get('title') . "</p>
                            <p>Description: " . $request->request->get('description') . "</p>
                            <p>Precio: " . $request->request->get('amount') . " " . $request->request->get('currency') . "</p>
                            <p>Tienda: " . $request->request->get('type') . "</p>", 'text/html');

            if (0 === $mailer->send($message)) {
                // throw new HttpException(400, "Error al enviar el email del cobro");
            }

            return new Response($serializer->serialize($this->getUser(), "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir el pago - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/payments")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Historial de pagos obtenido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener el historial de pagos"
     * )
     * 
     * @SWG\Tag(name="Get Payments")
     */
    public function getPayments()
    {
        $serializer = $this->get('jms_serializer');
        $payments = $this->getUser()->getPayments();

        return new Response($serializer->serialize($payments, "json", SerializationContext::create()->setGroups(array('payment'))));
    }
}
