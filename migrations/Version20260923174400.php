<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El esquema inicial completo: 66 tablas repartidas en ocho esquemas de
 * PostgreSQL, uno por bounded context (`P-1`).
 *
 * Separar los esquemas hace visible la frontera: un `JOIN` entre contextos
 * deja de ser algo que alguien escribe sin darse cuenta. **No hay ninguna
 * clave foránea entre esquemas.** La integridad entre contextos es eventual y
 * se mantiene reaccionando a eventos, no con `ON DELETE CASCADE`.
 *
 * Tres detalles que conviene no «arreglar» más adelante:
 *
 * - `credits_ctx.credit_account.balance` es un entero con signo y **no lleva
 *   ninguna restricción que impida valores negativos**. El corrector cobra
 *   siempre, así que el autor puede quedar en descubierto (`FEAT-CRD-018`).
 * - los índices únicos **parciales** son la única garantía real de varias
 *   reglas de negocio bajo concurrencia, no una optimización.
 * - los dos índices del catálogo son **GIN**, porque el filtro es «cualquiera
 *   de estos valores» sobre JSONB (`decision:0008`).
 */
final class Version20260923174400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Esquema inicial: ocho esquemas, uno por bounded context, y sus 66 tablas.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA reading_ctx');
        $this->addSql('CREATE SCHEMA user_ctx');
        $this->addSql('CREATE SCHEMA moderation_ctx');
        $this->addSql('CREATE SCHEMA community_ctx');
        $this->addSql('CREATE SCHEMA work_ctx');
        $this->addSql('CREATE SCHEMA credits_ctx');
        $this->addSql('CREATE SCHEMA feedback_ctx');
        $this->addSql('CREATE SCHEMA notification_ctx');
        $this->addSql(<<<'SQL'
            CREATE TABLE reading_ctx.access_invitation (
              work_id UUID NOT NULL,
              author_id UUID NOT NULL,
              invitee_id UUID NOT NULL,
              status VARCHAR(16) NOT NULL,
              message TEXT DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_access_invitation_invitee ON reading_ctx.access_invitation (invitee_id, status)
        SQL);
        $this->addSql('CREATE INDEX idx_access_invitation_work ON reading_ctx.access_invitation (work_id, status)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_access_invitation_pending ON reading_ctx.access_invitation (work_id, invitee_id)
            WHERE
              (
                (status):: text = 'PENDING' :: text
              )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE reading_ctx.access_request (
              work_id UUID NOT NULL,
              requester_id UUID NOT NULL,
              author_id UUID NOT NULL,
              status VARCHAR(16) NOT NULL,
              message TEXT DEFAULT NULL,
              requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_access_request_author ON reading_ctx.access_request (author_id, status)');
        $this->addSql('CREATE INDEX idx_access_request_requester ON reading_ctx.access_request (requester_id, status)');
        $this->addSql('CREATE INDEX idx_access_request_work ON reading_ctx.access_request (work_id, status)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_access_request_pending ON reading_ctx.access_request (work_id, requester_id)
            WHERE
              (
                (status):: text = 'PENDING' :: text
              )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.account (
              email VARCHAR(254) NOT NULL,
              username VARCHAR(30) NOT NULL,
              username_changed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              password_hash VARCHAR(255) DEFAULT NULL,
              auth_provider VARCHAR(16) NOT NULL,
              external_id VARCHAR(255) DEFAULT NULL,
              status VARCHAR(24) NOT NULL,
              onboarding_status VARCHAR(24) NOT NULL,
              name VARCHAR(80) DEFAULT NULL,
              description TEXT DEFAULT NULL,
              birth_date DATE DEFAULT NULL,
              avatar_url VARCHAR(512) DEFAULT NULL,
              avatar_original_url VARCHAR(512) DEFAULT NULL,
              avatar_crop JSONB DEFAULT NULL,
              cover_url VARCHAR(512) DEFAULT NULL,
              invited_by UUID DEFAULT NULL,
              registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              activated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_user_name ON user_ctx.account (name)');
        $this->addSql('CREATE INDEX idx_user_invited_by ON user_ctx.account (invited_by)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON user_ctx.account (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_username ON user_ctx.account (username)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_user_external_identity ON user_ctx.account (auth_provider, external_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.account_activation_token (
              user_id UUID NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              invalidated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_activation_token_user ON user_ctx.account_activation_token (user_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_activation_token_hash ON user_ctx.account_activation_token (token_hash)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.audit_entry (
              actor_id UUID NOT NULL,
              action VARCHAR(64) NOT NULL,
              target_type VARCHAR(32) NOT NULL,
              target_id VARCHAR(64) NOT NULL,
              reason TEXT DEFAULT NULL,
              payload JSONB NOT NULL,
              occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_audit_actor ON moderation_ctx.audit_entry (actor_id, occurred_at)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_audit_target ON moderation_ctx.audit_entry (
              target_type, target_id, occurred_at
            )
        SQL);
        $this->addSql('CREATE INDEX idx_audit_action ON moderation_ctx.audit_entry (action, occurred_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.author_genre (
              works INT NOT NULL,
              author_id UUID NOT NULL,
              genre_code VARCHAR(32) NOT NULL,
              PRIMARY KEY (author_id, genre_code)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_author_genre_code ON community_ctx.author_genre (genre_code)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.author_stats (
              display_name VARCHAR(80) DEFAULT NULL,
              avatar_url VARCHAR(512) DEFAULT NULL,
              followers INT NOT NULL,
              posts INT NOT NULL,
              published_works INT NOT NULL,
              corrections_delivered INT NOT NULL,
              tips_received INT NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              author_id UUID NOT NULL,
              PRIMARY KEY (author_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_author_stats_followers ON community_ctx.author_stats (followers)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_author_stats_corrections ON community_ctx.author_stats (corrections_delivered)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.author_subscription (
              subscriber_id UUID NOT NULL,
              author_id UUID NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_author_subscription_author ON community_ctx.author_subscription (author_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_author_subscription_subscriber ON community_ctx.author_subscription (subscriber_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_author_subscription ON community_ctx.author_subscription (subscriber_id, author_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.authorship_record (
              work_id UUID NOT NULL,
              author_id UUID NOT NULL,
              content_hash VARCHAR(128) NOT NULL,
              algorithm VARCHAR(16) NOT NULL,
              word_count INT NOT NULL,
              chapter_count SMALLINT NOT NULL,
              recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_authorship_work ON work_ctx.authorship_record (work_id, recorded_at)');
        $this->addSql('CREATE INDEX idx_authorship_author ON work_ctx.authorship_record (author_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE reading_ctx.beta_reader_access (
              work_id UUID NOT NULL,
              reader_id UUID NOT NULL,
              author_id UUID NOT NULL,
              source VARCHAR(24) NOT NULL,
              granted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_access_reader ON reading_ctx.beta_reader_access (reader_id, revoked_at)');
        $this->addSql('CREATE INDEX idx_access_work ON reading_ctx.beta_reader_access (work_id, revoked_at)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_beta_reader_access_live ON reading_ctx.beta_reader_access (work_id, reader_id)
            WHERE
              (revoked_at IS NULL)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE reading_ctx.beta_reader_group (
              author_id UUID NOT NULL,
              name VARCHAR(80) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_beta_reader_group_author ON reading_ctx.beta_reader_group (author_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE reading_ctx.beta_reader_group_member (
              added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              group_id UUID NOT NULL,
              reader_id UUID NOT NULL,
              PRIMARY KEY (group_id, reader_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_group_member_reader ON reading_ctx.beta_reader_group_member (reader_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.catalogue_entry (
              work_id UUID NOT NULL,
              author_id UUID NOT NULL,
              work_title VARCHAR(180) NOT NULL,
              chapter_title VARCHAR(180) DEFAULT NULL,
              chapter_position SMALLINT NOT NULL,
              word_count INT NOT NULL,
              price SMALLINT NOT NULL,
              author_balance INT NOT NULL,
              corrections_received INT NOT NULL,
              open_corrections SMALLINT NOT NULL,
              correctable BOOLEAN NOT NULL,
              adults_only BOOLEAN NOT NULL,
              genres JSONB NOT NULL,
              content_warnings JSONB NOT NULL,
              opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              chapter_id UUID NOT NULL,
              PRIMARY KEY (chapter_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_catalogue_correctable ON work_ctx.catalogue_entry (correctable, opened_at)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalogue_ranking ON work_ctx.catalogue_entry (
              correctable, corrections_received,
              opened_at
            )
            WHERE
              correctable
        SQL);
        // GIN y no btree: el catálogo filtra por «cualquiera de estos
        // valores» sobre un JSONB. El mapeo lo declara como índice
        // normal porque el modelo de esquema de Doctrine no sabe
        // expresar el método de acceso; el comparador solo mira nombre
        // y columnas, así que no lo toca.
        $this->addSql('CREATE INDEX idx_catalogue_genres ON work_ctx.catalogue_entry USING GIN (genres)');
        // GIN y no btree: el catálogo filtra por «cualquiera de estos
        // valores» sobre un JSONB. El mapeo lo declara como índice
        // normal porque el modelo de esquema de Doctrine no sabe
        // expresar el método de acceso; el comparador solo mira nombre
        // y columnas, así que no lo toca.
        $this->addSql('CREATE INDEX idx_catalogue_warnings ON work_ctx.catalogue_entry USING GIN (content_warnings)');
        $this->addSql('CREATE INDEX idx_catalogue_author ON work_ctx.catalogue_entry (author_id)');
        $this->addSql('CREATE INDEX idx_catalogue_work ON work_ctx.catalogue_entry (work_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.chapter (
              work_id UUID NOT NULL,
              position SMALLINT NOT NULL,
              title VARCHAR(180) DEFAULT NULL,
              content TEXT NOT NULL,
              word_count INT NOT NULL,
              visibility VARCHAR(16) NOT NULL,
              blocked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_chapter_work ON work_ctx.chapter (work_id, position)');
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.chapter_price (
              work_id UUID NOT NULL,
              author_id UUID NOT NULL,
              word_count INT NOT NULL,
              required_words INT NOT NULL,
              price SMALLINT NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              chapter_id UUID NOT NULL,
              PRIMARY KEY (chapter_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_chapter_price_work ON credits_ctx.chapter_price (work_id)');
        $this->addSql('CREATE INDEX idx_chapter_price_author ON credits_ctx.chapter_price (author_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.claim (
              type VARCHAR(32) NOT NULL,
              target_type VARCHAR(24) NOT NULL,
              target_id UUID NOT NULL,
              reporter_id UUID NOT NULL,
              subject_id UUID DEFAULT NULL,
              reason VARCHAR(32) NOT NULL,
              description TEXT DEFAULT NULL,
              status VARCHAR(16) NOT NULL,
              filed_on_behalf BOOLEAN NOT NULL,
              submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_claim_status ON moderation_ctx.claim (status, submitted_at)');
        $this->addSql('CREATE INDEX idx_claim_target ON moderation_ctx.claim (target_type, target_id)');
        $this->addSql('CREATE INDEX idx_claim_reporter ON moderation_ctx.claim (reporter_id, submitted_at)');
        $this->addSql('CREATE INDEX idx_claim_subject ON moderation_ctx.claim (subject_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_claim_reporter_target ON moderation_ctx.claim (
              reporter_id, target_type, target_id
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.claim_message (
              claim_id UUID NOT NULL,
              thread_party VARCHAR(16) NOT NULL,
              author_type VARCHAR(16) NOT NULL,
              author_id UUID NOT NULL,
              body TEXT NOT NULL,
              sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_claim_message_thread ON moderation_ctx.claim_message (claim_id, thread_party, sent_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.claim_restriction (
              dismissed_claims SMALLINT NOT NULL,
              blocked_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.claim_review (
              claim_id UUID NOT NULL,
              moderator_id UUID NOT NULL,
              decision VARCHAR(16) NOT NULL,
              motivation TEXT NOT NULL,
              reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_claim_review_claim ON moderation_ctx.claim_review (claim_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_claim_review_moderator ON moderation_ctx.claim_review (moderator_id, reviewed_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.comment_mention (
              comment_id UUID NOT NULL,
              mentioned_user_id UUID NOT NULL,
              position INT NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_comment_mention_user ON community_ctx.comment_mention (mentioned_user_id)');
        $this->addSql('CREATE INDEX idx_comment_mention_comment ON community_ctx.comment_mention (comment_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.content_preference (
              excluded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID NOT NULL,
              warning VARCHAR(32) NOT NULL,
              PRIMARY KEY (user_id, warning)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.content_review (
              target_type VARCHAR(24) NOT NULL,
              target_id UUID NOT NULL,
              outcome VARCHAR(16) NOT NULL,
              mechanism VARCHAR(48) NOT NULL,
              mechanism_version VARCHAR(16) NOT NULL,
              notes TEXT DEFAULT NULL,
              reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_content_review_target ON moderation_ctx.content_review (
              target_type, target_id, reviewed_at
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_content_review_outcome ON moderation_ctx.content_review (outcome, reviewed_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.conversation (
              member_one UUID NOT NULL,
              member_two UUID NOT NULL,
              started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              last_message_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_conversation_one ON community_ctx.conversation (member_one, last_message_at)');
        $this->addSql('CREATE INDEX idx_conversation_two ON community_ctx.conversation (member_two, last_message_at)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_conversation_pair ON community_ctx.conversation (member_one, member_two)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.correction (
              work_id UUID NOT NULL,
              chapter_id UUID NOT NULL,
              reader_id UUID DEFAULT NULL,
              author_label VARCHAR(80) DEFAULT NULL,
              owner_id UUID NOT NULL,
              questionnaire_version SMALLINT NOT NULL,
              status VARCHAR(16) NOT NULL,
              origin VARCHAR(16) NOT NULL,
              visibility VARCHAR(24) NOT NULL,
              helpful BOOLEAN DEFAULT NULL,
              rated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              tip_amount SMALLINT DEFAULT NULL,
              tipped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              submitted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_correction_work ON feedback_ctx.correction (work_id, submitted_at)');
        $this->addSql('CREATE INDEX idx_correction_reader ON feedback_ctx.correction (reader_id, status)');
        $this->addSql('CREATE INDEX idx_correction_chapter ON feedback_ctx.correction (chapter_id, status)');
        $this->addSql('CREATE INDEX idx_correction_owner ON feedback_ctx.correction (owner_id, submitted_at)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_correction_reader_chapter ON feedback_ctx.correction (chapter_id, reader_id)
            WHERE
              (reader_id IS NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.correction_answer (
              correction_id UUID NOT NULL,
              question_id UUID NOT NULL,
              position SMALLINT NOT NULL,
              text TEXT NOT NULL,
              word_count INT NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_correction_answer_correction ON feedback_ctx.correction_answer (correction_id, position)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_correction_answer_question ON feedback_ctx.correction_answer (correction_id, question_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.correction_assessment (
              correction_id UUID NOT NULL,
              mechanism VARCHAR(48) NOT NULL,
              mechanism_version VARCHAR(16) NOT NULL,
              outcome VARCHAR(16) NOT NULL,
              reason TEXT DEFAULT NULL,
              assessed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_assessment_correction ON feedback_ctx.correction_assessment (correction_id, assessed_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_assessment_outcome ON feedback_ctx.correction_assessment (outcome, assessed_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.correction_price (
              author_id UUID NOT NULL,
              amount SMALLINT NOT NULL,
              quoted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              chapter_id UUID NOT NULL,
              reader_id UUID NOT NULL,
              PRIMARY KEY (chapter_id, reader_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_correction_price_author ON credits_ctx.correction_price (author_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.correction_reply (
              correction_id UUID NOT NULL,
              author_id UUID NOT NULL,
              body TEXT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              edited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_correction_reply_correction ON feedback_ctx.correction_reply (correction_id, created_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.credit_account (
              balance INT NOT NULL,
              invitation_rewards SMALLINT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              anonymised_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              user_id UUID NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.credit_transaction (
              user_id UUID NOT NULL,
              amount INT NOT NULL,
              reason VARCHAR(32) NOT NULL,
              event_id UUID DEFAULT NULL,
              metadata JSONB NOT NULL,
              occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_credit_transaction_account ON credits_ctx.credit_transaction (user_id, occurred_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_credit_transaction_reason ON credits_ctx.credit_transaction (reason, occurred_at)
        SQL);
        $this->addSql('CREATE INDEX idx_credit_transaction_event ON credits_ctx.credit_transaction (event_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.direct_message (
              conversation_id UUID NOT NULL,
              sender_id UUID NOT NULL,
              body TEXT NOT NULL,
              sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_direct_message_conversation ON community_ctx.direct_message (conversation_id, sent_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.email_change_request (
              user_id UUID NOT NULL,
              new_email VARCHAR(254) NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_email_change_user ON user_ctx.email_change_request (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_email_change_token_hash ON user_ctx.email_change_request (token_hash)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_email_change_pending ON user_ctx.email_change_request (user_id)
            WHERE
              (consumed_at IS NULL)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.genre (
              name VARCHAR(64) NOT NULL,
              position INT NOT NULL,
              active BOOLEAN NOT NULL,
              code VARCHAR(32) NOT NULL,
              PRIMARY KEY (code)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_genre_position ON user_ctx.genre (active, position)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.legal_acceptance (
              user_id UUID NOT NULL,
              document_type VARCHAR(24) NOT NULL,
              version VARCHAR(32) NOT NULL,
              accepted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              ip_address VARCHAR(45) DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_legal_acceptance_user ON user_ctx.legal_acceptance (user_id, document_type)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.legal_document (
              type VARCHAR(24) NOT NULL,
              version VARCHAR(32) NOT NULL,
              effective_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              url VARCHAR(512) NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_legal_document_version ON user_ctx.legal_document (type, version)');
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.moderator_role (
              level VARCHAR(16) NOT NULL,
              email_alerts BOOLEAN NOT NULL,
              granted_by UUID NOT NULL,
              granted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              user_id UUID NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_moderator_alerts ON moderation_ctx.moderator_role (email_alerts, revoked_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_ctx.notification (
              recipient_id UUID NOT NULL,
              kind VARCHAR(48) NOT NULL,
              payload JSONB NOT NULL,
              source_event_id UUID DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_notification_recipient ON notification_ctx.notification (
              recipient_id, read_at, created_at
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_notification_source ON notification_ctx.notification (
              recipient_id, kind, source_event_id
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_ctx.notification_delivery (
              status VARCHAR(16) NOT NULL,
              attempts SMALLINT NOT NULL,
              failure_reason VARCHAR(255) DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              notification_id UUID NOT NULL,
              channel VARCHAR(16) NOT NULL,
              PRIMARY KEY (notification_id, channel)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_delivery_status ON notification_ctx.notification_delivery (status, updated_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.notification_preference (
              enabled BOOLEAN NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID NOT NULL,
              topic VARCHAR(48) NOT NULL,
              channel VARCHAR(16) NOT NULL,
              PRIMARY KEY (user_id, topic, channel)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.overdraft_grant (
              author_id UUID NOT NULL,
              chapter_id UUID NOT NULL,
              quota_period VARCHAR(8) NOT NULL,
              amount SMALLINT NOT NULL,
              granted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              settled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_overdraft_quota ON credits_ctx.overdraft_grant (quota_period, author_id)');
        $this->addSql('CREATE INDEX idx_overdraft_unsettled ON credits_ctx.overdraft_grant (settled_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.platform_invitation (
              inviter_id UUID NOT NULL,
              email VARCHAR(254) DEFAULT NULL,
              token_hash VARCHAR(64) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              consumed_by UUID DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_platform_invitation_inviter ON user_ctx.platform_invitation (inviter_id, consumed_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_platform_invitation_token ON user_ctx.platform_invitation (token_hash)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post (
              author_id UUID NOT NULL,
              body TEXT NOT NULL,
              type VARCHAR(32) NOT NULL,
              format VARCHAR(16) NOT NULL,
              audience VARCHAR(16) NOT NULL,
              work_id UUID DEFAULT NULL,
              comment_count INT NOT NULL,
              like_count INT NOT NULL,
              repost_count INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_post_author ON community_ctx.post (author_id, created_at)');
        $this->addSql('CREATE INDEX idx_post_wall ON community_ctx.post (created_at, audience)');
        $this->addSql('CREATE INDEX idx_post_type ON community_ctx.post (type, created_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post_attachment (
              post_id UUID NOT NULL,
              url VARCHAR(512) NOT NULL,
              media_type VARCHAR(64) NOT NULL,
              position SMALLINT NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_post_attachment_post ON community_ctx.post_attachment (post_id, position)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post_comment (
              post_id UUID NOT NULL,
              author_id UUID NOT NULL,
              parent_comment_id UUID DEFAULT NULL,
              body TEXT NOT NULL,
              reply_count INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              edited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_post_comment_post ON community_ctx.post_comment (post_id, created_at)');
        $this->addSql('CREATE INDEX idx_post_comment_parent ON community_ctx.post_comment (parent_comment_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post_like (
              liked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              post_id UUID NOT NULL,
              member_id UUID NOT NULL,
              PRIMARY KEY (post_id, member_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_post_like_member ON community_ctx.post_like (member_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post_reaction (
              emoji VARCHAR(16) NOT NULL,
              reacted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              post_id UUID NOT NULL,
              member_id UUID NOT NULL,
              PRIMARY KEY (post_id, member_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.post_repost (
              post_id UUID NOT NULL,
              member_id UUID NOT NULL,
              comment TEXT DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_post_repost_member ON community_ctx.post_repost (member_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_post_repost ON community_ctx.post_repost (post_id, member_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.processed_event (
              event_name VARCHAR(64) NOT NULL,
              processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              event_id UUID NOT NULL,
              PRIMARY KEY (event_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_processed_event_date ON credits_ctx.processed_event (processed_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.public_link (
              work_id UUID NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              label VARCHAR(80) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_public_link_work ON work_ctx.public_link (work_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_public_link_token ON work_ctx.public_link (token_hash)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.published_book (
              user_id UUID NOT NULL,
              title VARCHAR(255) NOT NULL,
              publisher VARCHAR(255) DEFAULT NULL,
              publication_year SMALLINT DEFAULT NULL,
              purchase_url VARCHAR(512) DEFAULT NULL,
              cover_url VARCHAR(512) DEFAULT NULL,
              position INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_published_book_user ON user_ctx.published_book (user_id, position)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.question (
              questionnaire_id UUID NOT NULL,
              position SMALLINT NOT NULL,
              statement TEXT NOT NULL,
              example TEXT DEFAULT NULL,
              required BOOLEAN NOT NULL,
              min_words SMALLINT DEFAULT NULL,
              max_words SMALLINT DEFAULT NULL,
              scope VARCHAR(16) NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_question_questionnaire ON work_ctx.question (questionnaire_id, position)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.questionnaire (
              work_id UUID NOT NULL,
              version SMALLINT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_questionnaire_version ON work_ctx.questionnaire (work_id, version)');
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_ctx.recipient_preference (
              enabled BOOLEAN NOT NULL,
              all_muted BOOLEAN NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              recipient_id UUID NOT NULL,
              topic VARCHAR(48) NOT NULL,
              channel VARCHAR(16) NOT NULL,
              PRIMARY KEY (recipient_id, topic, channel)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.refresh_token (
              user_id UUID NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              user_agent VARCHAR(255) DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_refresh_token_user ON user_ctx.refresh_token (user_id, revoked_at)');
        $this->addSql('CREATE INDEX idx_refresh_token_expires ON user_ctx.refresh_token (expires_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_refresh_token_hash ON user_ctx.refresh_token (token_hash)');
        $this->addSql(<<<'SQL'
            CREATE TABLE moderation_ctx.sanction (
              user_id UUID NOT NULL,
              type VARCHAR(24) NOT NULL,
              duration VARCHAR(16) DEFAULT NULL,
              reason TEXT NOT NULL,
              claim_id UUID DEFAULT NULL,
              imposed_by UUID NOT NULL,
              imposed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              lifted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sanction_user ON moderation_ctx.sanction (user_id, lifted_at, expires_at)');
        $this->addSql('CREATE INDEX idx_sanction_claim ON moderation_ctx.sanction (claim_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE community_ctx.user_block (
              blocker_id UUID NOT NULL,
              blocked_id UUID NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_user_block_blocked ON community_ctx.user_block (blocked_id)');
        $this->addSql('CREATE INDEX idx_user_block_blocker ON community_ctx.user_block (blocker_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_block ON community_ctx.user_block (blocker_id, blocked_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.user_genre (
              selected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID NOT NULL,
              genre_code VARCHAR(32) NOT NULL,
              PRIMARY KEY (user_id, genre_code)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_user_genre_genre ON user_ctx.user_genre (genre_code)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.user_notification_settings (
              all_muted BOOLEAN NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.user_privacy_settings (
              profile_visibility VARCHAR(16) NOT NULL,
              comment_permission VARCHAR(16) NOT NULL,
              message_permission VARCHAR(16) NOT NULL,
              activity_visible BOOLEAN NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID NOT NULL,
              PRIMARY KEY (user_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.user_tour (
              last_step INT DEFAULT NULL,
              dismissed BOOLEAN NOT NULL,
              completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID NOT NULL,
              tour_id VARCHAR(64) NOT NULL,
              PRIMARY KEY (user_id, tour_id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_ctx.username_alias (
              user_id UUID DEFAULT NULL,
              reason VARCHAR(24) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              username VARCHAR(30) NOT NULL,
              PRIMARY KEY (username)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_username_alias_expires ON user_ctx.username_alias (expires_at)');
        $this->addSql('CREATE INDEX idx_username_alias_user ON user_ctx.username_alias (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.work (
              author_id UUID NOT NULL,
              title VARCHAR(180) NOT NULL,
              synopsis TEXT DEFAULT NULL,
              status VARCHAR(16) NOT NULL,
              status_changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              access_mode VARCHAR(16) NOT NULL,
              adults_only BOOLEAN NOT NULL,
              word_count INT NOT NULL,
              chapter_count SMALLINT NOT NULL,
              blocked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_work_author ON work_ctx.work (author_id, status)');
        $this->addSql('CREATE INDEX idx_work_status ON work_ctx.work (status, status_changed_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.work_content_warning (
              work_id UUID NOT NULL,
              warning VARCHAR(32) NOT NULL,
              PRIMARY KEY (work_id, warning)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_work_warning ON work_ctx.work_content_warning (warning)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_ctx.work_genre (
              work_id UUID NOT NULL,
              genre_code VARCHAR(32) NOT NULL,
              PRIMARY KEY (work_id, genre_code)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_work_genre_code ON work_ctx.work_genre (genre_code)');
        $this->addSql(<<<'SQL'
            CREATE TABLE feedback_ctx.work_rating (
              work_id UUID NOT NULL,
              reader_id UUID NOT NULL,
              value SMALLINT NOT NULL,
              rated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_work_rating_work ON feedback_ctx.work_rating (work_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_work_rating_reader ON feedback_ctx.work_rating (work_id, reader_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE reading_ctx.writing_buddy_link (
              member_one UUID NOT NULL,
              member_two UUID NOT NULL,
              proposed_by UUID NOT NULL,
              status VARCHAR(16) NOT NULL,
              proposed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_writing_buddy_one ON reading_ctx.writing_buddy_link (member_one, status)');
        $this->addSql('CREATE INDEX idx_writing_buddy_two ON reading_ctx.writing_buddy_link (member_two, status)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_writing_buddy_live ON reading_ctx.writing_buddy_link (member_one, member_two)
            WHERE
              (
                (status):: text = ANY (
                  (
                    ARRAY[ 'PROPOSED' :: character varying,
                    'ACCEPTED' :: character varying]
                  ):: text[]
                )
              )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
              id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              body TEXT NOT NULL,
              headers TEXT NOT NULL,
              queue_name VARCHAR(190) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (
              queue_name, available_at, delivered_at,
              id
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE reading_ctx.access_invitation');
        $this->addSql('DROP TABLE reading_ctx.access_request');
        $this->addSql('DROP TABLE user_ctx.account');
        $this->addSql('DROP TABLE user_ctx.account_activation_token');
        $this->addSql('DROP TABLE moderation_ctx.audit_entry');
        $this->addSql('DROP TABLE community_ctx.author_genre');
        $this->addSql('DROP TABLE community_ctx.author_stats');
        $this->addSql('DROP TABLE community_ctx.author_subscription');
        $this->addSql('DROP TABLE work_ctx.authorship_record');
        $this->addSql('DROP TABLE reading_ctx.beta_reader_access');
        $this->addSql('DROP TABLE reading_ctx.beta_reader_group');
        $this->addSql('DROP TABLE reading_ctx.beta_reader_group_member');
        $this->addSql('DROP TABLE work_ctx.catalogue_entry');
        $this->addSql('DROP TABLE work_ctx.chapter');
        $this->addSql('DROP TABLE credits_ctx.chapter_price');
        $this->addSql('DROP TABLE moderation_ctx.claim');
        $this->addSql('DROP TABLE moderation_ctx.claim_message');
        $this->addSql('DROP TABLE moderation_ctx.claim_restriction');
        $this->addSql('DROP TABLE moderation_ctx.claim_review');
        $this->addSql('DROP TABLE community_ctx.comment_mention');
        $this->addSql('DROP TABLE user_ctx.content_preference');
        $this->addSql('DROP TABLE moderation_ctx.content_review');
        $this->addSql('DROP TABLE community_ctx.conversation');
        $this->addSql('DROP TABLE feedback_ctx.correction');
        $this->addSql('DROP TABLE feedback_ctx.correction_answer');
        $this->addSql('DROP TABLE feedback_ctx.correction_assessment');
        $this->addSql('DROP TABLE credits_ctx.correction_price');
        $this->addSql('DROP TABLE feedback_ctx.correction_reply');
        $this->addSql('DROP TABLE credits_ctx.credit_account');
        $this->addSql('DROP TABLE credits_ctx.credit_transaction');
        $this->addSql('DROP TABLE community_ctx.direct_message');
        $this->addSql('DROP TABLE user_ctx.email_change_request');
        $this->addSql('DROP TABLE user_ctx.genre');
        $this->addSql('DROP TABLE user_ctx.legal_acceptance');
        $this->addSql('DROP TABLE user_ctx.legal_document');
        $this->addSql('DROP TABLE moderation_ctx.moderator_role');
        $this->addSql('DROP TABLE notification_ctx.notification');
        $this->addSql('DROP TABLE notification_ctx.notification_delivery');
        $this->addSql('DROP TABLE user_ctx.notification_preference');
        $this->addSql('DROP TABLE credits_ctx.overdraft_grant');
        $this->addSql('DROP TABLE user_ctx.platform_invitation');
        $this->addSql('DROP TABLE community_ctx.post');
        $this->addSql('DROP TABLE community_ctx.post_attachment');
        $this->addSql('DROP TABLE community_ctx.post_comment');
        $this->addSql('DROP TABLE community_ctx.post_like');
        $this->addSql('DROP TABLE community_ctx.post_reaction');
        $this->addSql('DROP TABLE community_ctx.post_repost');
        $this->addSql('DROP TABLE credits_ctx.processed_event');
        $this->addSql('DROP TABLE work_ctx.public_link');
        $this->addSql('DROP TABLE user_ctx.published_book');
        $this->addSql('DROP TABLE work_ctx.question');
        $this->addSql('DROP TABLE work_ctx.questionnaire');
        $this->addSql('DROP TABLE notification_ctx.recipient_preference');
        $this->addSql('DROP TABLE user_ctx.refresh_token');
        $this->addSql('DROP TABLE moderation_ctx.sanction');
        $this->addSql('DROP TABLE community_ctx.user_block');
        $this->addSql('DROP TABLE user_ctx.user_genre');
        $this->addSql('DROP TABLE user_ctx.user_notification_settings');
        $this->addSql('DROP TABLE user_ctx.user_privacy_settings');
        $this->addSql('DROP TABLE user_ctx.user_tour');
        $this->addSql('DROP TABLE user_ctx.username_alias');
        $this->addSql('DROP TABLE work_ctx.work');
        $this->addSql('DROP TABLE work_ctx.work_content_warning');
        $this->addSql('DROP TABLE work_ctx.work_genre');
        $this->addSql('DROP TABLE feedback_ctx.work_rating');
        $this->addSql('DROP TABLE reading_ctx.writing_buddy_link');
        $this->addSql('DROP TABLE messenger_messages');

        foreach (['user_ctx', 'work_ctx', 'reading_ctx', 'feedback_ctx',
            'community_ctx', 'credits_ctx', 'moderation_ctx',
            'notification_ctx'] as $schemaName) {
            $this->addSql(\sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $schemaName));
        }
    }
}
