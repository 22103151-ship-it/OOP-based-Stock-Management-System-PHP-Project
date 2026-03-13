<?php

namespace App\Services;

use App\Models\Supplier;

class SupplierManagementService
{
    private Supplier $supplierModel;

    public function __construct(\mysqli $db)
    {
        $this->supplierModel = new Supplier($db);
    }

    public function addSupplier(array $data): bool
    {
        return $this->supplierModel->create(
            0,
            (string)($data['name'] ?? ''),
            (string)($data['email'] ?? ''),
            (string)($data['phone'] ?? ''),
            (string)($data['address'] ?? '')
        ) > 0;
    }

    public function editSupplier(int $id, array $data): bool
    {
        return $this->supplierModel->updateProfile($id, [
            'name' => (string)($data['name'] ?? ''),
            'email' => (string)($data['email'] ?? ''),
            'phone' => (string)($data['phone'] ?? ''),
            'address' => (string)($data['address'] ?? ''),
        ]);
    }

    public function deleteSupplier(int $id): bool
    {
        return $this->supplierModel->deleteById($id);
    }

    /** @return array<int,array<string,mixed>> */
    public function getSuppliers(): array
    {
        return $this->supplierModel->findAll('id DESC');
    }

    public function findSupplier(int $id): ?array
    {
        return $this->supplierModel->findById($id);
    }
}
