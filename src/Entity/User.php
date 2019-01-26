<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\Regex(
     *     pattern="/^[a-zA-Z0-9._-]+$/",
     *     match=true,
     *     message="Solo se permiten caracteres alfanuméricos, puntos y/o guiones; ni letras con tildes, ni caracteres especiales",
     *     payload = {"severity" = "error"}
     * )
     */
    private $username;

    /**
     * @var array
     * @ORM\Column(type="json")
     * @Serializer\ReadOnly()
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     * @Groups({"private"})
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Serializer\ReadOnly()
     */
    private $register_date;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Type("DateTime<'Y-m-d'>")
     */
    private $birthday;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     */
    private $gender;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     */
    private $orientation;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     */
    private $relationship;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     */
    private $pronoun;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Serializer\ReadOnly()
     */
    private $register_ip;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Serializer\ReadOnly()
     */
    private $last_ip;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Serializer\ReadOnly()
     */
    private $last_login;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $latitude;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $longitude;

    /**
     * @var array
     * @ORM\Column(type="json", nullable=true)
     */
    private $interesting = [];

    public function getId() : ? int
    {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername() : string
    {
        return (string)$this->username;
    }

    public function setUsername(string $username) : self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles() : array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles) : self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword() : string
    {
        return (string)$this->password;
    }

    public function setPassword(string $password) : self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail() : ? string
    {
        return $this->email;
    }

    public function setEmail(string $email) : self
    {
        $this->email = $email;

        return $this;
    }

    public function getRegisterDate() : ? \DateTimeInterface
    {
        return $this->register_date;
    }

    public function setRegisterDate() : self
    {
        $this->register_date = new \DateTime;

        return $this;
    }

    public function getBirthday() : ? \DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(\DateTimeInterface $birthday) : self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getGender() : ? string
    {
        return $this->gender;
    }

    public function setGender(string $gender) : self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getRegisterIp() : ? string
    {
        return $this->register_ip;
    }

    public function setRegisterIp() : self
    {
        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $this->register_ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $this->register_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED"])) {
            $this->register_ip = $_SERVER["HTTP_X_FORWARDED"];
        } elseif (isset($_SERVER["HTTP_FORWARDED_FOR"])) {
            $this->register_ip = $_SERVER["HTTP_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_FORWARDED"])) {
            $this->register_ip = $_SERVER["HTTP_FORWARDED"];
        } else {
            $this->register_ip = $_SERVER["REMOTE_ADDR"];
        }

        return $this;
    }

    public function getLastIp() : ? string
    {
        return $this->last_ip;
    }

    public function setLastIp(string $last_ip) : self
    {
        $this->last_ip = $last_ip;

        return $this;
    }

    public function getLastLogin() : ? \DateTimeInterface
    {
        return $this->last_login;
    }

    public function setLastLogin(\DateTimeInterface $last_login) : self
    {
        $this->last_login = $last_login;

        return $this;
    }

    public function getLocation() : ? string
    {
        return $this->location;
    }

    public function setLocation(string $location) : self
    {
        $this->location = $location;

        return $this;
    }

    public function getLatitude() : ? string
    {
        return $this->latitude;
    }

    public function setLatitude(? string $latitude) : self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude() : ? string
    {
        return $this->longitude;
    }

    public function setLongitude(? string $longitude) : self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getDescription() : ? string
    {
        return $this->description;
    }

    public function setDescription(? string $description) : self
    {
        $this->description = $description;

        return $this;
    }

    public function getRelationship() : ? string
    {
        return $this->relationship;
    }

    public function setRelationship(? string $relationship) : self
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function getOrientation() : ? string
    {
        return $this->orientation;
    }

    public function setOrientation(? string $orientation) : self
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getPronoun() : ? string
    {
        return $this->pronoun;
    }

    public function setPronoun(? string $pronoun) : self
    {
        $this->pronoun = $pronoun;

        return $this;
    }

    public function getStatus() : ? string
    {
        return $this->status;
    }

    public function setStatus(? string $status) : self
    {
        $this->status = $status;

        return $this;
    }

    public function getInteresting() : ? array
    {
        return $this->interesting;
    }

    public function setInteresting(? array $interesting) : self
    {
        $this->interesting = $interesting;

        return $this;
    }
}
