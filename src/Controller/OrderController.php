<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'success' => false,
                    'message' => 'Données JSON invalides'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['customer'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Les informations client sont requises'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find or create customer
            $customerData = $data['customer'];
            $customer = $this->entityManager->getRepository(Customer::class)
                ->findOneBy(['email' => $customerData['email'] ?? $customerData['phone']]);

            // Normalize field names (accept both camelCase and snake_case)
            $firstName = $customerData['firstName'] ?? $customerData['first_name'] ?? '';
            $lastName = $customerData['lastName'] ?? $customerData['last_name'] ?? '';
            $zipCode = $customerData['zipCode'] ?? $customerData['zip_code'] ?? '';

            // Validate required fields
            if (empty($firstName) || empty($lastName)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le prénom et le nom sont requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$customer) {
                // Create new customer if not exists
                $customer = new Customer();
                $customer->setEmail($customerData['email'] ?? $customerData['phone'] . '@twins-elegance.local');
                $customer->setFirstName($firstName);
                $customer->setLastName($lastName);
                $customer->setPhone($customerData['phone'] ?? '');
                $customer->setAddress($customerData['address'] ?? '');
                $customer->setCity($customerData['city'] ?? '');
                $customer->setZipCode($zipCode);

                // Set a default password for guest customers
                $defaultPassword = $this->passwordHasher->hashPassword($customer, bin2hex(random_bytes(16)));
                $customer->setPassword($defaultPassword);

                $this->entityManager->persist($customer);
            } else {
                // Update existing customer info
                $customer->setFirstName($firstName);
                $customer->setLastName($lastName);
                $customer->setPhone($customerData['phone'] ?? $customer->getPhone());
                $customer->setAddress($customerData['address'] ?? $customer->getAddress());
                $customer->setCity($customerData['city'] ?? $customer->getCity());
                if (!empty($zipCode)) {
                    $customer->setZipCode($zipCode);
                }
            }

            if (!isset($data['items']) || empty($data['items'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'La commande doit contenir au moins un article'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create order
            $order = new Order();
            $order->setCustomer($customer);
            // Ensure all DECIMAL fields are strings
            $order->setSubtotal((string)($data['subtotal'] ?? 0));
            $order->setDeliveryFee((string)($data['deliveryFee'] ?? $data['delivery_fee'] ?? 0));
            $order->setTotal((string)($data['total'] ?? 0));
            $order->setStatus($data['status'] ?? 'pending');

            if (isset($data['id'])) {
                $order->setOrderNumber($data['id']);
            }

            // Add order items
            foreach ($data['items'] as $itemData) {
                if (!isset($itemData['id'])) {
                    return $this->json([
                        'success' => false,
                        'message' => 'ID produit manquant dans les articles'
                    ], Response::HTTP_BAD_REQUEST);
                }

                $product = $this->entityManager->getRepository(Product::class)->find($itemData['id']);

                if (!$product) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Produit non trouvé: ' . $itemData['id']
                    ], Response::HTTP_BAD_REQUEST);
                }

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($itemData['quantity'] ?? 1);
                // Ensure price is a string (DECIMAL type requires string)
                $itemPrice = isset($itemData['price']) ? (string)$itemData['price'] : $product->getPrice();
                $orderItem->setPrice($itemPrice);
                $order->addOrderItem($orderItem);

                $this->entityManager->persist($orderItem);
            }

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'order' => json_decode(
                    $this->serializer->serialize($order, 'json', ['groups' => 'order:read']),
                    true
                ),
                'id' => $order->getId()
            ], Response::HTTP_CREATED);

        } catch (\Doctrine\DBAL\Exception\ConnectionLost $e) {
            // Attempt to reconnect
            $connection = $this->entityManager->getConnection();
            $connection->close();
            $connection->connect();
            
            return $this->json([
                'success' => false,
                'message' => 'Connexion à la base de données perdue. Veuillez réessayer.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande: ' . $e->getMessage(),
                'error_class' => get_class($e)
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'api_orders_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // Only admins can list all orders
        $orders = $this->entityManager->getRepository(Order::class)->findAll();

        return $this->json(
            json_decode(
                $this->serializer->serialize($orders, 'json', ['groups' => 'order:read']),
                true
            )
        );
    }

    #[Route('/{id}', name: 'api_orders_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return $this->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user is authorized to view this order
        $user = $this->getUser();
        if ($user instanceof Customer && $order->getCustomer()->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json(
            json_decode(
                $this->serializer->serialize($order, 'json', ['groups' => 'order:read']),
                true
            )
        );
    }

    #[Route('/{id}/status', name: 'api_orders_update_status', methods: ['PUT'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        // Only admins can update order status
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return $this->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $order->setStatus($data['status']);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'order' => json_decode(
                $this->serializer->serialize($order, 'json', ['groups' => 'order:read']),
                true
            )
        ]);
    }
}