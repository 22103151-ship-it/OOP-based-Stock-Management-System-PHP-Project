<?php

use App\Models\Supplier;
use App\Models\User;

class SupplierContextResolver
{
    private Supplier $supplierModel;
    private User $userModel;

    public function __construct(mysqli $conn)
    {
        $this->supplierModel = new Supplier($conn);
        $this->userModel = new User($conn);
    }

    public function resolveSupplierId(): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['supplier_id'])) {
            return (int)$_SESSION['supplier_id'];
        }

        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($user_id <= 0) {
            return 0;
        }

        $supplierById = $this->supplierModel->findById($user_id);
        if ($supplierById) {
            $_SESSION['supplier_id'] = (int)$supplierById['id'];
            return (int)$supplierById['id'];
        }

        $supplierByUser = $this->supplierModel->findByUserId($user_id);
        if ($supplierByUser) {
            $_SESSION['supplier_id'] = (int)$supplierByUser['id'];
            return (int)$supplierByUser['id'];
        }

        $user = $this->userModel->findById($user_id);
        if (!$user) {
            return 0;
        }

        $email = (string)$user['email'];
        $name = (string)$user['name'];

        $supplierByIdentity = $this->supplierModel->findByEmailOrName($email, $name);
        if ($supplierByIdentity) {
            $_SESSION['supplier_id'] = (int)$supplierByIdentity['id'];
            return (int)$supplierByIdentity['id'];
        }

        return 0;
    }
}

function getResolvedSupplierId(mysqli $conn): int
{
    $resolver = new SupplierContextResolver($conn);
    return $resolver->resolveSupplierId();
}
