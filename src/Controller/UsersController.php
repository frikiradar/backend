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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\File\File;

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

            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setPassword($encoder->encodePassword($user, $password));
            $user->setRegisterDate();
            $user->setRegisterIp();

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
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('App:User')->findeOneUser($this->getUser()->getId());
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

            $coords = new Point(0, 0);
            $coords
                ->setLatitude($request->request->get('latitude'))
                ->setLongitude($request->request->get('longitude'));

            $user->setCoordinates($coords);

            $em->persist($user);
            $em->flush();

            $response = $user;
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
     * @Rest\Post("/v1/avatar", name="avatar")
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
    public function putAvatarAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();
        $avatar = $request->files->get('avatar');

        $uploader = new FileUploader("../assets/images/avatar");
        $uploader->upload($avatar);

        // return new Response($serializer->serialize($response, "json"));
    }

    /**
     * @Rest\Get("/v1/radar", name="radar")
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
     *     name="ratio",
     *     in="body",
     *     type="integer",
     *     description="Ratio",
     *     schema={}
     * )
     */
    public function getRadarUsers(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $code = 200;
            $error = false;

            $user = $this->getUser();
            $users = $em->getRepository('App:User')->getUsersByDistance($user, 10000);
        } catch (Exception $ex) {
            $code = 500;
            $error = true;
            $message = "Error al registrar coordenadas - Error: {$ex->getMessage()}";
        }

        $response = [
            'code' => $code,
            'error' => $error,
            'data' => $code == 200 ? $users : $message,
        ];

        return new Response($serializer->serialize($response, "json"));
    }
}
