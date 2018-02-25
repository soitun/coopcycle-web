<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180224221039 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_item (id SERIAL NOT NULL, delivery_id INT DEFAULT NULL, task_id INT NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CE87ED8412136921 ON delivery_item (delivery_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CE87ED848DB60186 ON delivery_item (task_id)');
        $this->addSql('ALTER TABLE delivery_item ADD CONSTRAINT FK_CE87ED8412136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_item ADD CONSTRAINT FK_CE87ED848DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE delivery_item');
    }
}
