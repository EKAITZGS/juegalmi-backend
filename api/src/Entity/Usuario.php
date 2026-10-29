<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    // Hash de la contraseña, nunca la contraseña en claro
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(length: 150)]
    private ?string $apellidos = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaAlta = null;

    // Un usuario desactivado desde el panel no puede entrar, pero conserva sus pedidos y tickets
    #[ORM\Column(options: ['default' => true])]
    private bool $activo = true;

    // Correo verificado. Las cuentas creadas por el administrador y las anteriores a la verificación ya lo están
    #[ORM\Column(options: ['default' => true])]
    private bool $verificado = true;

    // Solo se guarda el resumen (SHA-256) del enlace enviado por correo
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tokenVerificacion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $verificacionCaduca = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $verificacionEnviada = null;

    /** @var Collection<int, Compra> */
    #[ORM\OneToMany(targetEntity: Compra::class, mappedBy: 'usuario')]
    private Collection $compras;

    /** @var Collection<int, Alquiler> */
    #[ORM\OneToMany(targetEntity: Alquiler::class, mappedBy: 'usuario')]
    private Collection $alquileres;

    /** @var Collection<int, Ticket> */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'usuario')]
    private Collection $tickets;

    /** @var Collection<int, Ubicacion> */
    #[ORM\OneToMany(targetEntity: Ubicacion::class, mappedBy: 'usuario')]
    private Collection $ubicaciones;

    public function __construct(?string $email = null, ?string $nombre = null, ?string $apellidos = null, ?string $telefono = null, array $roles = [])
    {
        $this->email = $email;
        $this->nombre = $nombre;
        $this->apellidos = $apellidos;
        $this->telefono = $telefono;
        $this->roles = $roles;
        $this->fechaAlta = new \DateTime();
        $this->compras = new ArrayCollection();
        $this->alquileres = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->ubicaciones = new ArrayCollection();
    }

    // Lo que identifica al usuario en el token (el campo username del JWT)
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // Todo usuario es, como mínimo, ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Obligatorio por la interfaz en Symfony 7; desaparece en Symfony 8
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getApellidos(): ?string
    {
        return $this->apellidos;
    }

    public function setApellidos(string $apellidos): static
    {
        $this->apellidos = $apellidos;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isVerificado(): bool
    {
        return $this->verificado;
    }

    public function setVerificado(bool $verificado): static
    {
        $this->verificado = $verificado;

        return $this;
    }

    public function getTokenVerificacion(): ?string
    {
        return $this->tokenVerificacion;
    }

    public function setTokenVerificacion(?string $token): static
    {
        $this->tokenVerificacion = $token;

        return $this;
    }

    public function getVerificacionCaduca(): ?\DateTimeInterface
    {
        return $this->verificacionCaduca;
    }

    public function setVerificacionCaduca(?\DateTimeInterface $caduca): static
    {
        $this->verificacionCaduca = $caduca;

        return $this;
    }

    public function getVerificacionEnviada(): ?\DateTimeInterface
    {
        return $this->verificacionEnviada;
    }

    public function setVerificacionEnviada(?\DateTimeInterface $enviada): static
    {
        $this->verificacionEnviada = $enviada;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    public function getFechaAlta(): ?\DateTime
    {
        return $this->fechaAlta;
    }

    public function setFechaAlta(\DateTime $fechaAlta): static
    {
        $this->fechaAlta = $fechaAlta;

        return $this;
    }

    /**
     * @return Collection<int, Compra>
     */
    public function getCompras(): Collection
    {
        return $this->compras;
    }

    public function addCompra(Compra $compra): static
    {
        if (!$this->compras->contains($compra)) {
            $this->compras->add($compra);
            $compra->setUsuario($this);
        }

        return $this;
    }

    public function removeCompra(Compra $compra): static
    {
        if ($this->compras->removeElement($compra)) {
            // set the owning side to null (unless already changed)
            if ($compra->getUsuario() === $this) {
                $compra->setUsuario(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Alquiler>
     */
    public function getAlquileres(): Collection
    {
        return $this->alquileres;
    }

    public function addAlquiler(Alquiler $alquiler): static
    {
        if (!$this->alquileres->contains($alquiler)) {
            $this->alquileres->add($alquiler);
            $alquiler->setUsuario($this);
        }

        return $this;
    }

    public function removeAlquiler(Alquiler $alquiler): static
    {
        if ($this->alquileres->removeElement($alquiler)) {
            // set the owning side to null (unless already changed)
            if ($alquiler->getUsuario() === $this) {
                $alquiler->setUsuario(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setUsuario($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getUsuario() === $this) {
                $ticket->setUsuario(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ubicacion>
     */
    public function getUbicaciones(): Collection
    {
        return $this->ubicaciones;
    }

    public function addUbicacion(Ubicacion $ubicacion): static
    {
        if (!$this->ubicaciones->contains($ubicacion)) {
            $this->ubicaciones->add($ubicacion);
            $ubicacion->setUsuario($this);
        }

        return $this;
    }

    public function removeUbicacion(Ubicacion $ubicacion): static
    {
        if ($this->ubicaciones->removeElement($ubicacion)) {
            // set the owning side to null (unless already changed)
            if ($ubicacion->getUsuario() === $this) {
                $ubicacion->setUsuario(null);
            }
        }

        return $this;
    }
}
