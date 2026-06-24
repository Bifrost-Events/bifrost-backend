<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\AdminAccessRepositoryPort;
use App\Contracts\Repositories\AdminDomainRepositoryPort;
use App\Contracts\Repositories\AdminOrganizationRepositoryPort;
use App\Contracts\Repositories\AdminSeasonRepositoryPort;
use App\Contracts\Repositories\AdminTenantRepositoryPort;
use App\Contracts\Repositories\AdminUserRepositoryPort;
use App\Contracts\Repositories\TenantRepositoryPort;
use App\Repositories\Pdo\PdoAdminAccessRepository;
use App\Repositories\Pdo\PdoAdminDomainRepository;
use App\Repositories\Pdo\PdoAdminOrganizationRepository;
use App\Repositories\Pdo\PdoAdminSeasonRepository;
use App\Repositories\Pdo\PdoAdminTenantRepository;
use App\Repositories\Pdo\PdoAdminUserRepository;
use App\Repositories\Pdo\PdoTenantRepository;
use App\Service\AdminService;

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
    private ?AdminService $adminService = null;

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
