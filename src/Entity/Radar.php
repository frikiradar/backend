<?php

namespace App\Entity;

use App\Repository\RadarRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RadarRepository::class)]
class Radar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private $fromUser;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'radars', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private $toUser;

    #[ORM\Column(type: 'datetime')]
    private $date;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $time_read;

    public function __construct()
    {
        $this->setDate(new \DateTime);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromUser(): ?User
    {
        return $this->fromUser;
    }

    public function setFromUser(?User $fromUser): self
    {
        $this->fromUser = $fromUser;

        return $this;
    }

    public function getToUser(): ?User
    {
        return $this->toUser;
    }

    public function setToUser(?User $toUser): self
    {
        $this->toUser = $toUser;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getTimeRead(): ?\DateTimeInterface
    {
        return $this->time_read;
    }

    public function setTimeRead(?\DateTimeInterface $time_read): self
    {
        $this->time_read = $time_read;

        return $this;
    }
}
