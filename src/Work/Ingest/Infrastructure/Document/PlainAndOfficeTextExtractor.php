<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Infrastructure\Document;

use LectoresBeta\Work\Ingest\Application\Port\DocumentTextExtractor;
use LectoresBeta\Work\Ingest\Domain\Exception\DocumentNotReadable;
use LectoresBeta\Work\Ingest\Domain\ValueObject\DocumentBlock;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ExtractedDocument;

/**
 * Lee `.txt` y `.docx` **sin ninguna dependencia** (`FEAT-WRK-002`, `W-3`).
 *
 * Un `.docx` es un zip con `word/document.xml` dentro, y de ahí salen los
 * párrafos y —lo que de verdad importa— **qué párrafos el autor marcó como
 * título**. Eso lo dice `w:pStyle`, y es información que él puso: es la
 * diferencia entre proponer un troceado y adivinarlo.
 *
 * `.docx` y no `.doc`: el binario de Word es un formato OLE de los noventa
 * que hoy casi nadie produce, y leerlo a mano no es razonable. El `.pdf` se
 * queda fuera por dos motivos y el segundo pesa más: extrae los párrafos mal
 * —un PDF describe dónde va cada letra, no dónde acaba una idea— y es un
 * formato con capacidad de ejecución que `file-uploads.md` ya dice que no se
 * trate como texto inofensivo. Añadir cualquiera de los dos es escribir otro
 * adaptador detrás del mismo puerto.
 *
 * **El tipo se decide por el contenido**, nunca por la extensión ni por el
 * `Content-Type` que declare el cliente: renombrar algo a `.docx` es lo
 * primero que prueba quien quiere colar otra cosa.
 */
final class PlainAndOfficeTextExtractor implements DocumentTextExtractor
{
    public const ACCEPTED = ['.txt', '.docx'];

    /**
     * Los primeros bytes de un zip. Un `.docx` es uno, así que si empieza por
     * aquí se intenta abrir como tal, se llame como se llame.
     */
    private const ZIP_MAGIC = "PK\x03\x04";

    /**
     * Cuántos caracteres puede ocupar el XML de un documento antes de que
     * abrirlo sea el problema.
     *
     * Un zip pequeño puede descomprimirse en gigabytes —eso es una bomba de
     * descompresión, no un manuscrito—, y el límite del fichero subido no
     * protege de eso porque se mide antes de descomprimir.
     */
    private const MAX_XML_BYTES = 80 * 1024 * 1024;

    public function extract(string $bytes, string $filename): ExtractedDocument
    {
        if (str_starts_with($bytes, self::ZIP_MAGIC)) {
            return ExtractedDocument::of($this->fromDocx($bytes));
        }

        if (!$this->looksLikeText($bytes)) {
            throw DocumentNotReadable::inThatFormat(self::ACCEPTED);
        }

        return ExtractedDocument::of($this->fromPlainText($bytes));
    }

    /**
     * Un texto plano no tiene cabecera que lo identifique, así que se mira lo
     * único que se puede mirar: que sea UTF-8 válido y no lleve bytes de
     * control. Es lo que separa un `.txt` de un binario renombrado.
     */
    private function looksLikeText(string $bytes): bool
    {
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            return false;
        }

        // Tabulador, salto de línea y retorno de carro sí; el resto de
        // caracteres de control, no.
        return 1 !== preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $bytes);
    }

    /**
     * @return list<DocumentBlock>
     */
    private function fromPlainText(string $bytes): array
    {
        $lines = preg_split('/\R/u', $bytes) ?: [];

        // Ningún título: en texto plano no hay nada que leer, y fingir que se
        // distingue un título de una frase corta sería inventarse capítulos.
        return array_values(array_map(
            static fn (string $line): DocumentBlock => new DocumentBlock(trim($line), false),
            $lines,
        ));
    }

    /**
     * @return list<DocumentBlock>
     */
    private function fromDocx(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'manuscript');

        if (false === $path) {
            throw DocumentNotReadable::becauseItIsCorrupt();
        }

        try {
            file_put_contents($path, $bytes);

            return $this->blocksOf($this->documentXmlOf($path));
        } finally {
            @unlink($path);
        }
    }

    private function documentXmlOf(string $path): string
    {
        $zip = new \ZipArchive();

        if (true !== $zip->open($path)) {
            throw DocumentNotReadable::becauseItIsCorrupt();
        }

        try {
            $entry = $zip->statName('word/document.xml');

            // Un zip que no lleva esto dentro es un zip, no un `.docx`.
            if (false === $entry) {
                throw DocumentNotReadable::inThatFormat(self::ACCEPTED);
            }

            if (($entry['size'] ?? 0) > self::MAX_XML_BYTES) {
                throw DocumentNotReadable::becauseItIsTooLarge(self::MAX_XML_BYTES);
            }

            $xml = $zip->getFromName('word/document.xml');

            if (false === $xml) {
                throw DocumentNotReadable::becauseItIsCorrupt();
            }

            return $xml;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<DocumentBlock>
     */
    private function blocksOf(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();

            // `LIBXML_NONET` y sin `LIBXML_NOENT`: un XML ajeno no abre
            // conexiones ni expande entidades. Es la forma clásica de leer un
            // fichero del servidor a través de un documento subido.
            $loaded = $document->loadXML($xml, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);

            if (!$loaded) {
                throw DocumentNotReadable::becauseItIsCorrupt();
            }

            return $this->paragraphsOf($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return list<DocumentBlock>
     */
    private function paragraphsOf(\DOMDocument $document): array
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xpath->query('//w:p');

        if (false === $paragraphs) {
            throw DocumentNotReadable::becauseItIsCorrupt();
        }

        $blocks = [];

        foreach ($paragraphs as $paragraph) {
            if (!$paragraph instanceof \DOMElement) {
                continue;
            }

            $blocks[] = new DocumentBlock(
                trim($this->textOf($xpath, $paragraph)),
                $this->isHeading($xpath, $paragraph),
            );
        }

        return $blocks;
    }

    /**
     * Todo el texto de un párrafo. Word lo parte en tantos `w:t` como
     * cambios de formato haya, así que una frase con una palabra en cursiva
     * llega en tres trozos y hay que volver a juntarla.
     */
    private function textOf(\DOMXPath $xpath, \DOMElement $paragraph): string
    {
        $runs = $xpath->query('.//w:t', $paragraph);
        $text = '';

        foreach ($runs ?: [] as $run) {
            if ($run instanceof \DOMNode) {
                $text .= $run->textContent;
            }
        }

        return $text;
    }

    /**
     * Si el autor le aplicó un estilo de título.
     *
     * Se mira el identificador del estilo y no su nombre visible: `Heading1`
     * en un Word en inglés y `Ttulo1` en uno en español son el mismo estilo,
     * y el identificador es el que no cambia de idioma. El prefijo cubre los
     * dos, y `title` cubre el estilo de portada.
     */
    private function isHeading(\DOMXPath $xpath, \DOMElement $paragraph): bool
    {
        $style = $xpath->query('.//w:pStyle/@w:val', $paragraph);
        $first = false === $style ? null : $style->item(0);

        if (!$first instanceof \DOMAttr) {
            return false;
        }

        $value = strtolower($first->value);

        return str_starts_with($value, 'heading') || str_starts_with($value, 'ttulo')
            || str_starts_with($value, 'titulo') || 'title' === $value;
    }
}
