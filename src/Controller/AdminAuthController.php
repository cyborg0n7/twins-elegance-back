<?php

namespace App\Controller;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin')]
class AdminAuthController extends AbstractController
{
    public function __construct(
        private SerializerInterface $serializer,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ?JWTTokenManagerInterface $jwtManager = null
    ) {
    }

    #[Route('/login', name: 'api_admin_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['email']) || empty($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email et mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $admin = $this->entityManager->getRepository(Admin::class)
            ->findOneBy(['email' => $data['email']]);

        if (!$admin || !$this->passwordHasher->isPasswordValid($admin, $data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Generate JWT token
        $token = null;
        if ($this->jwtManager) {
            $token = $this->jwtManager->create($admin);
        }

        return $this->json([
            'success' => true,
            'message' => 'Login successful',
            'admin' => [
                'id' => $admin->getId(),
                'email' => $admin->getEmail()
            ],
            'token' => $token
        ]);
    }

    #[Route('/logout', name: 'api_admin_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }
}