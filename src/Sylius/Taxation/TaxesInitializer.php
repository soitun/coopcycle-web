<?php

namespace AppBundle\Sylius\Taxation;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;

class TaxesInitializer
{
    private $logger;

    public function __construct(
        private Connection $conn,
        private TaxesProvider $taxesProvider,
        private TaxCategoryRepositoryInterface $taxCategoryRepository,
        private ObjectManager $em,
        ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function initialize()
    {
        $sql = "UPDATE sylius_adjustment SET origin_code = :new WHERE type = 'tax' AND origin_code = :old";
        $stmt = $this->conn->prepare($sql);

        $expectedTaxCategories = $this->taxesProvider->getCategories();

        $allMigrations = [];
        foreach ($expectedTaxCategories as $c) {
            $taxCategory = $this->taxCategoryRepository->findOneByCode($c->getCode());
            if (null === $taxCategory) {
                $this->em->persist($c);
                $this->logger->info(sprintf('Creating tax category « %s »', $c->getCode()));
            } else {
                $this->logger->info(sprintf('Tax category « %s » already exists, checking rates…', $c->getCode()));
                $migrations = $this->taxesProvider->synchronize($c, $taxCategory, $this->logger);
                $allMigrations = array_merge($allMigrations, $migrations);
            }
        }

        // https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/transactions-and-concurrency.html
        $this->conn->beginTransaction(); // suspend auto-commit
        try {

            $this->logger->info('Flushing changes…');
            $this->em->flush();

            $this->logger->info('Migrating Sylius adjustments…');
            foreach ($allMigrations as $migration) {
                [ $old, $new ] = $migration;
                $stmt->bindParam('old', $old);
                $stmt->bindParam('new', $new);
                $result = $stmt->execute();
                $this->logger->info(sprintf('Migrated %d Sylius adjustments from « %s » to « %s »', $result->rowCount(), $old, $new));
            }

            $this->conn->commit();

        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
