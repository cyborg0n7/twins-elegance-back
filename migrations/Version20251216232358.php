<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251216232358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // SQLite-compatible migration
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = $platform instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        
        if ($isSqlite) {
            $this->addSql('CREATE TABLE admins (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_A2E0150FE7927C74 ON admins (email)');
            
            $this->addSql('CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, address TEXT DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, zip_code VARCHAR(20) DEFAULT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_62534E21E7927C74 ON customers (email)');
            
            $this->addSql('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, image TEXT DEFAULT NULL, category VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, in_stock INTEGER NOT NULL, created_at DATETIME NOT NULL)');
            
            $this->addSql('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_number VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, delivery_fee NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, customer_id INTEGER NOT NULL, CONSTRAINT FK_E52FFDEE9395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id))');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_E52FFDEE551F0F81 ON orders (order_number)');
            $this->addSql('CREATE INDEX IDX_E52FFDEE9395C3F3 ON orders (customer_id)');
            
            $this->addSql('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, quantity INTEGER NOT NULL, price NUMERIC(10, 2) NOT NULL, order_ref_id INTEGER NOT NULL, product_id INTEGER NOT NULL, CONSTRAINT FK_62809DB0E238517C FOREIGN KEY (order_ref_id) REFERENCES orders (id), CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id))');
            $this->addSql('CREATE INDEX IDX_62809DB0E238517C ON order_items (order_ref_id)');
            $this->addSql('CREATE INDEX IDX_62809DB04584665A ON order_items (product_id)');
        } else {
            // MySQL syntax
            $this->addSql('CREATE TABLE admins (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_A2E0150FE7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE customers (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, address LONGTEXT DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, zip_code VARCHAR(20) DEFAULT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_62534E21E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE order_items (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, price NUMERIC(10, 2) NOT NULL, order_ref_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_62809DB0E238517C (order_ref_id), INDEX IDX_62809DB04584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE `orders` (id INT AUTO_INCREMENT NOT NULL, order_number VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, delivery_fee NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, customer_id INT NOT NULL, UNIQUE INDEX UNIQ_E52FFDEE551F0F81 (order_number), INDEX IDX_E52FFDEE9395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, image LONGTEXT DEFAULT NULL, category VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, in_stock TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB0E238517C FOREIGN KEY (order_ref_id) REFERENCES `orders` (id)');
            $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id)');
            $this->addSql('ALTER TABLE `orders` ADD CONSTRAINT FK_E52FFDEE9395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB0E238517C');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB04584665A');
        $this->addSql('ALTER TABLE `orders` DROP FOREIGN KEY FK_E52FFDEE9395C3F3');
        $this->addSql('DROP TABLE admins');
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE `orders`');
        $this->addSql('DROP TABLE products');
    }
}
