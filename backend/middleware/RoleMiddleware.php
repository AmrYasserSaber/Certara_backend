<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

final class RoleMiddleware implements Middleware
{
    /** @var list<string> */
    private array $allowedRoles;

    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(Request $request): Request
    {
        if ($this->allowedRoles === []) {
            return $request;
        }

        $user = $request->user();
        if (!is_object($user) || !property_exists($user, 'role')) {
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        $role = (string) ($user->role ?? '');
        if (!in_array($role, $this->allowedRoles, true)) {
            Response::error('Forbidden.', 403, 'forbidden');
        }

        return $request;
    }
}
