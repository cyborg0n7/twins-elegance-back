<?php

namespace App\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/customers')]
class CustomerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * List all customers (Admin only)
     * GET /api/customers
     */
    #[Route('', name: 'api_customers_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // This endpoint should be protected by security.yaml to admin only
        $customers = $this->entityManager->getRepository(Customer::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $customersData = [];
        foreach ($customers as $customer) {
            $customersData[] = [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
                'city' => $customer->getCity(),
                'zipCode' => $customer->getZipCode(),
                'ordersCount' => $customer->getOrders()->count(),
                'createdAt' => $customer->getCreatedAt()?->format('Y-m-d H:i:s')
            ];
        }

        return $this->json([
            'success' => true,
            'customers' => $customersData
        ]);
    }

    /**
     * Get a specific customer (Admin only)
     * GET /api/customers/{id}
     */
    #[Route('/{id}', name: 'api_customers_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $customer = $this->entityManager->getRepository(Customer::class)->find($id);

        if (!$customer) {
            return $this->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        // Get customer orders
        $orders = [];
        foreach ($customer->getOrders() as $order) {
            $items = [];
            foreach ($order->getOrderItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'productId' => $item->getProduct()?->getId(),
                    'productName' => $item->getProduct()?->getName(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice()
                ];
            }

            $orders[] = [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'subtotal' => $order->getSubtotal(),
                'deliveryFee' => $order->getDeliveryFee(),
                'total' => $order->getTotal(),
                'items' => $items,
                'createdAt' => $order->getCreatedAt()?->format('Y-m-d H:i:s')
            ];
        }

        return $this->json([
            'success' => true,
            'customer' => [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
                'city' => $customer->getCity(),
                'zipCode' => $customer->getZipCode(),
                'createdAt' => $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
                'orders' => $orders
            ]
        ]);
    }

    /**
     * Update a customer (Admin only)
     * PUT /api/customers/{id}
     */
    #[Route('/{id}', name: 'api_customers_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $customer = $this->entityManager->getRepository(Customer::class)->find($id);

        if (!$customer) {
            return $this->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update fields if provided
        if (isset($data['email'])) {
            // Check if email is already taken by another customer
            $existingCustomer = $this->entityManager->getRepository(Customer::class)
                ->findOneBy(['email' => $data['email']]);
            
            if ($existingCustomer && $existingCustomer->getId() !== $customer->getId()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé'
                ], Response::HTTP_CONFLICT);
            }
            
            $customer->setEmail($data['email']);
        }
        
        if (isset($data['firstName']) || isset($data['first_name'])) {
            $customer->setFirstName($data['firstName'] ?? $data['first_name']);
        }
        if (isset($data['lastName']) || isset($data['last_name'])) {
            $customer->setLastName($data['lastName'] ?? $data['last_name']);
        }
        if (isset($data['phone'])) {
            $customer->setPhone($data['phone']);
        }
        if (isset($data['address'])) {
            $customer->setAddress($data['address']);
        }
        if (isset($data['city'])) {
            $customer->setCity($data['city']);
        }
        if (isset($data['zipCode']) || isset($data['zip_code'])) {
            $customer->setZipCode($data['zipCode'] ?? $data['zip_code']);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Client mis à jour avec succès',
            'customer' => [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
                'city' => $customer->getCity(),
                'zipCode' => $customer->getZipCode()
            ]
        ]);
    }

    /**
     * Delete a customer (Admin only)
     * DELETE /api/customers/{id}
     */
    #[Route('/{id}', name: 'api_customers_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $customer = $this->entityManager->getRepository(Customer::class)->find($id);

        if (!$customer) {
            return $this->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if customer has orders
        if ($customer->getOrders()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer un client avec des commandes. Veuillez d\'abord supprimer ou réassigner les commandes.'
            ], Response::HTTP_CONFLICT);
        }

        $this->entityManager->remove($customer);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Client supprimé avec succès'
        ]);
    }

    /**
     * Search customers (Admin only)
     * GET /api/customers/search?q=query
     */
    #[Route('/search', name: 'api_customers_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (empty($query)) {
            return $this->json([
                'success' => false,
                'message' => 'Paramètre de recherche manquant'
            ], Response::HTTP_BAD_REQUEST);
        }

        $qb = $this->entityManager->getRepository(Customer::class)
            ->createQueryBuilder('c');

        $qb->where('c.email LIKE :query')
            ->orWhere('c.firstName LIKE :query')
            ->orWhere('c.lastName LIKE :query')
            ->orWhere('c.phone LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.createdAt', 'DESC');

        $customers = $qb->getQuery()->getResult();

        $customersData = [];
        foreach ($customers as $customer) {
            $customersData[] = [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
                'city' => $customer->getCity(),
                'zipCode' => $customer->getZipCode(),
                'ordersCount' => $customer->getOrders()->count(),
                'createdAt' => $customer->getCreatedAt()?->format('Y-m-d H:i:s')
            ];
        }

        return $this->json([
            'success' => true,
            'customers' => $customersData,
            'count' => count($customersData)
        ]);
    }

    /**
     * Get customer statistics (Admin only)
     * GET /api/customers/stats
     */
    #[Route('/stats', name: 'api_customers_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $totalCustomers = $this->entityManager->getRepository(Customer::class)->count([]);
        
        // Customers with orders
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(DISTINCT c.id)')
            ->from(Customer::class, 'c')
            ->join('c.orders', 'o');
        
        $customersWithOrders = $qb->getQuery()->getSingleScalarResult();

        // New customers this month
        $firstDayOfMonth = new \DateTime('first day of this month 00:00:00');
        $qb2 = $this->entityManager->createQueryBuilder();
        $qb2->select('COUNT(c.id)')
            ->from(Customer::class, 'c')
            ->where('c.createdAt >= :firstDay')
            ->setParameter('firstDay', $firstDayOfMonth);
        
        $newCustomersThisMonth = $qb2->getQuery()->getSingleScalarResult();

        return $this->json([
            'success' => true,
            'stats' => [
                'total' => $totalCustomers,
                'withOrders' => (int) $customersWithOrders,
                'withoutOrders' => $totalCustomers - (int) $customersWithOrders,
                'newThisMonth' => (int) $newCustomersThisMonth
            ]
        ]);
    }
}