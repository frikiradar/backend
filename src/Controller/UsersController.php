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
     * @SWG\Tag(name="Get User")
     */
    public function getAction()
    {
        $serializer = $this->get('jms_serializer');
        $response = $this->getUser();
        return new Response($serializer->serialize($response, "json"));
    }

    /**
     * @Rest\Put("/v1/user")
     * @ParamConverter("user", converter="fos_rest.request_body")
     * @param User $user
     */
    public function putAction(User $user)
    {
        $serializer = $this->get('jms_serializer');
        $message = "";

        try {
            $code = 200;
            $error = false;

            if ($user->getId() == $this->getUser()->getId()) {
                $em = $this->getDoctrine()->getManager();

                $auxUser = $em->getRepository('App:User')->find($user->getId());
                $user->setPassword($auxUser->getPassword());

                $em->merge($user);
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
     * @SWG\Parameter(
     *     name="latitude",
     *     in="body",
     *     type="string",
     *     description="Latitude",
     *     schema={}
     * )
     *
     * @SWG\Parameter(
     *     name="longitude",
     *     in="body",
     *     type="string",
     *     description="Longitude",
     *     schema={}
     * )
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
            $user->setLatitude($request->request->get('latitude'));
            $user->setLongitude($request->request->get('longitude'));

            $em->persist($user);
            $em->flush();

        } catch (Exception $ex) {
            $code = 500;
            $error = true;
            $message = "Error al registrar coordenadas - Error: {$ex->getMessage()}";
        }

        $response = [
            'code' => $code,
            'error' => $error,
            'data' => $code == 200 ? $user : $message,
        ];

        return new Response($serializer->serialize($response, "json"));
    }
}