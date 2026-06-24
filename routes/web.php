<?php

declare(strict_types=1);

use App\Controller\AdminAccessController;
use App\Controller\AdminDomainController;
use App\Controller\AdminOrganizationController;
use App\Controller\AdminSeasonController;
use App\Controller\AdminTenantController;
use App\Controller\AdminUserController;
use App\Controller\AuthController;
use App\Controller\HealthController;
use App\Controller\TenantController;
use App\Support\Container;
use App\Support\Router;

return function (array $app): Router {
    $router = new Router();
    $container = new Container();
    $admin = $container->getAdminService();

    $tenants = new TenantController();
    $auth = new AuthController();
    $adminTenants = new AdminTenantController($admin);
    $adminDomains = new AdminDomainController($admin);
    $adminUsers = new AdminUserController($admin);
    $adminAccess = new AdminAccessController($admin);
    $adminOrganizations = new AdminOrganizationController($admin);
    $adminSeasons = new AdminSeasonController($admin);

    $router->get('/api/health', fn () => (new HealthController())());
    $router->post('/api/auth/login', fn () => $auth->login());
    $router->post('/api/auth/logout', fn () => $auth->logout());
    $router->get('/api/auth/me', fn () => $auth->me());
    $router->get('/api/tenants', fn () => $tenants->index());
    $router->get('/api/tenants/{id}', fn (int $id) => $tenants->show($id));
    $router->get('/api/tenant/resolve', fn () => $tenants->resolve());

    $router->get('/api/admin/tenants', fn () => $adminTenants->index());
    $router->get('/api/admin/tenants/{id}', fn (int $id) => $adminTenants->show($id));
    $router->post('/api/admin/tenants', fn () => $adminTenants->store());
    $router->put('/api/admin/tenants/{id}', fn (int $id) => $adminTenants->update($id));
    $router->delete('/api/admin/tenants/{id}', fn (int $id) => $adminTenants->destroy($id));

    $router->get('/api/admin/tenants/{tenantId}/domains', fn (int $tenantId) => $adminDomains->index($tenantId));
    $router->post('/api/admin/tenants/{tenantId}/domains', fn (int $tenantId) => $adminDomains->store($tenantId));
    $router->put('/api/admin/domains/{id}', fn (int $id) => $adminDomains->update($id));
    $router->delete('/api/admin/domains/{id}', fn (int $id) => $adminDomains->destroy($id));

    $router->get('/api/admin/users', fn () => $adminUsers->index());
    $router->get('/api/admin/users/{id}', fn (int $id) => $adminUsers->show($id));
    $router->post('/api/admin/users', fn () => $adminUsers->store());
    $router->put('/api/admin/users/{id}', fn (int $id) => $adminUsers->update($id));
    $router->delete('/api/admin/users/{id}', fn (int $id) => $adminUsers->destroy($id));

    $router->get('/api/admin/roles', fn () => $adminAccess->roles());
    $router->get('/api/admin/role-assignments', fn () => $adminAccess->roleAssignmentsOverview());
    $router->get('/api/admin/role-assignments/{role}', fn (string $role) => $adminAccess->roleAssignments($role));
    $router->get('/api/admin/users/{id}/access', fn (int $id) => $adminAccess->userAccess($id));
    $router->post('/api/admin/users/{id}/system-roles', fn (int $id) => $adminAccess->grantSystemRole($id));
    $router->delete('/api/admin/users/{id}/system-roles/{role}', fn (int $id, string $role) => $adminAccess->revokeSystemRole($id, $role));
    $router->post('/api/admin/users/{id}/tenant-access', fn (int $id) => $adminAccess->grantTenantAccess($id));
    $router->delete('/api/admin/users/{id}/tenant-access/{accessId}', fn (int $id, int $accessId) => $adminAccess->revokeTenantAccess($id, $accessId));

    $router->get('/api/admin/organizations', fn () => $adminOrganizations->index());
    $router->get('/api/admin/organizations/{id}', fn (int $id) => $adminOrganizations->show($id));
    $router->post('/api/admin/organizations', fn () => $adminOrganizations->store());
    $router->put('/api/admin/organizations/{id}', fn (int $id) => $adminOrganizations->update($id));
    $router->delete('/api/admin/organizations/{id}', fn (int $id) => $adminOrganizations->destroy($id));
    $router->post('/api/admin/organizations/{id}/members', fn (int $id) => $adminOrganizations->addMember($id));
    $router->delete('/api/admin/organizations/{id}/members/{memberId}', fn (int $id, int $memberId) => $adminOrganizations->removeMember($id, $memberId));

    $router->get('/api/admin/seasons', fn () => $adminSeasons->index());
    $router->get('/api/admin/seasons/{id}', fn (int $id) => $adminSeasons->show($id));
    $router->post('/api/admin/seasons', fn () => $adminSeasons->store());
    $router->put('/api/admin/seasons/{id}', fn (int $id) => $adminSeasons->update($id));
    $router->put('/api/admin/seasons/{id}/cup-standings', fn (int $id) => $adminSeasons->updateCupStandings($id));
    $router->post('/api/admin/seasons/{id}/rounds', fn (int $id) => $adminSeasons->storeRound($id));

    return $router;
};
