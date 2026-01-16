<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:database:seed',
    description: 'Seed the database with initial data',
)]
class DatabaseSeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create admin user
        $admin = $this->entityManager->getRepository(Admin::class)->findOneBy(['email' => 'admin@twins-elegance.com']);
        
        if (!$admin) {
            $admin = new Admin();
            $admin->setEmail('admin@twins-elegance.com');
            $hashedPassword = $this->passwordHasher->hashPassword($admin, 'Admin@2025');
            $admin->setPassword($hashedPassword);
            $this->entityManager->persist($admin);
            $io->success('Admin user created: admin@twins-elegance.com / Admin@2025');
        } else {
            $io->note('Admin user already exists');
        }

        // Seed products
        $products = [
            [
                'name' => 'Collier en Or 18K',
                'price' => '299.99',
                'image' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=500',
                'category' => 'Colliers',
                'description' => 'Magnifique collier en or 18 carats avec pendentif élégant. Parfait pour toutes les occasions.',
                'inStock' => true
            ],
            [
                'name' => 'Bracelet Argent Sterling',
                'price' => '89.99',
                'image' => 'https://images.unsplash.com/photo-1603561596112-0d0395e0e5f5?w=500',
                'category' => 'Bracelets',
                'description' => 'Bracelet en argent sterling avec motifs délicats. Design moderne et intemporel.',
                'inStock' => true
            ],
            [
                'name' => 'Boucles d\'Oreilles Diamants',
                'price' => '599.99',
                'image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=500',
                'category' => 'Boucles d\'Oreilles',
                'description' => 'Boucles d\'oreilles en or blanc avec diamants scintillants. Élégance et raffinement.',
                'inStock' => true
            ],
            [
                'name' => 'Bague Solitaire Or Blanc',
                'price' => '449.99',
                'image' => 'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=500',
                'category' => 'Bagues',
                'description' => 'Bague solitaire en or blanc avec pierre précieuse. Un classique intemporel.',
                'inStock' => true
            ],
            [
                'name' => 'Montre Classique Or Rose',
                'price' => '899.99',
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500',
                'category' => 'Montres',
                'description' => 'Montre classique en or rose avec cadran élégant. Design intemporel et raffiné.',
                'inStock' => true
            ],
        ];

        $existingProductCount = $this->entityManager->getRepository(Product::class)->count([]);
        
        if ($existingProductCount === 0) {
            foreach ($products as $productData) {
                $product = new Product();
                $product->setName($productData['name']);
                $product->setPrice($productData['price']);
                $product->setImage($productData['image']);
                $product->setCategory($productData['category']);
                $product->setDescription($productData['description']);
                $product->setInStock($productData['inStock']);
                $this->entityManager->persist($product);
            }
            $io->success(count($products) . ' products created');
        } else {
            $io->note('Products already exist');
        }

        $this->entityManager->flush();

        $io->success('Database seeded successfully!');

        return Command::SUCCESS;
    }
}