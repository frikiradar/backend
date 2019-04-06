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
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use App\Service\FileUploader;
use Geocoder\Query\ReverseQuery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Entity\LikeUser;
use App\Entity\BlockUser;

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
    { }

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
     *     name="password",
     *     in="query",
     *     type="string",
     *     description="The password"
     * )
     *
     * @SWG\Tag(name="User")
     */
    public function registerAction(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $user = [];
        $message = "";

        try {
            $code = 200;
            $error = false;

            $email = $request->request->get('email');
            $username = $request->request->get('username');
            $password = $request->request->get('password');
            $birthday = \DateTime::createFromFormat('Y-m-d', $request->request->get('birthday'));

            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setPassword($encoder->encodePassword($user, $password));
            $user->setBirthday($birthday);
            $user->setRegisterDate();
            $user->setRegisterIp();
            $user->setActive(false);
            $user->setVerificationCode();
            $user->setRoles(['ROLE_USER']);

            $em->persist($user);
            $em->flush();
        } catch (Exception $ex) {
            $code = 500;
            $error = true;
            $message = "An error has occurred trying to register the user - Error: {$ex->getMessage()}";
        }

        $response = [
            'code' => $code,
            'error' => $error,
            'data' => $code == 200 ? $user : $message,
        ];

        return new Response($serializer->serialize($response, "json"));
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
        $serializer = $this->get('jms_serializer');

        $user = $this->getUser();
        $user->setAvatar($user->getAvatar());
        $user->setVerificationCode(null);
        return new Response($serializer->serialize($user, "json"));
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
        $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
        $user = $em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);

        return new Response($serializer->serialize($user, "json"));
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
        $message = "";

        try {
            $code = 200;
            $error = false;

            if ($newUser->getId() == $this->getUser()->getId()) {
                $em = $this->getDoctrine()->getManager();

                /**
                 * @var User
                 */
                $user = $this->getUser();
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

                $em->persist($user);
                $em->flush();
                $user->setAvatar($user->getAvatar());

                $response = $user;
            } else {
                $code = 500;
                $error = true;
                $message = "An error has occurred trying to edit the current task - Error: The task id does not exist";
                /*$response = [
                    'code' => $code,
                    'error' => $error,
                    'data' => $message,
                ];*/
            }
        } catch (Exception $ex) {
            $code = 500;
            $error = true;
            $message = "Error al actualizar la información del usuario - Error: {$ex->getMessage()}";

            $response = [
                'code' => $code,
                'error' => $error,
                'data' => $message,
            ];
        }

        return new Response($serializer->serialize($response, "json"));
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

        $user = [];
        $message = "";

        try {
            $code = 200;
            $error = false;

            $user = $this->getUser();

            $httpClient = new \Http\Adapter\Guzzle6\Client();
            $coords = new Point(0, 0);

            if ($request->request->get('latitude') && $request->request->get('longitude')) {
                $coords
                    ->setLatitude($request->request->get('latitude'))
                    ->setLongitude($request->request->get('longitude'));
            } else {
                // Calculamos las coordenadas y ciudad por la ip
                $ip = $user->getIP();

                $provider = new \Geocoder\Provider\GeoPlugin\GeoPlugin($httpClient);
                $geocoder = new \Geocoder\StatefulGeocoder($provider, 'es');
                $ipResult = $geocoder->geocode($ip);
                $coords
                    ->setLatitude($ipResult->first()->getCoordinates()->getLatitude())
                    ->setLongitude($ipResult->first()->getCoordinates()->getLongitude());
            }

            $user->setCoordinates($coords);
            $em->persist($user);
            $em->flush();

            try {
                $google = new \Geocoder\Provider\GoogleMaps\GoogleMaps($httpClient, null, 'AIzaSyDgwnkBNx1TrvQO0GZeMmT6pNVvG3Froh0');
                $geocoder = new \Geocoder\StatefulGeocoder($google, 'es');
                $result = $geocoder->reverseQuery(ReverseQuery::fromCoordinates($user->getCoordinates()->getLatitude(), $user->getCoordinates()->getLongitude()));
                $user->setLocation($result->first()->getLocality());
                $em->persist($user);
                $em->flush();

                $response = $user;
            } catch (Exception $ex) {
                //echo "No se ha podido obtener la localidad". $ex;
                $response = $user;
            }
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al registrar coordenadas - Error: {$ex->getMessage()}"
            ];
        }

        return new Response($serializer->serialize($response, "json"));
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
        $serializer = $this->get('jms_serializer');
        $avatar = $request->files->get('avatar');

        $id = $this->getUser()->getId();
        $filename = date('YmdHis');
        $uploader = new FileUploader("../public/images/avatar/" . $id . "/", $filename);
        $image = $uploader->upload($avatar);

        if (isset($image)) {
            $files = glob("../public/images/avatar/" . $id . "/*.jpg");
            usort($files, function ($a, $b) {
                return basename($b) <=> basename($a);
            });
            foreach ($files as $key => $file) {
                if ($key > 3) {
                    unlink($file);
                }
            }

            $server = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $response = str_replace("../public", $server, $image);
        } else {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al subir la imagen"
            ];
        }
        return new Response($serializer->serialize($response, "json"));
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
     */
    public function getRadarUsers(int $ratio)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $users = $em->getRepository('App:User')->getUsersByDistance($this->getUser(), $ratio);

            usort($users, function ($a, $b) {
                return $b['match'] <=> $a['match'];
            });

            /* PONDERACIÓN SOBRE 100
            $index = 1 / (max(array_column($users, 'match')) / 100);
            foreach ($users as $key => $rUsers) {
                $users[$key]["match"] = $rUsers["match"] * $index;
            }*/

            $response = $users;
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al obtener los usuarios - Error: {$ex->getMessage()}",
            ];
        }



        return new Response($serializer->serialize($response, "json"));
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
     */
    public function searchAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $users = $em->getRepository('App:User')->searchUsers($request->request->get("query"), $this->getUser());

            switch ($request->request->get("order")) {
                case 'match':
                    usort($users, function ($a, $b) {
                        return $b['match'] <=> $a['match'];
                    });
                    break;
                default:
                    usort($users, function ($a, $b) {
                        return $a['distance'] <=> $b['distance'];
                    });
            }

            $response = $users;
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al obtener los resultados de búsqueda - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
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

            $message = (new \Swift_Message('Aquí tienes tu código de activación de FrikiRadar'))
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
                'data' => "Error al enviar el email de activación - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
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

            return new Response($serializer->serialize($user, "json"));
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

        try {
            if (preg_match('#^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $request->request->get('username'))) {
                $user = $em->getRepository('App:User')->findOneBy(array('email' => $request->request->get('username')));
            } else {
                $user = $em->getRepository('App:User')->findOneBy(array('username' => $request->request->get('username')));
            }

            $user->setVerificationCode();
            $em->persist($user);
            $em->flush();

            $message = (new \Swift_Message('He olvidado mi contraseña de FrikiRadar'))
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
                'data' => "Error al enviar el email de recuperación - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
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

            return new Response($serializer->serialize($user, "json"));
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

            return new Response($serializer->serialize($user, "json"));
        } else {
            throw new HttpException(400, "La contraseña actual no es válida");
        }


        if (!is_null($user)) {
            $user->setPassword($encoder->encodePassword($user, $request->request->get('password')));
            $user->setVerificationCode(null);
            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($user, "json"));
        } else {
            throw new HttpException(400, "Error al recuperar la cuenta");
        }
    }

    /**
     * @Rest\Put("/v1/like", name="like")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Like guardado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al guardar el like"
     * )
     * 
     * @SWG\Parameter(
     *     name="user",
     *     in="body",
     *     type="string",
     *     description="To user like",
     *     schema={}
     * )
     *
     */
    public function putLikeAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $request->request->get('user')));

            $newLike = new LikeUser();
            $newLike->setDate(new \DateTime);
            $newLike->setFromUser($this->getUser());
            $newLike->setToUser($toUser);
            $em->persist($newLike);
            $em->flush();

            $title = $newLike->getFromUser()->getUsername();
            $text = "Ha mostrado interés en ti dando 'Me gusta' a tu perfil";
            $url = "/profile/" . $newLike->getFromUser()->getId();
            $em->getRepository('App:Notification')->push($newLike->getFromuser(), $newLike->getTouser(), $title, $text, $url, "like");
            $user = $em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);

            return new Response($serializer->serialize($user, "json"));
        } catch (Exception $e) {
            throw new HttpException(400, "Error al dar like " . $e);
        }
    }

    /**
     * @Rest\Delete("/v1/like/{id}", name="unlike")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Like borrado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al borrar el like"
     * )
     *
     */
    public function removeLikeAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
            $like = $em->getRepository('App:LikeUser')->findOneBy(array('to_user' => $toUser, 'from_user' => $this->getUser()));
            $em->remove($like);
            $em->flush();

            $user = $em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);

            return new Response($serializer->serialize($user, "json"));
        } catch (Exception $e) {
            throw new HttpException(400, "Error al quitar el like " . $e);
        }
    }

    /**
     * @Rest\Get("/v1/likes")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Likes obtenidos correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener los likes"
     * )
     * 
     * @SWG\Tag(name="Get Likes")
     */
    public function getLikesAction()
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('App:LikeUser')->getToLikes($this->getUser());

        foreach ($users as $key => $u) {
            $user = $em->getRepository('App:User')->findOneBy(array('id' => $u['id']));
            $users[$key]['age'] = (int)$u['age'];
            $users[$key]['distance'] = round($u['distance'], 0, PHP_ROUND_HALF_UP);
            $users[$key]['location'] = (!$user->getHideLocation() && !empty($user->getLocation())) ? $user->getLocation() : null;
            $users[$key]['match'] = $em->getRepository('App:User')->getMatchIndex($this->getUser(), $user);
            $users[$key]['avatar'] = $user->getAvatar() ?: null;
        }

        return new Response($serializer->serialize($users, "json"));
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
                $message = (new \Swift_Message('He olvidado mi contraseña de FrikiRadar'))
                    ->setFrom([$this->getUser()->getEmail() => $this->getUser()->getUsername()])
                    ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setBody("El usuario " . $this->getUser()->getUsername() . " ha bloqueado al usuario < href='mailto:" . $blockUser->getEmail() . "'>" . $blockUser->getUsername() . "</a> por el siguiente motivo: " . $newBlock->getNote());

                if (0 === $mailer->send($message)) {
                    // throw new \RuntimeException('Unable to send email');
                }
            }

            $response = [
                'code' => 200,
                'error' => false,
                'data' => "Usuario bloqueado correctamente",
            ];

            return new Response($serializer->serialize($response, "json"));
        } catch (Exception $e) {
            throw new HttpException(400, "Error al bloquear usuario " . $e);
        }
    }

    /**
     * @Rest\Delete("/v1/block/{id}", name="unlike")
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

            $block = $em->getRepository('App:LikeUser')->findOneBy(array('block_user' => $blockUser, 'from_user' => $this->getUser()));
            $em->remove($block);
            $em->flush();

            $response = [
                'code' => 200,
                'error' => false,
                'data' => "Usuario desbloqueado correctamente",
            ];

            return new Response($serializer->serialize($response, "json"));
        } catch (Exception $e) {
            throw new HttpException(400, "Error al desbloquear el usuario " . $e);
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

        return new Response($serializer->serialize($users, "json"));
    }
}
