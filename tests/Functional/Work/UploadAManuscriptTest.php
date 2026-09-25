<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Crear una obra subiendo un fichero (`FEAT-WRK-002`).
 *
 * **Dos pasos, y es la respuesta a `W-4`**: el servidor lee el fichero y
 * propone dónde acaba cada capítulo; el autor lo revisa y confirma. La obra
 * nace en el segundo, y sale exactamente igual que la de `FEAT-WRK-001` —un
 * manuscrito subido no es una obra de segunda clase.
 *
 * Los `.docx` de estas pruebas se construyen aquí, con zip y XML de verdad:
 * un fichero de muestra guardado en el repositorio sería un binario que nadie
 * puede revisar en un diff.
 */
final class UploadAManuscriptTest extends EconomyScenario
{
    /**
     * El camino entero con un `.docx`: se propone por los títulos que el
     * autor marcó, y al confirmar sale una obra con sus capítulos.
     */
    public function testADocxIsSplitByItsHeadingsAndBecomesAWork(): void
    {
        $autora = $this->activatedPerson('autora');

        $propuesta = $this->upload($autora['token'], $this->docx([
            ['La ciudad de los pájaros', true],
            ['Para quien ya no está.', false],
            ['Capítulo 1', true],
            ['Todo empezó una mañana de marzo.', false],
            ['Capítulo 2', true],
            ['Y siguió por la tarde.', false],
        ]), 'novela.docx');

        self::assertSame('novela.docx', $propuesta['filename']);
        self::assertSame(
            ['La ciudad de los pájaros', 'Capítulo 1', 'Capítulo 2'],
            array_column($propuesta['chapters'], 'title'),
            'La dedicatoria cuelga del título de portada, que es lo que tenía encima.',
        );
        self::assertSame([1, 2, 3], array_column($propuesta['chapters'], 'position'));
        self::assertGreaterThan(0, $propuesta['wordCount']);

        $workId = $this->confirm($autora['token'], $propuesta['uploadId'], ['title' => 'La ciudad de los pájaros']);

        $obra = $this->workOf($autora['token'], $workId);
        self::assertSame('La ciudad de los pájaros', $obra['title']);
        self::assertCount(3, $obra['chapters']);
        self::assertSame('DRAFT', $obra['status'], 'Nace en borrador, igual que con el editor.');
    }

    /**
     * **El texto no se pierde ni se inventa.** Lo que se lee del fichero es
     * lo que acaba en los capítulos.
     */
    public function testTheTextSurvivesTheRoundTrip(): void
    {
        $autora = $this->activatedPerson('autora');

        $propuesta = $this->upload($autora['token'], $this->docx([
            ['Capítulo único', true],
            ['Todo empezó una mañana de marzo.', false],
            ['Y nadie supo por qué.', false],
        ]), 'relato.docx');

        $workId = $this->confirm($autora['token'], $propuesta['uploadId'], ['title' => 'Relato']);
        $chapterId = $this->workOf($autora['token'], $workId)['chapters'][0]['chapterId'];

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $html = (string) $this->payload()['content'];
        self::assertStringContainsString('Todo empezó una mañana de marzo.', $html);
        self::assertStringContainsString('Y nadie supo por qué.', $html);
    }

    /**
     * Un `.txt` no tiene estructura que leer, así que se propone **un solo
     * capítulo**: fingir que se distingue un título de una frase corta sería
     * inventarse capítulos.
     */
    public function testAPlainTextFileBecomesOneChapter(): void
    {
        $autora = $this->activatedPerson('autora');

        $propuesta = $this->upload(
            $autora['token'],
            "Todo empezó una mañana.\n\nY siguió por la tarde.",
            'relato.txt',
        );

        self::assertCount(1, $propuesta['chapters']);
        self::assertNull($propuesta['chapters'][0]['title']);
    }

    /**
     * El autor **corrige los títulos** que se le propusieron, y por posición:
     * cambiar el primero de tres no obliga a reenviar los tres.
     */
    public function testTheAuthorCanCorrectTheProposedTitles(): void
    {
        $autora = $this->activatedPerson('autora');

        $propuesta = $this->upload($autora['token'], $this->docx([
            ['Capitulo 1', true],
            ['Todo empezó una mañana.', false],
            ['Capítulo 2', true],
            ['Y siguió por la tarde.', false],
        ]), 'novela.docx');

        $workId = $this->confirm($autora['token'], $propuesta['uploadId'], [
            'title' => 'La obra',
            'chapterTitles' => ['Prólogo', ''],
        ]);

        self::assertSame(
            ['Prólogo', 'Capítulo 2'],
            array_column($this->workOf($autora['token'], $workId)['chapters'], 'title'),
            'El vacío deja el que se propuso.',
        );
    }

    /**
     * **El texto pasa por el mismo saneador que el editor.** Un `.docx` es
     * literalmente lo que se pega desde Word: no merece más confianza por
     * venir en un fichero.
     */
    public function testTheTextIsSanitisedLikeAnythingElse(): void
    {
        $autora = $this->activatedPerson('autora');

        $propuesta = $this->upload($autora['token'], $this->docx([
            ['Capítulo 1', true],
            ['Hola <script>alert(1)</script> mundo.', false],
        ]), 'novela.docx');

        $workId = $this->confirm($autora['token'], $propuesta['uploadId'], ['title' => 'La obra']);
        $chapterId = $this->workOf($autora['token'], $workId)['chapters'][0]['chapterId'];

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertStringNotContainsString('<script', (string) $this->payload()['content']);
    }

