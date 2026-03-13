<?php

namespace App\Services;

use App\Models\PurchaseOrder;

class SupplierDashboardService
{
    private PurchaseOrder $purchaseOrderModel;
    private NotificationService $notificationService;

    public function __construct(\mysqli $db)
    {
        $this->purchaseOrderModel = new PurchaseOrder($db);
        $this->notificationService = new NotificationService($db);
    }

    /** @return array{total_orders:int,pending_orders:int,delivered_orders:int,returned_orders:int} */
    public function getOrderCounts(?int $supplierId): array
    {
        return [
            'total_orders' => $this->purchaseOrderModel->countAll($supplierId),
            'pending_orders' => $this->purchaseOrderModel->countByStatus('pending', $supplierId),
            'delivered_orders' => $this->purchaseOrderModel->countByStatus('delivered', $supplierId),
            'returned_orders' => $this->purchaseOrderModel->countByStatus('returned', $supplierId),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function getNotifications(): array
    {
        return $this->notificationService->getActiveDots('supplier');
    }
}
