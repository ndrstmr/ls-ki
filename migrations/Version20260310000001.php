<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Erstellt die Messenger-Tabellen für den Doctrine-Transport.
 *
 * Symfony Messenger nutzt LISTEN/NOTIFY für sofortige Auslieferung (kein Polling).
 * auto_setup=false in messenger.yaml – diese Migration steuert die Tabellen explizit.
 *
 * Referenz: Infrastrukturkonzept Abschnitt 6.3
 */
final class Version20260310000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messenger-Tabellen für Doctrine-Transport (leichte_sprache Queue + failed Queue)';
    }

    public function up(Schema $schema): void
    {
        // Haupt-Queue: leichte_sprache
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id                BIGSERIAL PRIMARY KEY,
                body              TEXT NOT NULL,
                headers           TEXT NOT NULL,
                queue_name        VARCHAR(190) NOT NULL,
                created_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                available_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_messenger_queue     ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX idx_messenger_available ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_messenger_delivered ON messenger_messages (delivered_at)');

        // NOTIFY-Trigger: benachrichtigt Worker sofort wenn neue Message eintrifft
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION notify_messenger_messages()
            RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify('messenger_messages', NEW.queue_name::text);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER messenger_notify
            AFTER INSERT ON messenger_messages
            FOR EACH ROW EXECUTE FUNCTION notify_messenger_messages()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS messenger_notify ON messenger_messages');
        $this->addSql('DROP FUNCTION IF EXISTS notify_messenger_messages()');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
