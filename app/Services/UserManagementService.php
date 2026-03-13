<?php

namespace App\Services;

use App\Models\User;

class UserManagementService
{
    private User $userModel;

    public function __construct(\mysqli $db)
    {
        $this->userModel = new User($db);
    }

    public function addUser(string $name, string $email, string $password, string $role): bool
    {
        return $this->userModel->create($name, $email, $password, $role) > 0;
    }

    public function editUser(int $id, string $name, string $email, string $password, string $role): bool
    {
        return $this->userModel->updateDetails($id, $name, $email, $password, $role);
    }

    public function deleteUser(int $id): bool
    {
        return $this->userModel->deleteById($id);
    }

    /** @return array<int,array<string,mixed>> */
    public function getUsers(): array
    {
        return $this->userModel->findAll('id DESC');
    }

    /** @return array<string,mixed>|null */
    public function getUser(int $id): ?array
    {
        return $this->userModel->findById($id);
    }
}
