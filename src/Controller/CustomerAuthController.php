<?php

namespace App\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/customer')]
class CustomerAuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private ?JWTTokenManagerInterface $jwtManager = null
    ) {
    }

    /**
     * Customer Registration
     * POST /api/customer/register
     */
    #[Route('/register', name: 'api_customer_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (empty($data['email']) || empty($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email et mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if email already exists
        $existingCustomer = $this->entityManager->getRepository(Customer::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingCustomer) {
            return $this->json([
                'success' => false,
                'message' => 'Un compte avec cet email existe déjà'
            ], Response::HTTP_CONFLICT);
        }

        // Create new customer
        $customer = new Customer();
        $customer->setEmail($data['email']);
        $customer->setFirstName($data['first_name'] ?? $data['firstName'] ?? '');
        $customer->setLastName($data['last_name'] ?? $data['lastName'] ?? '');
        $customer->setPhone($data['phone'] ?? null);
        $customer->setAddress($data['address'] ?? null);
        $customer->setCity($data['city'] ?? null);
        $customer->setZipCode($data['zip_code'] ?? $data['zipCode'] ?? null);

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword(
            $customer,
            $data['password']
        );
        $customer->setPassword($hashedPassword);

        // Validate entity
        $errors = $this->validator->validate($customer);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save to database
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        // Generate JWT token for the new customer
        $token = null;
        if ($this->jwtManager) {
            $token = $this->jwtManager->create($customer);
        }

        return $this->json([
            'customer' => [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
                'city' => $customer->getCity(),
                'zip_code' => $customer->getZipCode()
            ],
            'token' => $token
        ], Response::HTTP_CREATED);
    }

    /**
     * Customer Login
     * POST /api/customer/login
     */
    #[Route('/login', name: 'api_customer_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['email']) || empty($data['password'])) {
            return $this->json([
                'message' => 'Email et mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $customer = $this->entityManager->getRepository(Customer::class)
            ->findOneBy(['email' => $data['email']]);

        if (!$customer || !$this->passwordHasher->isPasswordValid($customer, $data['password'])) {
            return $this->json([
                'message' => 'Email ou mot de passe incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Generate JWT token
        $token = null;
        if ($this->jwtManager) {
            $token = $this->jwtManager->create($customer);
        }

        return $this->json([
            'customer' => [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
                'city' => $customer->getCity(),
                'zip_code' => $customer->getZipCode()
            ],
            'token' => $token
        ]);
    }

    /**
     * Customer Logout
     * POST /api/customer/logout
     * Note: With JWT, logout is handled client-side by removing the token
     */
    #[Route('/logout', name: 'api_customer_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        // JWT is stateless, so logout just returns success
        // The client should remove the token from storage
        
        return $this->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Get Customer Profile
     * GET /api/customer/profile
     * Requires: Authorization: Bearer {token}
     */
    #[Route('/profile', name: 'api_customer_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'first_name' => $customer->getFirstName(),
            'last_name' => $customer->getLastName(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'city' => $customer->getCity(),
            'zip_code' => $customer->getZipCode()
        ]);
    }

    /**
     * Update Customer Profile
     * PUT /api/customer/profile
     * Requires: Authorization: Bearer {token}
     */
    #[Route('/profile', name: 'api_customer_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update fields if provided
        if (isset($data['first_name']) || isset($data['firstName'])) {
            $customer->setFirstName($data['first_name'] ?? $data['firstName']);
        }
        if (isset($data['last_name']) || isset($data['lastName'])) {
            $customer->setLastName($data['last_name'] ?? $data['lastName']);
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
        if (isset($data['zip_code']) || isset($data['zipCode'])) {
            $customer->setZipCode($data['zip_code'] ?? $data['zipCode']);
        }

        // Update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword(
                $customer,
                $data['password']
            );
            $customer->setPassword($hashedPassword);
        }

        // Validate changes
        $errors = $this->validator->validate($customer);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'first_name' => $customer->getFirstName(),
            'last_name' => $customer->getLastName(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'city' => $customer->getCity(),
            'zip_code' => $customer->getZipCode()
        ]);
    }

    /**
     * Get Customer Orders
     * GET /api/customer/orders
     * Requires: Authorization: Bearer {token}
     */
    #[Route('/orders', name: 'api_customer_orders', methods: ['GET'])]
    public function orders(): JsonResponse
    {
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $orders = $customer->getOrders();
        
        $ordersData = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->getOrderItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'name' => $item->getProduct()?->getName() ?? 'Produit',
                    'quantity' => $item->getQuantity(),
                    'price' => (float) $item->getPrice()
                ];
            }

            $ordersData[] = [
                'id' => $order->getId() ?? $order->getOrderNumber(),
                'total' => (float) $order->getTotal(),
                'status' => $order->getStatus() ?? 'pending',
                'createdAt' => $order->getCreatedAt()?->format('Y-m-d\TH:i:s\Z') ?? $order->getCreatedAt()?->format('c'),
                'items' => $items
            ];
        }

        return $this->json($ordersData);
    }

    /**
     * Delete Customer Account
     * DELETE /api/customer/account
     * Requires: Authorization: Bearer {token}
     */
    #[Route('/account', name: 'api_customer_delete_account', methods: ['DELETE'])]
    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verify password for security
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe requis pour confirmer la suppression'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->passwordHasher->isPasswordValid($customer, $data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe incorrect'
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete customer account
        $this->entityManager->remove($customer);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Compte supprimé avec succès'
        ]);
    }

    /**
     * Change Password
     * POST /api/customer/change-password
     * Requires: Authorization: Bearer {token}
     */
    #[Route('/change-password', name: 'api_customer_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe actuel et nouveau mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($customer, $data['current_password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect'
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate new password length
        if (strlen($data['new_password']) < 6) {
            return $this->json([
                'success' => false,
                'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Hash and save new password
        $hashedPassword = $this->passwordHasher->hashPassword(
            $customer,
            $data['new_password']
        );
        $customer->setPassword($hashedPassword);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ]);
    }
}