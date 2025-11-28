<?php

namespace App\Entity;

use App\Repository\AgentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentRepository::class)]
class Agent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    /**
     * @var Collection<int, AgentAvailability>
     */
    #[ORM\OneToMany(targetEntity: AgentAvailability::class, mappedBy: 'agent')]
    private Collection $agentAvailabilities;

    public function __construct(
        string $firstName,
        string $lastName,
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->agentAvailabilities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @return Collection<int, AgentAvailability>
     */
    public function getAgentAvailabilities(): Collection
    {
        return $this->agentAvailabilities;
    }

    public function addAgentAvailability(AgentAvailability $agentAvailability): static
    {
        if (!$this->agentAvailabilities->contains($agentAvailability)) {
            $this->agentAvailabilities->add($agentAvailability);
            $agentAvailability->setAgent($this);
        }

        return $this;
    }

    public function removeAgentAvailability(AgentAvailability $agentAvailability): static
    {
        if ($this->agentAvailabilities->removeElement($agentAvailability)) {
            // set the owning side to null (unless already changed)
            if ($agentAvailability->getAgent() === $this) {
                $agentAvailability->setAgent(null);
            }
        }

        return $this;
    }
}
