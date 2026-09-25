<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\OAuth;

use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Authentication\Application\DTO\ExternalIdentity;
use LectoresBeta\User\Authentication\Application\Port\OAuthProvider;
use LectoresBeta\User\Authentication\Domain\Exception\ExternalSignInFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google, por OpenID Connect (`FEAT-USR-002`).
 *
 * **No se guarda ninguna credencial** (`RN-5`). El `access_token` que
 * devuelve Google se usa para nada: lo único que hace falta viene ya dentro
 * del `id_token`, y ni uno ni otro salen de este método. Guardar el de acceso
 * significaría poder actuar en nombre de alguien durante meses, que es una
 * capacidad que esta funcionalidad no necesita y que habría que custodiar.
 *
 * **La firma del `id_token` no se verifica, y es correcto**: el token no
 * llega por el navegador sino de una respuesta directa del endpoint de Google
 * sobre TLS, autenticada con el `client_secret`. Es el caso que OpenID
 * Connect Core 3.1.3.7 exime expresamente de validar la firma. Verificarla
 * exigiría descargar y cachear las claves públicas de Google —una pieza más
 * que mantener y otra llamada de red— a cambio de nada.
 *
 * Lo que sí se comprueba es lo que el token dice: `sub`, `email` y sobre todo
 * `email_verified`, del que depende poder enlazar con una cuenta que ya
 * existe.
 */
final readonly class GoogleOAuthProvider implements OAuthProvider
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Lo mínimo. `openid email` da el identificador y la dirección, que es
     * todo lo que esta plataforma usa: el nombre público, la fecha de
     * nacimiento y los géneros los pone la persona en el onboarding
     * (`RN-7`), así que pedir el perfil sería pedir datos que no se van a
     * usar.
     */
    private const SCOPE = 'openid email';

    public function __construct(
        private HttpClientInterface $http,
        private string $clientId,
        private string $clientSecret,
        private string $defaultRedirectUri,
    ) {
    }

    public function handles(): AuthProvider
    {
        return AuthProvider::GOOGLE;
    }

    public function authorizationUrl(string $state, ?string $redirectUri): string
    {
        return self::AUTHORIZATION_ENDPOINT.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri($redirectUri),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            // Sin token de refresco: no hay nada que hacer en nombre de
            // nadie después de saber quién es.
            'access_type' => 'online',
            // Que se pueda elegir cuenta. Sin esto, quien tenga varias entra
            // siempre con la última, y descubrirlo cuesta una cuenta
            // duplicada.
            'prompt' => 'select_account',
        ]);
    }

    public function identify(string $code, ?string $redirectUri): ExternalIdentity
    {
        try {
            $response = $this->http->request('POST', self::TOKEN_ENDPOINT, [
                'body' => [
                    'code' => $code,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri($redirectUri),
                    'grant_type' => 'authorization_code',
                ],
            ]);

            /** @var array<string, mixed> $token */
            $token = $response->toArray();
        } catch (ExceptionInterface) {
            // Lo que falle al otro lado no se cuenta: es detalle técnico de
            // una integración, y quien pulsó un botón solo necesita saber
            // que lo intente otra vez.
            throw ExternalSignInFailed::providerUnavailable();
        }

        $claims = self::claimsOf($token['id_token'] ?? null);

        $subject = $claims['sub'] ?? null;

        if (!\is_string($subject) || '' === $subject) {
            throw ExternalSignInFailed::providerUnavailable();
        }

        $email = $claims['email'] ?? null;

        return new ExternalIdentity(
            $subject,
            \is_string($email) && '' !== $email ? $email : null,
            true === ($claims['email_verified'] ?? null),
        );
    }

    /**
     * El cliente elige adónde vuelve —una web y una aplicación móvil vuelven
     * a sitios distintos— y Google exige que sea **la misma** en los dos
     * pasos. Si no la manda, la de la configuración.
     */
    private function redirectUri(?string $redirectUri): string
    {
        return null === $redirectUri || '' === $redirectUri ? $this->defaultRedirectUri : $redirectUri;
    }

    /**
     * El cuerpo del `id_token`, que es un JWT.
     *
     * Se lee sin verificar la firma **a conciencia** (ver la nota de la
     * clase): viene de una respuesta directa de Google sobre TLS, no del
     * navegador. Lo que sí se comprueba es que sea lo que dice ser.
     *
     * @return array<string, mixed>
     */
    private static function claimsOf(mixed $idToken): array
    {
        if (!\is_string($idToken)) {
            throw ExternalSignInFailed::providerUnavailable();
        }

        $parts = explode('.', $idToken);

        if (3 !== \count($parts)) {
            throw ExternalSignInFailed::providerUnavailable();
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);

        if (false === $payload) {
            throw ExternalSignInFailed::providerUnavailable();
        }

        try {
            $claims = json_decode($payload, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ExternalSignInFailed::providerUnavailable();
        }

        if (!\is_array($claims)) {
            throw ExternalSignInFailed::providerUnavailable();
        }

        /** @var array<string, mixed> $claims */
        return $claims;
    }
}
