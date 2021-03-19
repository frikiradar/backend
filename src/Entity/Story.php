<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\StoryRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass=StoryRepository::class)
 */
class Story
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"story"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"story"})
     */
    private $image;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"story"})
     */
    private $text;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"story"})
     */
    private $time_creation;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="stories")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"story"})
     */
    private $user;

    /**
     * @ORM\OneToMany(targetEntity=Comment::class, mappedBy="story")
     * @Groups({"story"})
     */
    private $comments;

    /**
     * @ORM\OneToMany(targetEntity=LikeStory::class, mappedBy="story", orphanRemoval=true)
     * @Groups({"story"})
     */
    private $likeStories;

    /**
     * @ORM\OneToMany(targetEntity=ViewStory::class, mappedBy="story", orphanRemoval=true)
     * @Groups({"story"})
     */
    private $viewStories;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->likeStories = new ArrayCollection();
        $this->viewStories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
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
     * @return Collection|Comment[]
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->setStory($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getStory() === $this) {
                $comment->setStory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|LikeStory[]
     */
    public function getLikeStories(): Collection
    {
        return $this->likeStories;
    }

    public function addLikeStory(LikeStory $likeStory): self
    {
        if (!$this->likeStories->contains($likeStory)) {
            $this->likeStories[] = $likeStory;
            $likeStory->setStory($this);
        }

        return $this;
    }

    public function removeLikeStory(LikeStory $likeStory): self
    {
        if ($this->likeStories->removeElement($likeStory)) {
            // set the owning side to null (unless already changed)
            if ($likeStory->getStory() === $this) {
                $likeStory->setStory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ViewStory[]
     */
    public function getViewStories(): Collection
    {
        return $this->viewStories;
    }

    public function addViewStory(ViewStory $viewStory): self
    {
        if (!$this->viewStories->contains($viewStory)) {
            $this->viewStories[] = $viewStory;
            $viewStory->setStory($this);
        }

        return $this;
    }

    public function removeViewStory(ViewStory $viewStory): self
    {
        if ($this->viewStories->removeElement($viewStory)) {
            // set the owning side to null (unless already changed)
            if ($viewStory->getStory() === $this) {
                $viewStory->setStory(null);
            }
        }

        return $this;
    }
}
