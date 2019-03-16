<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChatRepository")
 */
class Chat
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="chats")
     */
    private $fromuser;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="chats")
     */
    private $touser;

    /**
     * @ORM\Column(type="text")
     */
    private $text;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timeCreation;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $timeRead;

    public function getId(): ? int
    {
        return $this->id;
    }

    public function getFromuser(): ? User
    {
        return $this->fromuser;
    }

    public function setFromuser(? User $fromuser): self
    {
        $this->fromuser = $fromuser;

        return $this;
    }

    public function getTouser(): ? User
    {
        return $this->touser;
    }

    public function setTouser(? User $touser): self
    {
        $this->touser = $touser;

        return $this;
    }

    public function getText(): ? string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getTimeCreation(): ? \DateTimeInterface
    {
        return $this->timeCreation;
    }

    public function setTimeCreation(\DateTimeInterface $timeCreation): self
    {
        $this->timeCreation = new \DateTime;

        return $this;
    }

    public function getTimeRead(): ? \DateTimeInterface
    {
        return $this->timeRead;
    }

    public function setTimeRead(\DateTimeInterface $timeRead): self
    {
        $this->timeRead = $timeRead;

        return $this;
    }
}
