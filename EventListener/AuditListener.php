<?php declare(strict_types = 1);

namespace Vairogs\Component\Audit\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use ReflectionClass;
use Vairogs\Bundle\VairogsBundle;
use Vairogs\Component\Functions\Text\_SnakeCaseFromCamelCase;

use function array_key_exists;
use function strtolower;

// #[AsDoctrineListener(event: Events::preUpdate)]
// #[AsDoctrineListener(event: Events::postFlush)]
readonly class AuditListener
{
    private object $snake;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->snake = (new class {
            use _SnakeCaseFromCamelCase;
        });
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        $auditTable = $this->getAuditTableName($entity);

        if (!$this->auditTableExists($auditTable)) {
            $this->createAuditTable($entity);
        } else {
            $this->updateAuditTableSchema($entity);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
    }

    private function auditTableExists($auditTable): bool
    {
        return $this->entityManager->getConnection()->createSchemaManager()->tablesExist([$auditTable]);
    }

    private function getAuditTableName(
        object $entity,
    ): string {
        return VairogsBundle::VAIROGS . '.audit_' . strtolower($this->snake->snakeCaseFromCamelCase((new ReflectionClass($entity))->getShortName()));
    }

    private function createAuditTable(
        object $entity,
    ): void {
        $entityFields = $this->entityManager->getClassMetadata($entity::class)->fieldMappings;

        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $auditTable = new Table($this->getAuditTableName($entity));

        $auditTable->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $auditTable->addColumn('created_at', Types::DATETIME_IMMUTABLE);

        $auditTableName = strtolower($this->snake->snakeCaseFromCamelCase((new ReflectionClass($entity))->getShortName()));

        foreach ($entityFields as $field => $mapping) {
            if ('id' === $field) {
                $auditTable->addColumn($auditTableName . '_id', $mapping['type'], ['notnull' => false]);
            }

            $field = $this->snake->snakeCaseFromCamelCase($field);

            $auditTable->addColumn($field . '_old', $mapping['type'], ['notnull' => false]);
            $auditTable->addColumn($field . '_new', $mapping['type'], ['notnull' => false]);
        }
        $auditTable->setPrimaryKey(['id']);

        $schemaManager->createTable($auditTable);
    }

    private function updateAuditTableSchema(object $entity): void
    {
        $entityFields = $this->entityManager->getClassMetadata($entity::class)->fieldMappings;
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $auditTable = $this->getAuditTableName($entity);

        $columns = $schemaManager->listTableColumns($auditTable);

        $addedColumns = [];
        foreach ($entityFields as $field => $mapping) {
            if ('id' === $field) {
                continue;
            }

            $field = $this->snake->snakeCaseFromCamelCase($field);

            if (!array_key_exists($field . '_old', $columns)) {
                $columnType = Type::getType($mapping['type']);
                $addedColumns[] = new Column($field . '_old', $columnType, ['notnull' => false]);
            }

            if (!array_key_exists($field . '_new', $columns)) {
                $columnType = Type::getType($mapping['type']);
                $addedColumns[] = new Column($field . '_new', $columnType, ['notnull' => false]);
            }
        }

        if (!empty($addedColumns)) {
            $tableDiff = new TableDiff(new Table($auditTable), $addedColumns, [], [], [], [], [], [], [], [], [], []);
            $schemaManager->alterTable($tableDiff);
        }
    }
}
