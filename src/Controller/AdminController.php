<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $admin = $this->getUser();

        if (!$admin instanceof Admin) {
            return $this->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $productCount = $this->entityManager->getRepository(Product::class)->count([]);
        $orderCount = $this->entityManager->getRepository(Order::class)->count([]);
        $customerCount = $this->entityManager->getRepository(Customer::class)->count([]);

        // Calculate total revenue
        $orders = $this->entityManager->getRepository(Order::class)->findAll();
        $totalRevenue = 0;
        foreach ($orders as $order) {
            $totalRevenue += (float) $order->getTotal();
        }

        return $this->json([
            'success' => true,
            'stats' => [
                'products' => $productCount,
                'orders' => $orderCount,
                'customers' => $customerCount,
                'revenue' => $totalRevenue
            ]
        ]);
    }

    #[Route('/orders', name: 'api_admin_orders', methods: ['GET'])]
    public function orders(): JsonResponse
    {
        $orders = $this->entityManager->getRepository(Order::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $this->json(
            json_decode(
                $this->serializer->serialize($orders, 'json', ['groups' => 'order:read']),
                true
            )
        );
    }

    #[Route('/customers', name: 'api_admin_customers', methods: ['GET'])]
    public function customers(): JsonResponse
    {
        $customers = $this->entityManager->getRepository(Customer::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $this->json(
            json_decode(
                $this->serializer->serialize($customers, 'json', ['groups' => 'customer:read']),
                true
            )
        );
    }
}