# Capas y control de dependencias

> Las reglas están en `AGENTS.md`. Aquí se concreta **cómo se verifican**.

## Dirección de dependencias

```text
Infrastructure  ──▶  Application  ──▶  Domain
```

Nunca al revés. `Domain` no conoce Symfony, Doctrine, HTTP, Messenger ni el sistema de ficheros.

## Reparto de responsabilidades en este proyecto

| Pregunta | Capa | Ejemplo en Lectores Beta |
|---|---|---|
| ¿Puede este lector beta comentar esta obra? | `Domain` | Invariante de `BetaReaderAccess` |
| ¿Cuántos créditos cuesta recibir feedback de un relato de 3.200 palabras? | `Domain` (en `Credits`) | `CreditRule` sobre `TextTier` |
| ¿En qué orden se conceden el acceso y se notifica al autor? | `Application` | Caso de uso `GrantBetaReaderAccess` |
| ¿Cómo se serializa el evento y a qué cola va? | `Infrastructure` | Configuración de Messenger |
| ¿Cómo se extrae el texto de un `.docx` subido? | `Infrastructure` (implementa un puerto) | Adaptador de `DocumentTextExtractor` |
| ¿Cómo se cuentan las palabras de un texto? | `Domain` | Value object del contenido |

Criterio práctico: **si la regla seguiría siendo cierta cambiando de framework y de base de
datos, es `Domain`.**

## Puertos previstos

Interfaces que define `Application` (o `Domain`) y que implementa `Infrastructure`:

| Puerto | Para qué | Contexto |
|---|---|---|
| `PasswordHasher` | Cifrado de contraseñas | `User` |
| `OAuthProvider` | Autenticación con Google y Facebook | `User` |
| `Mailer` | Envío de correo | `Notification` |
| `FileStorage` | Almacenamiento de ficheros subidos y portadas | `Work` |
| `DocumentTextExtractor` | Extraer texto plano de `.doc`, `.docx`, `.pdf`, `.txt` | `Work` |
| `ContentHasher` | Huella criptográfica para el registro de autoría | `Work` |
| `Clock` | Tiempo controlable en tests | `Shared` |
| `EventPublisher` | Publicación de eventos de integración | `Shared` |
| `IdGenerator` | Generación de identificadores | `Shared` |

Ninguna implementación concreta aparece en `Application`. Los tests de casos de uso usan
dobles de estos puertos y no necesitan Symfony ni PostgreSQL.

## Control automático

`AGENTS.md` exige que las fronteras sean verificables, no solo documentadas. Reglas a
configurar (propuesta, pendiente de implementar con el primer código):

| Regla | Herramienta |
|---|---|
| `Domain` no depende de `Symfony\*` ni `Doctrine\*` | Deptrac |
| `Application` no depende de `Infrastructure` | Deptrac |
| `<ContextA>` no depende de `<ContextB>\*` salvo contratos de integración explícitos | Deptrac |
| Ningún contexto depende de `Credits\*` | Deptrac (regla dedicada) |
| Tipado de repositorios, DTO y resultados | PHPStan nivel 7 |

Una violación arquitectónica **no se silencia con un baseline**: se corrige la dirección
de la dependencia.

## Estructura interna de un concepto

```text
src/Work/Manuscript/
    Domain/
        Entity/           Work.php
        ValueObject/      WorkId.php, WorkTitle.php, Visibility.php, WordCount.php
        Repository/       WorkRepository.php          (interfaz)
        Service/
        Event/            WorkCreated.php             (evento de dominio)
        Exception/        WorkNotFound.php
        Enum/             BetaReaderAccessMode.php
    Application/
        Command/          CreateWork.php
        Query/            GetWork.php
        Handler/          CreateWorkHandler.php
        DTO/              WorkView.php
        Port/             DocumentTextExtractor.php
    Infrastructure/
        Controller/       CreateWorkController.php
        Persistence/Doctrine/  DoctrineWorkRepository.php, Work.orm.xml
        Messenger/        WorkCreatedPublisher.php
        Storage/          S3FileStorage.php
```

Se crean **solo las carpetas necesarias**. Una carpeta vacía no aporta estructura.

## Errores frecuentes que esta arquitectura previene

| Atajo tentador | Por qué está prohibido | Qué hacer |
|---|---|---|
| `FeedbackController` llama a `CreditsService::spend()` | Acopla `Feedback` a `Credits` | Publicar `FeedbackSubmitted` y que `Credits` decida |
| `Feedback` inyecta `WorkRepository` para saber el `TextTier` | Dependencia entre contextos | Incluir `TextTier` en el evento, o un contrato de consulta explícito |
| La entidad `Work` lleva atributos de Doctrine | Acopla `Domain` a la persistencia | Mapeo XML en `Infrastructure` |
| El controlador calcula el `TextTier` | Lógica de negocio en `Infrastructure` | Value object en `Domain` |
| `Shared\WorkStatus` usado por tres contextos | Modelo de negocio compartido | Cada contexto define el suyo |
