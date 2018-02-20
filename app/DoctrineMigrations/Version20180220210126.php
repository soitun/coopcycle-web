<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180220210126 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_step (id SERIAL NOT NULL, delivery_id INT NOT NULL, task_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_922536A612136921 ON delivery_step (delivery_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_922536A68DB60186 ON delivery_step (task_id)');
        $this->addSql('CREATE UNIQUE INDEX delivery_step_delivery_task_unique ON delivery_step (delivery_id, task_id)');
        $this->addSql('ALTER TABLE delivery_step ADD CONSTRAINT FK_922536A612136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_step ADD CONSTRAINT FK_922536A68DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $tasks = [];
        $stmt = $this->connection->prepare('SELECT * FROM task WHERE delivery_id IS NOT NULL');

        $stmt->execute();
        while ($task = $stmt->fetch()) {
            $this->addSql('INSERT INTO delivery_step (delivery_id, task_id) VALUES(:delivery_id, :task_id)', [
                'delivery_id' => $task['delivery_id'],
                'task_id' => $task['id']
            ]);
        }

        $this->addSql('ALTER TABLE task DROP CONSTRAINT fk_527edb2512136921');
        $this->addSql('DROP INDEX idx_527edb2512136921');
        $this->addSql('ALTER TABLE task DROP delivery_id');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE delivery_step');
        $this->addSql('ALTER TABLE task_event ALTER task_id SET NOT NULL');
        $this->addSql('ALTER TABLE task ADD delivery_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT fk_527edb2512136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_527edb2512136921 ON task (delivery_id)');
    }
}
