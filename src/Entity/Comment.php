<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\CommentRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=CommentRepository::class)
 */
class Comment
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"story"})
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     * @Groups({"story"})
     */
    private $text;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"story"})
     */
    private $time_creation;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, cascade={"persist"})
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Groups({"story"})
     */
    private $user;

    /**
     * @ORM\ManyToMany(targetEntity=User::class)
     * @Groups({"story"})
     */
    private $likes;

    /**
     * @ORM\ManyToOne(targetEntity=Story::class, inversedBy="comments")
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id", onDelete="CASCADE")
     */
    private $story;

    /**
     * @ORM\Column(type="json", nullable=true)
     * @Groups({"story"})
     */
    private $mentions = [];

    public function __construct()
    {
        $this->likes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getTimeCreation(): ?\DateTimeInterface
    {
        return $this->time_creation;
    }

    public function setTimeCreation(): self
    {
        $this->time_creation = new \DateTime;

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

    /**
     * @return Collection|User[]
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(User $like): self
    {
        if (!$this->likes->contains($like)) {
            $this->likes[] = $like;
        }

        return $this;
    }

    public function removeLike(User $like): self
    {
        $this->likes->removeElement($like);

        return $this;
    }

    public function getStory(): ?Story
    {
        return $this->story;
    }

    public function setStory(?Story $story): self
    {
        $this->story = $story;

        return $this;
    }

    public function getMentions(): ?array
    {
        return $this->mentions;
    }

    public function setMentions(?array $mentions): self
    {
        $this->mentions = $mentions;

        return $this;
    }
}
