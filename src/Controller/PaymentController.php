<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\RequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api')]
class PaymentController extends AbstractController
{
    private $paymentRepository;
    private $request;

    public function __construct(PaymentRepository $paymentRepository, RequestService $request)
    {
        $this->paymentRepository = $paymentRepository;
        $this->request = $request;
    }

    #[Route('/v1/payments', name: 'payments', methods: ['GET'])]
    public function getPayments()
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $payments = $user->getPayments();

        return $this->json($payments);
    }

    #[Route('/v1/payment', name: 'payment', methods: ['POST'])]
    public function setPayment(Request $request)
    {
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
        $payment->setProduct($this->request->get($request, 'product'));
        $payment->setPurchase($this->request->get($request, 'purchase'));
        $payment->setStatus('active');

        $this->paymentRepository->save($payment);

        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/PaymentController.php',
        ]);
    }
}
