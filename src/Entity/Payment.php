<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups('payment')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups('payment')]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Groups('payment')]
    private ?string $description = null;

    #[ORM\Column(length: 70)]
    #[Groups('payment')]
    private ?string $method = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups('payment')]
    private ?\DateTimeInterface $payment_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups('payment')]
    private ?\DateTimeInterface $expiration_date = null;

    #[ORM\Column]
    #[Groups('payment')]
    private ?float $amount = null;

    #[ORM\Column(length: 5)]
    #[Groups('payment')]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    private ?array $product = null;

    #[ORM\Column(nullable: true)]
    private ?array $purchase = null;

    #[ORM\Column(length: 70)]
    #[Groups('payment')]
    private ?string $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPaymentDate(): ?\DateTimeInterface
    {
        return $this->payment_date;
    }

    public function setPaymentDate(\DateTimeInterface $payment_date = null): static
    {
        if ($payment_date === null) {
            $payment_date = new \DateTime();
        }

        $this->payment_date = $payment_date;

        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expiration_date;
    }

    public function setExpirationDate(\DateTimeInterface $expiration_date): static
    {
        $this->expiration_date = $expiration_date;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getProduct(): ?array
    {
        return $this->product;
    }

    public function setProduct(?array $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getPurchase(): ?array
    {
        return $this->purchase;
    }

    public function setPurchase(?array $purchase): static
    {
        $this->purchase = $purchase;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
