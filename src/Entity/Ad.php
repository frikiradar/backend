<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\AdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: AdRepository::class)]
class Ad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $image_url = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $end_date = null;

    #[ORM\ManyToOne(inversedBy: 'ads')]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'ad', targetEntity: ClickAd::class, orphanRemoval: true)]
    private Collection $clickAds;

    #[ORM\OneToMany(mappedBy: 'ad', targetEntity: ViewAd::class, orphanRemoval: true)]
    private Collection $viewAds;

    public function __construct()
    {
        $this->clickAds = new ArrayCollection();
        $this->viewAds = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(string $image_url): static
    {
        $this->image_url = $image_url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTimeInterface $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEndDate(?\DateTimeInterface $end_date): static
    {
        $this->end_date = $end_date;

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

    /**
     * @return Collection<int, ClickAd>
     */
    public function getClickAds(): Collection
    {
        return $this->clickAds;
    }

    public function addClickAd(ClickAd $clickAd): static
    {
        if (!$this->clickAds->contains($clickAd)) {
            $this->clickAds->add($clickAd);
            $clickAd->setAd($this);
        }

        return $this;
    }

    public function removeClickAd(ClickAd $clickAd): static
    {
        if ($this->clickAds->removeElement($clickAd)) {
            // set the owning side to null (unless already changed)
            if ($clickAd->getAd() === $this) {
                $clickAd->setAd(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ViewAd>
     */
    public function getViewAds(): Collection
    {
        return $this->viewAds;
    }

    public function addViewAd(ViewAd $viewAd): static
    {
        if (!$this->viewAds->contains($viewAd)) {
            $this->viewAds->add($viewAd);
            $viewAd->setAd($this);
        }

        return $this;
    }

    public function removeViewAd(ViewAd $viewAd): static
    {
        if ($this->viewAds->removeElement($viewAd)) {
            // set the owning side to null (unless already changed)
            if ($viewAd->getAd() === $this) {
                $viewAd->setAd(null);
            }
        }

        return $this;
    }
}
