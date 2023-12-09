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
        $payments = $user->getPayments();

        return new JsonResponse($this->serializer->serialize($payments, "json", ['groups' => 'payment']), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/payment', name: 'payment', methods: ['POST'])]
    public function setPayment(Request $request)
    {
        try {
            $payment = new Payment();
            $payment->setTitle($this->request->get($request, 'title'));
            $payment->setDescription($this->request->get($request, 'description'));
            $payment->setMethod($this->request->get($request, 'method'));
            $payment->setUser($this->getUser());
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
            $payment->setAmount($this->request->get($request, 'amount'));
            $payment->setCurrency($this->request->get($request, 'currency'));
            $payment->setProduct(json_decode($this->request->get($request, 'product'), true));
            $payment->setPurchase(json_decode($this->request->get($request, 'purchase'), true));
            $payment->setStatus('active');

            $this->paymentRepository->save($payment);

            return new JsonResponse($this->serializer->serialize($this->getUser(), "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir el pago - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/revenuecat', name: 'revenuecat', methods: ['POST'])]
    public function revenueCatWebhook(Request $request)
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
                    $payment->setDescription("Renovación automática de suscripción a frikiradar UNLIMITED");
                    $payment->setMethod($event["store"]);
                    $payment->setUser($user);
                    if ($payment_date) {
                        $payment->setPaymentDate($payment_date);
                    } else {
                        $payment->setPaymentDate();
                    }

                    $payment->setExpirationDate($expiration);
                    $payment->setAmount($event['price'] ?? 0);
                    $payment->setCurrency($event['currency'] ?? '');
                    $payment->setPurchase($event);
                    $payment->setStatus('active');

                    $this->paymentRepository->save($payment);

                    break;
                case 'INITIAL_PURCHASE':
                    // metemos el pago en la base de datos
                    $payment = new Payment();
                    $payment->setTitle($event["product_id"]);
                    $payment->setDescription("Suscripción a frikiradar UNLIMITED");
                    $payment->setMethod($event["store"]);
                    $payment->setUser($user);
                    if ($payment_date) {
                        $payment->setPaymentDate($payment_date);
                    } else {
                        $payment->setPaymentDate();
                    }

                    $payment->setExpirationDate($expiration);
                    $payment->setAmount($event['price'] ?? 0);
                    $payment->setCurrency($event['currency'] ?? '');
                    $payment->setPurchase($event);
                    $payment->setStatus('active');

                    $this->paymentRepository->save($payment);
                    break;
                case 'CANCELLATION':
                    // No debería ser necesario porque se cancela directamente si la fecha de expiración es menor que la actual
                    /*$user->setPremiumExpiration(null);
                    $this->userRepository->save($user);*/
                    break;
            }

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
}
