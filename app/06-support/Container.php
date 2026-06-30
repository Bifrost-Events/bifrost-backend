<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\AdminAccessRepositoryPort;
use App\Contracts\Repositories\AdminDomainRepositoryPort;
use App\Contracts\Repositories\AdminOrganizationRepositoryPort;
use App\Contracts\Repositories\AdminSeasonRepositoryPort;
use App\Contracts\Repositories\AdminTenantRepositoryPort;
use App\Contracts\Repositories\AdminUserRepositoryPort;
use App\Contracts\Repositories\ParticipantClaimRepositoryPort;
use App\Contracts\Repositories\ParticipantRepositoryPort;
use App\Contracts\Repositories\PublicReadRepositoryPort;
use App\Contracts\Repositories\SignupRepositoryPort;
use App\Contracts\Repositories\TenantRepositoryPort;
use App\Contracts\Repositories\UserProfileRepositoryPort;
use App\Repositories\Pdo\PdoAdminAccessRepository;
use App\Repositories\Pdo\PdoAdminDomainRepository;
use App\Repositories\Pdo\PdoAdminOrganizationRepository;
use App\Repositories\Pdo\PdoAdminSeasonRepository;
use App\Repositories\Pdo\PdoAdminTenantRepository;
use App\Repositories\Pdo\PdoAdminUserRepository;
use App\Repositories\Pdo\PdoLegacyOrganizerRepository;
use App\Repositories\Pdo\PdoParticipantClaimRepository;
use App\Repositories\Pdo\PdoParticipantRepository;
use App\Repositories\Pdo\PdoPublicReadRepository;
use App\Repositories\Pdo\PdoSignupRepository;
use App\Repositories\Pdo\PdoTenantRepository;
use App\Repositories\Pdo\PdoUserProfileRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Service\AdminService;
use App\Service\OnboardingService;
use App\Service\ParticipantService;
use App\Service\PublicReadService;
use App\Service\UseCases\EnsureParticipantForUserUseCase;

final class Container
{
    private ?\PDO $pdo = null;
    private ?TenantRepositoryPort $tenantRepo = null;
    private ?AdminTenantRepositoryPort $adminTenantRepo = null;
    private ?AdminDomainRepositoryPort $adminDomainRepo = null;
    private ?AdminUserRepositoryPort $adminUserRepo = null;
    private ?AdminAccessRepositoryPort $adminAccessRepo = null;
    private ?AdminOrganizationRepositoryPort $adminOrganizationRepo = null;
    private ?AdminSeasonRepositoryPort $adminSeasonRepo = null;
    private ?PublicReadRepositoryPort $publicReadRepo = null;
    private ?ParticipantRepositoryPort $participantRepo = null;
    private ?SignupRepositoryPort $signupRepo = null;
    private ?UserProfileRepositoryPort $userProfileRepo = null;
    private ?ParticipantClaimRepositoryPort $participantClaimRepo = null;
    private ?PdoLegacyOrganizerRepository $legacyOrganizerRepo = null;
    private ?EnsureParticipantForUserUseCase $ensureParticipantUseCase = null;
    private ?OnboardingService $onboardingService = null;
    private ?AdminService $adminService = null;
    private ?PublicReadService $publicReadService = null;
    private ?ParticipantService $participantService = null;

