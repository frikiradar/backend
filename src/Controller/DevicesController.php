<?php
/**
 * DevicesController.php
 *
 * Devices Controller
 *
 * @category   Controller
 * @package    FrikiRadar
 * @author     Alberto Merino
 * @copyright  2019 frikiradar.com
 */

namespace App\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use App\Entity\Device;


/**
 * Class DevicesController
 *
 * @Route("/api")
 */
class DevicesController extends FOSRestController
{
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
                $request->request->get("id"),
                $request->request->get("name"),
                $request->request->get("token") ?: ""
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


    /**
     * @Rest\Get("/v1/unknown-device", name="unknown device")
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
    public function unknownDeviceAction(\Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $this->getUser();
            $user->setVerificationCode();
            $em->persist($user);
            $em->flush();

            $message = (new \Swift_Message('Nuevo dispositivo detectado. Verificación de identidad.'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo($this->getUser()->getEmail())
                ->setBody(
                    $this->renderView(
                        "emails/unknown-device.html.twig",
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
     * @Rest\Put("/v1/unknown-device", name="verify device")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Dispositivo verificado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al verificar el dispositivo"
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
    public function verifyDeviceAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $verificationCode = $request->request->get("verification_code");
        $user = $em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId(), 'verificationCode' => $verificationCode));
        if (!is_null($user)) {
            $user->setVerificationCode(null);
            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($user, "json"));
        } else {
            throw new HttpException(400, "Error al verificar tu dispositivo");
        }
    }

    /**
     * @Rest\Delete("/v1/device/{id}")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Dispositivo eliminado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al eliminar el dispositivo"
     * )
     * 
     */
    public function deleteAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $this->getUser();
            $device = $em->getRepository('App:Device')->findOneBy(array('user' => $user, 'id' => $id));
            $user->removeDevice($device);
            $em->persist($user);
            $em->flush();

            $response = $user;
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al eliminar el dispositivo - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }

    /**
     * @Rest\Get("/v1/switch-device/{id}")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Dispositivo activado/desactivado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al activar/desactivar el dispositivo"
     * )
     * 
     */
    public function switchAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            /**
             * @var Device
             */
            $device = $em->getRepository('App:Device')->findOneBy(array('user' => $this->getUser(), 'id' => $id));
            $device->setActive(!$device->getActive());
            $em->persist($device);
            $em->flush();

            $response = $this->getUser();
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al eliminar el dispositivo - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }
}
