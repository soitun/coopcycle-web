<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180224172655 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT fk_3781ec10ebf23851');
        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT fk_3781ec104c6cf538');
        $this->addSql('DROP INDEX idx_3781ec10ebf23851');
        $this->addSql('DROP INDEX idx_3781ec104c6cf538');
        $this->addSql('ALTER TABLE delivery DROP origin_address_id');
        $this->addSql('ALTER TABLE delivery DROP delivery_address_id');
        $this->addSql('ALTER TABLE delivery DROP date');
        $this->addSql('ALTER TABLE delivery DROP price');
        $this->addSql('ALTER TABLE order_ ALTER customer_id SET NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER restaurant_id SET NOT NULL');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ ALTER customer_id DROP NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER restaurant_id DROP NOT NULL');
        $this->addSql('ALTER TABLE delivery ADD origin_address_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD delivery_address_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE delivery ADD price DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT fk_3781ec10ebf23851 FOREIGN KEY (delivery_address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT fk_3781ec104c6cf538 FOREIGN KEY (origin_address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_3781ec10ebf23851 ON delivery (delivery_address_id)');
        $this->addSql('CREATE INDEX idx_3781ec104c6cf538 ON delivery (origin_address_id)');
    }
}
