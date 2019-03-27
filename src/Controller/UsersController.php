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
use App\Entity\Device;
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
    public function registerAction(Request $request, UserPasswordEncoderInterface $encoder, \Swift_Mailer $mailer)
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
            $user->setVerificationCode();
            $user->setRoles(['ROLE_USER']);

            $em->persist($user);
            $em->flush();


            $body = $this->render(
                "emails/registration.html.twig",
                [
                    'username' => $user->getUsername(),
                    'code' => $user->getVerificationCode()
                ]
            );

            $message = (new \Swift_Message('Te has registrado correctamente en FrikiRadar'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView("emails/page.html.twig", ['body' => $body]),
                    'text/html'
                );

            if (0 === $mailer->send($message)) {
                throw new \RuntimeException('Unable to send email');
            }
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
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId()));
        $user->setAvatar($user->getAvatar());
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
        $user = $em->getRepository('App:User')->findeOneUser($id, $this->getUser());
        $user['age'] = (int)$user['age'];
        $user['distance'] = (int)$user['distance'];

        $obUser = new User();
        $obUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
        $user['tags'] = $obUser->getTags();
        $user['avatar'] = $obUser->getAvatar() ?: null;
        $user['match'] = $em->getRepository('App:User')->getMatchIndex($this->getUser(), $obUser);

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
            foreach ($users as $key => $u) {
                $user = $em->getRepository('App:User')->findOneBy(array('id' => $u['id']));
                $users[$key]['age'] = (int)$u['age'];
                $users[$key]['distance'] = round($u['distance'], 0, PHP_ROUND_HALF_UP);
                $users[$key]['match'] = $em->getRepository('App:User')->getMatchIndex($this->getUser(), $user);
                $users[$key]['avatar'] = $user->getAvatar() ?: null;
            }

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
            foreach ($users as $key => $u) {
                $user = $em->getRepository('App:User')->findOneBy(array('id' => $u['id']));
                $users[$key]['age'] = (int)$u['age'];
                $users[$key]['distance'] = (int)$u['distance'];
                $users[$key]['match'] = $em->getRepository('App:User')->getMatchIndex($this->getUser(), $user);
                $users[$key]['avatar'] = $user->getAvatar() ?: null;
            }

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
     * @Rest\Get("/v1/devices")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Dispositivos obtenidos correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener los dispositivos"
     * )
     * 
     */
    public function getDevices()
    {
        $serializer = $this->get('jms_serializer');

        try {
            $response = $this->getUser()->getDevices();
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al obtener los dispositivos - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }

    /**
     * @Rest\Put("/v1/device", name="device")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Dispositivo añadido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al añadir el dispositivo"
     * )
     * 
     * @SWG\Parameter(
     *     name="token",
     *     in="body",
     *     type="string",
     *     description="Token",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="id",
     *     in="body",
     *     type="string",
     *     description="Device Id",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="name",
     *     in="body",
     *     type="string",
     *     description="Device Name",
     *     schema={}
     * )
     *
     * @Rest\View(serializerGroups={"device"})
     */
    public function setDeviceAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $response = $em->getRepository('App:Device')->set(
                $this->getUser(),
                $request->request->get("token"),
                $request->request->get("id"),
                $request->request->get("name")
            );
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al registrar el dispositivo - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }
}
