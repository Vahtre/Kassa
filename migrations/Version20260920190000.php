<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add a per-convent product sequence number, so Tartu and Tallinn koondised can each order
 * products independently.
 */
final class Version20260920190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add seq column to ollekassa_product_info';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ollekassa_product_info ADD seq INT DEFAULT 1');
        $this->addSql('UPDATE ollekassa_product_info pi INNER JOIN ollekassa_product p ON p.id = pi.product_id SET pi.seq = p.seq');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ollekassa_product_info DROP seq');
    }
}
