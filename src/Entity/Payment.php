<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PaymentRepository")
 */
class Payment
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"payment"})
     */
    private $order_id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"payment"})
     */
    private $token;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"payment"})
     */
    private $signature;

    /**
     * @ORM\Column(type="string", length=70)
     * @Groups({"payment"})
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=70)
     * @Groups({"payment"})
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"payment"})
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="payments")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"payment"})
     */
    private $payment_date;

    /**
     * @ORM\Column(type="float")
     * @Groups({"payment"})
     */
    private $amount;

    /**
     * @ORM\Column(type="string", length=3)
     * @Groups({"payment"})
     */
    private $currency;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $json;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?string
    {
        return $this->order_id;
    }

    public function setOrderId(string $order_id): self
    {
        $this->order_id = $order_id;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPaymentDate(): ?\DateTimeInterface
    {
        return $this->payment_date;
    }

    public function setPaymentDate(\DateTimeInterface $payment_date): self
    {
        $this->payment_date = $payment_date;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getJson(): ?string
    {
        return $this->json;
    }

    public function setJson(?string $json): self
    {
        $this->json = $json;

        return $this;
    }
}
