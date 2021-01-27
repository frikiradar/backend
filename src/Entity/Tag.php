<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TagRepository")
 */
class Tag
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"default", "message", "like"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"default"})
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="tags")
     * @ORM\JoinColumn(nullable=false)
     * @MaxDepth(1)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Category", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"default"})
     */
    private $category;

    /*public function __construct($name, $user, $category)
    {
        $this->name = $name;
        $this->year = $user;
        $this->category = $category;
    }*/

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }
}
