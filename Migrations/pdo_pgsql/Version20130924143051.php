<?php

namespace Icap\WikiBundle\Migrations\pdo_pgsql;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2013/09/24 02:30:54
 */
class Version20130924143051 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE icap__wiki_section (
                id SERIAL NOT NULL, 
                wiki_id INT NOT NULL, 
                parent_id INT DEFAULT NULL, 
                title VARCHAR(255) NOT NULL, 
                visible BOOLEAN NOT NULL, 
                text TEXT DEFAULT NULL, 
                created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                lft INT NOT NULL, 
                lvl INT NOT NULL, 
                rgt INT NOT NULL, 
                root INT DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE INDEX IDX_82904AAAA948DBE ON icap__wiki_section (wiki_id)
        ");
        $this->addSql("
            CREATE INDEX IDX_82904AA727ACA70 ON icap__wiki_section (parent_id)
        ");
        $this->addSql("
            CREATE TABLE icap__wiki (
                id SERIAL NOT NULL, 
                root_id INT DEFAULT NULL, 
                resourceNode_id INT DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE UNIQUE INDEX UNIQ_1FAD6B8179066886 ON icap__wiki (root_id)
        ");
        $this->addSql("
            CREATE UNIQUE INDEX UNIQ_1FAD6B81B87FAB32 ON icap__wiki (resourceNode_id)
        ");
        $this->addSql("
            ALTER TABLE icap__wiki_section 
            ADD CONSTRAINT FK_82904AAAA948DBE FOREIGN KEY (wiki_id) 
            REFERENCES icap__wiki (id) 
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
        $this->addSql("
            ALTER TABLE icap__wiki_section 
            ADD CONSTRAINT FK_82904AA727ACA70 FOREIGN KEY (parent_id) 
            REFERENCES icap__wiki_section (id) 
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
        $this->addSql("
            ALTER TABLE icap__wiki 
            ADD CONSTRAINT FK_1FAD6B8179066886 FOREIGN KEY (root_id) 
            REFERENCES icap__wiki_section (id) 
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
        $this->addSql("
            ALTER TABLE icap__wiki 
            ADD CONSTRAINT FK_1FAD6B81B87FAB32 FOREIGN KEY (resourceNode_id) 
            REFERENCES claro_resource_node (id) 
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE icap__wiki_section 
            DROP CONSTRAINT FK_82904AA727ACA70
        ");
        $this->addSql("
            ALTER TABLE icap__wiki 
            DROP CONSTRAINT FK_1FAD6B8179066886
        ");
        $this->addSql("
            ALTER TABLE icap__wiki_section 
            DROP CONSTRAINT FK_82904AAAA948DBE
        ");
        $this->addSql("
            DROP TABLE icap__wiki_section
        ");
        $this->addSql("
            DROP TABLE icap__wiki
        ");
    }
}