    public function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $dsn = $_ENV['DB_DSN'] ?? 'mysql:host=localhost;dbname=bifrost;charset=utf8mb4';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $this->pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        }

        return $this->pdo;
    }

    public function getTenantRepo(): TenantRepositoryPort
    {
        if ($this->tenantRepo === null) {
            $this->tenantRepo = new PdoTenantRepository($this->getPdo());
        }

        return $this->tenantRepo;
    }

    public function getAdminTenantRepo(): AdminTenantRepositoryPort
    {
        if ($this->adminTenantRepo === null) {
            $this->adminTenantRepo = new PdoAdminTenantRepository($this->getPdo());
        }

        return $this->adminTenantRepo;
    }

    public function getAdminDomainRepo(): AdminDomainRepositoryPort
    {
        if ($this->adminDomainRepo === null) {
            $this->adminDomainRepo = new PdoAdminDomainRepository($this->getPdo());
        }

        return $this->adminDomainRepo;
    }

    public function getAdminUserRepo(): AdminUserRepositoryPort
    {
        if ($this->adminUserRepo === null) {
            $this->adminUserRepo = new PdoAdminUserRepository($this->getPdo());
        }

        return $this->adminUserRepo;
    }

    public function getAdminAccessRepo(): AdminAccessRepositoryPort
    {
        if ($this->adminAccessRepo === null) {
            $this->adminAccessRepo = new PdoAdminAccessRepository($this->getPdo());
        }

        return $this->adminAccessRepo;
    }

    public function getAdminOrganizationRepo(): AdminOrganizationRepositoryPort
    {
        if ($this->adminOrganizationRepo === null) {
            $this->adminOrganizationRepo = new PdoAdminOrganizationRepository($this->getPdo());
        }

        return $this->adminOrganizationRepo;
    }

    public function getAdminSeasonRepo(): AdminSeasonRepositoryPort
    {
        if ($this->adminSeasonRepo === null) {
            $this->adminSeasonRepo = new PdoAdminSeasonRepository($this->getPdo());
        }

        return $this->adminSeasonRepo;
    }

    public function getPublicReadRepo(): PublicReadRepositoryPort
    {
        if ($this->publicReadRepo === null) {
            $this->publicReadRepo = new PdoPublicReadRepository($this->getPdo());
        }

        return $this->publicReadRepo;
    }

    public function getPublicReadService(): PublicReadService
    {
        if ($this->publicReadService === null) {
            $this->publicReadService = new PublicReadService(
                $this->getTenantRepo(),
                $this->getPublicReadRepo(),
            );
        }

        return $this->publicReadService;
    }

    public function getParticipantRepo(): ParticipantRepositoryPort
    {
        if ($this->participantRepo === null) {
            $this->participantRepo = new PdoParticipantRepository($this->getPdo());
        }

        return $this->participantRepo;
    }

    public function getSignupRepo(): SignupRepositoryPort
    {
        if ($this->signupRepo === null) {
            $this->signupRepo = new PdoSignupRepository($this->getPdo());
        }

        return $this->signupRepo;
    }

    public function getUserProfileRepo(): UserProfileRepositoryPort
    {
        if ($this->userProfileRepo === null) {
            $this->userProfileRepo = new PdoUserProfileRepository($this->getPdo());
        }

        return $this->userProfileRepo;
    }

    public function getParticipantClaimRepo(): ParticipantClaimRepositoryPort
    {
        if ($this->participantClaimRepo === null) {
            $this->participantClaimRepo = new PdoParticipantClaimRepository($this->getPdo());
        }

        return $this->participantClaimRepo;
    }

    public function getLegacyOrganizerRepo(): PdoLegacyOrganizerRepository
    {
        if ($this->legacyOrganizerRepo === null) {
            $this->legacyOrganizerRepo = new PdoLegacyOrganizerRepository($this->getPdo());
        }

        return $this->legacyOrganizerRepo;
    }

    public function getEnsureParticipantUseCase(): EnsureParticipantForUserUseCase
    {
        if ($this->ensureParticipantUseCase === null) {
            $this->ensureParticipantUseCase = new EnsureParticipantForUserUseCase($this->getParticipantRepo());
        }

        return $this->ensureParticipantUseCase;
    }

    public function getOnboardingService(): OnboardingService
    {
        if ($this->onboardingService === null) {
            $this->onboardingService = new OnboardingService(
                $this->getUserProfileRepo(),
                new PdoUserRepository($this->getPdo()),
                $this->getParticipantRepo(),
                $this->getParticipantClaimRepo(),
                $this->getAdminOrganizationRepo(),
                $this->getTenantRepo(),
                $this->getLegacyOrganizerRepo(),
                $this->getEnsureParticipantUseCase(),
            );
        }

        return $this->onboardingService;
    }

    public function getParticipantService(): ParticipantService
    {
        if ($this->participantService === null) {
            $this->participantService = new ParticipantService(
                $this->getTenantRepo(),
                $this->getPublicReadRepo(),
                $this->getParticipantRepo(),
                $this->getSignupRepo(),
            );
        }

        return $this->participantService;
    }

    public function getAdminService(): AdminService
    {
        if ($this->adminService === null) {
            $this->adminService = new AdminService(
                $this->getAdminTenantRepo(),
                $this->getAdminDomainRepo(),
                $this->getAdminUserRepo(),
                $this->getAdminAccessRepo(),
                $this->getAdminOrganizationRepo(),
                $this->getAdminSeasonRepo(),
            );
        }

        return $this->adminService;
    }
}
