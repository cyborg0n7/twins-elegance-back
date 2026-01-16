<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'api_products_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $products = $this->entityManager->getRepository(Product::class)->findAll();

        return $this->json(
            json_decode(
                $this->serializer->serialize($products, 'json', ['groups' => 'product:read']),
                true
            )
        );
    }

    #[Route('/{id}', name: 'api_products_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $product = $this->entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            json_decode(
                $this->serializer->serialize($product, 'json', ['groups' => 'product:read']),
                true
            )
        );
    }

    #[Route('', name: 'api_products_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            // Only admins can create products - checked by security.yaml
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (empty($data['name'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le nom du produit est requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['price']) || !is_numeric($data['price'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le prix doit être un nombre valide'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['category'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'La catégorie est requise'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate image if provided (check if it's base64 and not too long)
            $image = $data['image'] ?? null;
            if ($image && strlen($image) > 10000000) { // ~10MB limit for base64
                return $this->json([
                    'success' => false,
                    'message' => 'L\'image est trop volumineuse (maximum ~7.5MB en base64)'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product = new Product();
            $product->setName(trim($data['name']));
            // Convert price to string for DECIMAL type
            $product->setPrice((string)$data['price']);
            $product->setImage($image);
            $product->setCategory(trim($data['category']));
            $product->setDescription($data['description'] ? trim($data['description']) : null);
            // Ensure inStock is a boolean
            $product->setInStock(isset($data['inStock']) ? (bool)$data['inStock'] : true);

            // Doctrine ORM handles transactions automatically with flush()
            // Try to persist and flush, with automatic reconnection on connection loss
            try {
                $this->entityManager->persist($product);
                $this->entityManager->flush();
            } catch (\Doctrine\DBAL\Exception\ConnectionLost $e) {
                // Connection lost during operation, try to reconnect and retry once
                $connection = $this->entityManager->getConnection();
                try {
                    $connection->close();
                } catch (\Exception $closeException) {
                    // Ignore errors when closing
                }
                $connection->connect();
                
                // Retry the operation
                $this->entityManager->persist($product);
                $this->entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'message' => 'Produit créé avec succès',
                'product' => json_decode(
                    $this->serializer->serialize($product, 'json', ['groups' => 'product:read']),
                    true
                )
            ], Response::HTTP_CREATED);
        } catch (\Doctrine\DBAL\Exception\ConnectionLost $e) {
            // Database connection lost
            return $this->json([
                'success' => false,
                'message' => 'Connexion à la base de données perdue. Vérifiez que la base de données est accessible.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            // Database errors (like data too long, constraint violations, etc.)
            $errorCode = method_exists($e, 'getErrorCode') ? $e->getErrorCode() : 'N/A';
            return $this->json([
                'success' => false,
                'message' => 'Erreur base de données: ' . $e->getMessage(),
                'error_code' => $errorCode
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Doctrine\ORM\ORMException $e) {
            // ORM errors
            return $this->json([
                'success' => false,
                'message' => 'Erreur ORM: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            // General errors
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du produit: ' . $e->getMessage(),
                'error_class' => get_class($e)
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_products_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        // Only admins can update products - checked by security.yaml
        $product = $this->entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }
        if (isset($data['price'])) {
            $product->setPrice($data['price']);
        }
        if (isset($data['image'])) {
            $product->setImage($data['image']);
        }
        if (isset($data['category'])) {
            $product->setCategory($data['category']);
        }
        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }
        if (isset($data['inStock'])) {
            $product->setInStock($data['inStock']);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Produit mis à jour',
            'product' => json_decode(
                $this->serializer->serialize($product, 'json', ['groups' => 'product:read']),
                true
            )
        ]);
    }

    #[Route('/{id}', name: 'api_products_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // Only admins can delete products - checked by security.yaml
        $product = $this->entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Produit supprimé'
        ]);
    }
}