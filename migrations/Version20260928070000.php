<?php

declare(strict_types=1);

namespace LectoresBeta\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Si la obra admite correcciones, proyectado en `Credits` (`FEAT-WRK-016`).
 *
 * `WorkOpenedForCorrection` se publicaba desde el principio y no lo escuchaba
 * nadie. El efecto no era que faltara un aviso: la corregibilidad se
 * respondía **solo con dinero**, así que los capítulos de un borrador salían
 * como corregibles y `Feedback` abría el panel contra esa respuesta.
 *
 * Una fila por obra y no una columna en `chapter_price`: la puerta es de la
 * obra, y repetirla en cada capítulo sería el mismo dato en cuarenta sitios.
 * También es lo que hace que un capítulo nuevo de una obra abierta nazca
 * correcto sin heredar nada de sus hermanos.
 *
 * **Con versión**, igual que `work_questionnaire_demand` y por la misma razón
 * (`FEAT-WRK-014`): la cola reentrega y no promete orden, así que sin versión
 * las reentregas de «abierta» y «cerrada» se turnan para siempre. Lo destapó
 * el banco de pruebas, con 158 tests cayendo por un ciclo en la cascada.
 *
 * La fecha no valía de desempate: publicar y abrir a corrección son dos clics
 * seguidos, así que las dos transiciones caen en el mismo segundo. De ahí
 * `work.status_version`, que cuenta decisiones sobre la puerta — archivar
 * tampoco cambia el estado y sí la cierra.
 *
 * **La tabla nace vacía a propósito.** Que no haya fila significa «este
 * contexto no sabe nada de esa obra», y eso **no bloquea**: las obras que ya
 * existían siguen comportándose como hasta ahora. Cerrarlas por ignorancia
 * les apagaría el catálogo sin que nadie lo hubiera decidido. A partir de
 * aquí, `WorkCreated` les da fila cerrada a todas las nuevas.
 */
final class Version20260928070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Credits projects whether a work takes corrections, which correctability ignored.';
    }

    public function up(Schema $schema): void
    {
        // Se añade con valor por defecto para las obras que ya existen, y se
        // le quita después: el defecto lo pone el modelo.
        $this->addSql('ALTER TABLE work_ctx.work ADD status_version INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE work_ctx.work ALTER status_version DROP DEFAULT');

        $this->addSql(<<<'SQL'
            CREATE TABLE credits_ctx.correction_window (
              work_id UUID NOT NULL,
              is_open BOOLEAN NOT NULL,
              version INT NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (work_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credits_ctx.correction_window');
        $this->addSql('ALTER TABLE work_ctx.work DROP status_version');
    }
}