    /**
     * Un formato que no se entiende **lo dice**, en vez de fallar por dentro.
     * Aquí quien pregunta es el dueño del fichero: no hay nada que proteger y
     * lo único que le sirve es saber qué arreglar.
     */
    public function testAnUnsupportedFileSaysSo(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->post($autora['token'], "\x89PNG\r\n\x1a\n\x00\x00binario", 'portada.png');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNSUPPORTED_FILE_TYPE', $this->payload()['code']);
    }

    /**
     * Un zip que no lleva un documento de Word dentro es un zip, no un
     * `.docx`, aunque se llame así. **El tipo lo decide el contenido.**.
     */
    public function testAZipThatIsNotADocxIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->post($autora['token'], $this->zip(['cualquiera.txt' => 'hola']), 'novela.docx');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNSUPPORTED_FILE_TYPE', $this->payload()['code']);
    }

    /**
     * Y un fichero vacío no es una obra vacía: no hay nada que leer.
     */
    public function testAFileWithNoTextSaysSo(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->post($autora['token'], "   \n\n  ", 'vacio.txt');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('FILE_HAS_NO_TEXT', $this->payload()['code']);
    }

    /**
     * **La subida de otra persona no existe**: que exista es información
     * sobre lo que alguien está escribiendo.
     */
    public function testSomebodyElsesUploadCannotBeConfirmed(): void
    {
        $autora = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');

        $propuesta = $this->upload($autora['token'], "Un relato corto.\n", 'relato.txt');

        $this->postConfirm($otra['token'], $propuesta['uploadId'], ['title' => 'Robada']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('MANUSCRIPT_UPLOAD_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * Y **se borra al confirmar**: el texto ya vive en sus capítulos, y
     * tenerlo dos veces es tenerlo mal una de las dos. Confirmar otra vez la
     * misma subida no crea una segunda obra.
     */
    public function testConfirmingTwiceDoesNotCreateTwoWorks(): void
    {
        $autora = $this->activatedPerson('autora');

        $propuesta = $this->upload($autora['token'], "Un relato corto.\n", 'relato.txt');
        $this->confirm($autora['token'], $propuesta['uploadId'], ['title' => 'El relato']);

        $this->postConfirm($autora['token'], $propuesta['uploadId'], ['title' => 'Otra vez']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Sin título, el nombre del fichero: nadie quiere una obra llamada
     * «relato», pero es mejor que rechazar la subida por un campo que se
     * puede cambiar en cualquier momento.
     */
    public function testWithoutATitleTheFilenameIsUsed(): void
    {
        $autora = $this->activatedPerson('autora');

        $propuesta = $this->upload($autora['token'], "Un relato corto.\n", 'mi novela.txt');
        $workId = $this->confirm($autora['token'], $propuesta['uploadId'], []);

        self::assertSame('mi novela', $this->workOf($autora['token'], $workId)['title']);
    }

    public function testWithoutASessionThereIsNothingToUpload(): void
    {
        $this->client->request('POST', '/api/v1/manuscript-uploads');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(string $token, string $bytes, string $filename): array
    {
        $this->post($token, $bytes, $filename);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->payload();
    }

    private function post(string $token, string $bytes, string $filename): void
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        self::assertIsString($path);
        file_put_contents($path, $bytes);

        try {
            $this->client->request('POST', '/api/v1/manuscript-uploads', files: [
                'file' => new UploadedFile($path, $filename, null, null, true),
            ], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        } finally {
            @unlink($path);
        }

        $this->capture();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function confirm(string $token, string $uploadId, array $body): string
    {
        $this->postConfirm($token, $uploadId, $body);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['workId'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postConfirm(string $token, string $uploadId, array $body): void
    {
        $this->client->request('POST', \sprintf('/api/v1/manuscript-uploads/%s/work', $uploadId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * @return array<string, mixed>
     */
    private function workOf(string $token, string $workId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * Un `.docx` de verdad: un zip con `word/document.xml` dentro, y los
     * títulos marcados con `w:pStyle` como los marca Word.
     *
     * @param list<array{0: string, 1: bool}> $blocks
     */
    private function docx(array $blocks): string
    {
        $paragraphs = '';

        foreach ($blocks as [$text, $isHeading]) {
            $style = $isHeading ? '<w:pPr><w:pStyle w:val="Heading1"/></w:pPr>' : '';
            $paragraphs .= \sprintf(
                '<w:p>%s<w:r><w:t>%s</w:t></w:r></w:p>',
                $style,
                htmlspecialchars($text, \ENT_XML1, 'UTF-8'),
            );
        }

        return $this->zip(['word/document.xml' => \sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>%s</w:body></w:document>',
            $paragraphs,
        )]);
    }

    /**
     * @param array<string, string> $entries
     */
    private function zip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        self::assertIsString($path);

        $zip = new \ZipArchive();
        self::assertTrue(true === $zip->open($path, \ZipArchive::OVERWRITE));

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
