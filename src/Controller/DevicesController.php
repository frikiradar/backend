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
use JMS\Serializer\SerializationContext;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
            return new Response($serializer->serialize($response, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los dispositivos - Error: {$ex->getMessage()}");
        }
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
            $em->getRepository('App:Device')->set(
                $this->getUser(),
                $request->request->get("id"),
                $request->request->get("name"),
                $request->request->get("token") ?: ""
            );

            return new Response($serializer->serialize($this->getUser(), "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al registrar el dispositivo - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Rest\Put("/v1/unknown-device", name="unknown device")
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
    public function unknownDeviceAction(Request $request, \Swift_Mailer $mailer)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $device = $request->request->get("device");

            $message = (new \Swift_Message('Aviso de inicio de sesión desde un dispositivo desconocido'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo($this->getUser()->getEmail())
                ->setBody(
                    $this->renderView(
                        "emails/unknown-device.html.twig",
                        [
                            'username' => $this->getUser()->getUsername(),
                            'device' => $device['device_name']
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

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el dispositivo - Error: {$ex->getMessage()}");
        }
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

            return new Response($serializer->serialize($this->getUser(), "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al acivar/desactivar el dispositivo - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/turnoff-device/{uuid}")
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
    public function turnOffAction(string $uuid)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            /**
             * @var Device
             */
            $device = $em->getRepository('App:Device')->findOneBy(array('user' => $this->getUser(), 'deviceId' => $uuid));

            if (!empty($device)) {
                $device->setActive(false);
                $em->persist($device);
                $em->flush();
            }

            return new Response($serializer->serialize($device, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desactivar el dispositivo - Error: {$ex->getMessage()}");
        }
    }
}
