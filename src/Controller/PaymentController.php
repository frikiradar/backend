<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Service\RequestService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/api')]
class PaymentController extends AbstractController
{
    private $paymentRepository;
    private $request;
    private $serializer;
    private $userRepository;

    public function __construct(PaymentRepository $paymentRepository, UserRepository $userRepository, RequestService $request, SerializerInterface $serializer)
    {
        $this->paymentRepository = $paymentRepository;
        $this->userRepository = $userRepository;
        $this->request = $request;
        $this->serializer = $serializer;
    }

    #[Route('/v1/payments', name: 'payments', methods: ['GET'])]
    public function getPayments()
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        // recogemos solo los payments con status 'active'
        $payments = $this->paymentRepository->findBy(array('user' => $user, 'status' => 'active'), array('payment_date' => 'ASC'));

        return new JsonResponse($this->serializer->serialize($payments, "json", ['groups' => 'payment']), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/payment', name: 'last_payment', methods: ['GET'])]
    public function getLastPayment()
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        // recogemos solo el último payment con status 'active'
        $payment = $this->paymentRepository->findOneBy(array('user' => $user, 'status' => 'active'), array('payment_date' => 'DESC'));

        return new JsonResponse($this->serializer->serialize($payment, "json", ['groups' => 'payment']), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/payment', name: 'payment', methods: ['POST'])]
    public function setPayment(Request $request)
    {
        try {
            $payment = new Payment();
            $payment->setMethod($this->request->get($request, 'method'));
            $payment->setUser($this->getUser());
            $payment->setTitle($this->request->get($request, 'title', false));
            $payment->setDescription($this->request->get($request, 'description', false));
            $payment_date = $this->request->get($request, 'payment_date', false);
            if ($payment_date) {
                $payment->setPaymentDate(new \DateTime($payment_date));
            } else {
                $payment->setPaymentDate();
            }
            $expiration_date = $this->request->get($request, 'expiration_date', false);
            if ($expiration_date) {
                $payment->setExpirationDate(new \DateTime($expiration_date));
            }
            $payment->setAmount($this->request->get($request, 'amount', false));
            $payment->setCurrency($this->request->get($request, 'currency', false));
            $payment->setPaypalId($this->request->get($request, 'paypal_id', false));
            $payment->setStatus($this->request->get($request, 'status', false) ?? 'pending'); // por defecto 'pending

            $this->paymentRepository->save($payment);

            return new JsonResponse($this->serializer->serialize($this->getUser(), "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir el pago - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/payment/{id}', name: 'delete_payment', methods: ['DELETE'])]
    public function deletePayment($id)
    {
        $payment = $this->paymentRepository->findOneBy(array('id' => $id, 'user' => $this->getUser()));
        if ($payment) {
            $this->paymentRepository->remove($payment);
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } else {
            throw new HttpException(404, "No se ha encontrado el pago con ID: {$id}");
        }
    }

    #[Route('/revenuecat', name: 'revenuecat', methods: ['POST'])]
    public function revenueCatWebhook(Request $request, MailerInterface $mailer)
    {
        try {
            $event = $this->request->get($request, "event", true);
            $userId = $event["app_user_id"];
            $type = $event["type"];
            if ($type == 'TEST') {
                $user = $this->userRepository->findOneBy(array('id' => 1));
            } else {
                $user = $this->userRepository->findOneBy(array('id' => $userId));
            }

            $expiration = $event["expiration_at_ms"];
            $expiration = $expiration / 1000;
            $expiration = (new \DateTime())->setTimestamp($expiration);

            $payment_date = $event["purchased_at_ms"];
            if ($payment_date) {
                $payment_date = $payment_date / 1000;
                $payment_date = (new \DateTime())->setTimestamp($payment_date);
            }

            switch ($type) {
                case 'RENEWAL':
                case 'TEST':
                    // actualizamos la fecha de expiración
                    $user->setPremiumExpiration($expiration);
                    $this->userRepository->save($user);

                    // metemos el pago en la base de datos
                    $payment = new Payment();
                    $payment->setTitle($event["product_id"]);
                    $description = ($type == 'TEST' ? "[TEST] " : "") . "Renovación automática de suscripción a frikiradar UNLIMITED";
                    $payment->setDescription($description);
                    $payment->setMethod($event["store"]);
                    $payment->setUser($user);
                    if ($payment_date) {
                        $payment->setPaymentDate($payment_date);
                    } else {
                        $payment->setPaymentDate();
                    }

                    $payment->setExpirationDate($expiration);
                    $payment->setAmount($event['price_in_purchased_currency'] ?? 0);
                    $payment->setCurrency($event['currency'] ?? '');
                    $payment->setPurchase($event);
                    $payment->setStatus('active');

                    $this->paymentRepository->save($payment);

                    break;
                case 'INITIAL_PURCHASE':
                    // metemos el pago en la base de datos
                    $payment = new Payment();
                    $payment->setTitle($event["product_id"]);
                    $description = "Suscripción a frikiradar UNLIMITED";
                    $payment->setDescription($description);
                    $payment->setMethod($event["store"]);
                    $payment->setUser($user);
                    if ($payment_date) {
                        $payment->setPaymentDate($payment_date);
                    } else {
                        $payment->setPaymentDate();
                    }

                    $payment->setExpirationDate($expiration);
                    $payment->setAmount($event['price_in_purchased_currency'] ?? 0);
                    $payment->setCurrency($event['currency'] ?? '');
                    $payment->setPurchase($event);
                    $payment->setStatus('active');

                    $this->paymentRepository->save($payment);
                    break;
                default:
                    $data = [
                        'code' => 200,
                        'message' => "Webhook recibido correctamente",
                    ];
                    return new JsonResponse($data, 200);
            }

            // Enviar un email a hola@frikiradar con los datos del pago
            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                ->subject($description)
                ->html(
                    "Usuario: <a href='https://frikiradar.app/" . urlencode($user->getUsername()) . "' target='_blank'>" . $user->getUsername() . "</a><br/>" .
                        "Email: " . $user->getEmail() . "<br/>" .
                        "Descripción: " . $description . "<br/>" .
                        "Producto: " . $event["product_id"] . "<br/>" .
                        "Fecha de pago: " . ($payment_date ? $payment_date->format('d/m/Y H:i:s') : 'No disponible') . "<br/>" .
                        "Fecha de expiración: " . $expiration->format('d/m/Y H:i:s') . "<br/>" .
                        "Método de pago: " . $event["store"] . "<br/>" .
                        "Precio: " . $event['price_in_purchased_currency'] . " " . $event['currency'] . "<br/>"
                );

            $mailer->send($email);

            // es un webhook
            $data = [
                'code' => 200,
                'message' => "Webhook recibido correctamente",
            ];
            return new JsonResponse($data, 200);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir los días premium - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/paypal', name: 'paypal', methods: ['POST'])]
    public function paypalWebhook(Request $request, MailerInterface $mailer)
    {
        try {
            $event = json_decode($request->getContent(), true);
            $type = $event["event_type"];

            switch ($type) {
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    $paypalId = $event["resource"]["id"];
                    $payment = $this->paymentRepository->findOneBy(['paypal_id' => $paypalId, 'status' => 'pending']);
                    if ($payment) {
                        $user = $payment->getUser();
                        $description = "Suscripción a frikiradar UNLIMITED";
                    } else {
                        // comprobamos si es una renovación
                        $payment = $this->paymentRepository->findOneBy(['paypal_id' => $paypalId, 'status' => 'active']);
                        if ($payment) {
                            $user = $payment->getUser();
                            $description = "Renovación automática de suscripción a frikiradar UNLIMITED";
                            $payment = new Payment();
                        } else {
                            throw new HttpException(400, "No se ha encontrado el pago con ID de PayPal: {$paypalId}");
                        }
                    }
                    $expiration = $event["resource"]["billing_info"]["next_billing_time"];
                    $payment_date = $event["resource"]["billing_info"]["last_payment"]["time"];
                    $price = $event['resource']['billing_info']['last_payment']['amount']['value'] ?? 0;
                    $currency = $event['resource']['billing_info']['last_payment']['amount']['currency_code'] ?? '';
                    $payment->setTitle($event["resource"]["plan_id"]);
                    $payment->setDescription($description);

                    if ($payment_date) {
                        $payment->setPaymentDate((new \DateTime())->setTimestamp(strtotime($payment_date)));
                    } else {
                        $payment->setPaymentDate();
                    }

                    $payment->setUser($user);
                    $payment->setExpirationDate((new \DateTime())->setTimestamp(strtotime($expiration)));
                    $payment->setAmount($price);
                    $payment->setCurrency($currency);
                    $payment->setPurchase($event);
                    $payment->setStatus('active');

                    $this->paymentRepository->save($payment);

                    break;
                default:
                    $data = [
                        'code' => 200,
                        'message' => "Webhook recibido correctamente",
                    ];
                    return new JsonResponse($data, 200);
            }


            // Enviar un email a hola@frikiradar con los datos del pago
            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                ->subject($description)
                ->html(
                    "Usuario: <a href='https://frikiradar.app/" . urlencode($user->getUsername()) . "' target='_blank'>" . $user->getUsername() . "</a><br/>" .
                        "Email: " . $user->getEmail() . "<br/>" .
                        "Descripción: " . $description . "<br/>" .
                        "ID de suscripción: " . $event["resource"]["id"] . "<br/>" .
                        "Plan: " . $event["resource"]["plan_id"] . "<br/>" .
                        "Fecha de pago: " . ($payment_date ?? 'No disponible') . "<br/>" .
                        "Fecha de expiración: " . ($expiration ?? 'No disponible') . "<br/>" .
                        "Método de pago: PAYPAL<br/>" .
                        "Precio: " . $price . " " . $currency . "<br/>"
                );

            $mailer->send($email);

            $data = [
                'code' => 200,
                'message' => "Webhook recibido correctamente",
            ];
            return new JsonResponse($data, 200);
        } catch (Exception $ex) {
            // Enviamos email con el error
            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                ->subject("Error al procesar el webhook de PayPal")
                ->html(
                    "Error al procesar el webhook de PayPal - Error: {$ex->getMessage()}"
                );

            $mailer->send($email);

            throw new HttpException(400, "Error al procesar el webhook de PayPal - Error: {$ex->getMessage()}");
        }
    }
}
