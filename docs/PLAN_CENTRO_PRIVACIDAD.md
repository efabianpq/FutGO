# Plan de implementación — Módulo "Centro de Privacidad" (FutGO)

> Fecha: 2026-07-02 · Alcance aprobado: **módulo completo** · DOB + menores: **sí (edad mínima 14)** · Cifrado `document`+`phone`: **sí** · Responsable/contacto: **privacidad@futgo.com.co**

## 0. Marco y principios

**Marco legal rector:** Ley 1581/2012 + Decreto 1377/2013 (Colombia), lineamientos SIC. Diseño extensible a GDPR (por eso el versionado y el registro de consentimiento con IP/UA).

**Principios transversales:**
- **Minimización:** no pedir datos "por si acaso". `document` sigue *opcional*; `birthdate` nuevo pero justificado (edad mínima/menores).
- **Consentimiento explícito y probable:** nada de opt-in automático; toda aceptación queda con `version + accepted_at + ip + user_agent`.
- **Versionado inmutable:** las políticas no se sobrescriben; cambio de versión ⇒ re-aceptación.
- **No-bloqueante en el camino crítico:** el audit-log y export capturan excepciones y nunca tumban un request.
- **Anonimizar, no romper historia:** las stats de torneo sobreviven; el jugador se vuelve "Jugador eliminado".

Namespaces nuevos: `App\Models\Privacy\*`, `App\Services\Privacy\*`, `App\Http\Controllers\Privacy\*`.
Morph map nuevo: `legal_document`, `user_consent`, `audit_log`, `data_request`.

## 1. Clasificación de datos (documento canónico → `docs/CLASIFICACION_DATOS.md` + `App\Support\Privacy\DataClassification`)

| Nivel | Campos | Regla de acceso |
|---|---|---|
| **Público** | name (nombre deportivo), futgo_id, avatar_url, city*, play_level*, stats, club, logros, ranking | Según `privacy_settings` (`*` = toggle del usuario) |
| **Privado** | email, phone_whatsapp, birthdate, IP, sesiones, remember_token | Solo el propio usuario + admin |
| **Muy sensible** | document, validaciones de credencial | Cifrado en BD; nunca en respuestas públicas ni logs |

## 2. Modelo de datos (migraciones nuevas, prefijo `2026_07_02_0000NN`)

**2.1 `legal_documents`**: `id, type (privacy|terms|cookies|content|minors), version, title, content (longText), summary_of_changes (text nullable), published_at (nullable), is_current (bool), created_by_user_id, timestamps`. Único `(type, version)`; índice `(type, is_current)`. Un solo `is_current` por type.

**2.2 `user_consents`**: `id, user_id, document_type, document_version, accepted (bool), accepted_at, ip (45), user_agent (text), source (register|reconsent|settings), created_at`. `marketing` y `parental` se modelan como `document_type` propios.

**2.3 `privacy_settings`** (1:1 con user): `user_id (PK/FK), show_email, show_phone, show_city, show_photo, show_birthdate, show_stats, show_teams, show_history, public_profile, searchable, indexable_by_search_engines, allow_messages (enum nadie|companeros|todos), timestamps`. Defaults conservadores (email/phone/birthdate=false; city/stats/photo/teams=true; allow_messages=companeros). Absorbe `accepts_direct_messages`.

**2.4 `audit_logs`**: `id, user_id (nullable), action, auditable_type, auditable_id (nullable), ip (45), user_agent (text), metadata (json nullable), created_at`. Append-only (sin updated_at). Índices `(user_id, created_at)`, `(action)`.

**2.5 `data_requests`**: `id, user_id, type (export|delete), status (pending|processing|ready|completed|cancelled), verification_code (nullable), verified_at, file_path (nullable), requested_ip, executes_at, completed_at, timestamps`.

**2.6 `users`**: `+ birthdate (date, nullable)`, `+ document_hash (string, nullable, index)`, `+ current_privacy_version`, `+ current_terms_version`, `+ guardian_email (nullable)`, `+ pending_guardian_consent (bool)`. `document` y `phone_whatsapp` → cifrados.

**2.7 `club_players` / `team_players`**: `club_players += document_hash`; cifrar `document`. Nombre denormalizado se anonimiza en el borrado.

## 3. Documentos legales (5) + versionado

Seeder `LegalDocumentsSeeder` con versión `1.0` de: Privacidad, Términos, Cookies, Contenido, Menores. Contacto/responsable: **privacidad@futgo.com.co**. Edad mínima: **14**.
Servido desde BD por `LegalController@show($type)` en `/privacidad`, `/terminos`, `/cookies`, `/contenido`, `/menores`. Vista `legal/documento.blade.php`. Footer de `layouts/landing` linkea los 5.
Admin `Admin\Privacy\LegalDocumentController` (`/admin/legal`): publicar nueva versión ⇒ anterior `is_current=false` + dispara re-consentimiento.

## 4. Consentimiento

- **Registro**: 3 checkboxes (Términos oblig., Privacidad oblig., Comunicaciones opc.) + `birthdate` requerido + edad mínima 14. `ConsentService::recordRegistration` graba filas en `user_consents` + crea `privacy_settings`, en la transacción del registro.
- **Google login**: primera vez o `current_privacy_version` null ⇒ pantalla `/privacidad/aceptar`.
- **Re-consentimiento**: middleware `EnsureConsentUpToDate` compara versión aceptada vs `is_current`; bloquea navegación (whitelist logout + pantalla + assets) mostrando `summary_of_changes`.
- `ConsentService`: `recordRegistration`, `reconsent`, `updateMarketing`, `history`.

## 5. Cifrado de datos sensibles

- Casts `AsEncryptedString` en `User` (`document`, `phone_whatsapp`) y `ClubPlayer` (`document`).
- **Blind index** `document_hash = hash_hmac('sha256', normalizar($doc), APP_KEY)` para dedupe/reclamo. `ProfileClaimService` y dedupe pasan a comparar por hash.
- Comando `futgo:encrypt-sensitive`: exige backup, chunks, idempotente.
- Tests: valor ilegible en BD; reclamo/dedupe por hash OK.

## 6. Menores + edad

- `birthdate` requerido; regla `MinimumAge` (default 14, configurable).
- Menores (<18): `guardian_email` + consentimiento parental pendiente (type `parental`) confirmado por código; hasta confirmar, `pending_guardian_consent=true` con capacidades limitadas.
- Detrás de feature flag `PRIVACY_PARENTAL_CONSENT`.

## 7. Derecho al olvido (robustecer)

Flujo `data_requests(type=delete)`: (1) password + checkbox, (2) código por email, (3) verificación → `executes_at=now()+30d`, (4) gracia con cancelación, (5) ejecución automática `AccountDeletionService::execute()` que anonimiza **users + club_players + team_players** (nombre→'Jugador eliminado', document→null), preservando match_events/player_stats/standings. Reemplaza el `PurgeDeletedAccounts` actual (que solo reporta).

## 8. Jugadores "por verificar" (endurecer)

Form de capitán limitado a nombre/apellido/posición (sin document/tel/email para no-registrados). Sin email/password/QR/notificaciones hasta reclamo. Al reclamar, acepta políticas.

## 9. Centro de Privacidad (UI + rutas, grupo `auth`)

Hub `/privacidad/centro` + entrada en dropdown avatar (`nav.blade.php`).
- `/privacidad/configuracion` — toggles `privacy_settings`.
- `/privacidad/consentimientos` — historial + toggle marketing.
- `/privacidad/exportar` — `data_request(export)` → job arma ZIP/JSON + email link temporal.
- `/privacidad/eliminar` — flujo §7.
- `/privacidad/sesiones` — lista `sessions`, cerrar una/todas.
- `/privacidad/actividad` — `audit_logs` propios paginados.
- `/privacidad/solicitudes` — habeas data (consulta/actualización/corrección).
Servicios: `PrivacyExportService`, `DataRequestService`, `SessionService`.

## 10. Auditoría

`App\Services\Privacy\AuditLogger::log()` no-bloqueante. Engancha: login, logout, cambio password, cambio email, aceptó política, exportó datos, eliminó cuenta, creó/modificó torneo, creó/eliminó equipo, cambió reglamento, cambió config privacidad. Nunca persiste password/token/document/email completo (email enmascarado). Test que verifica ausencia de claves prohibidas.

## 11. Autorización

Policies: `UserPrivacyPolicy`, `LegalDocumentPolicy`, `DataRequestPolicy`. Buscador global y fichas públicas: `select()` por clasificación §1 + respeto de `privacy_settings`. `UserResource`/`PlayerResource` como scaffolding documentado (API futura, no activo hoy).

## 12. Comunicaciones

Emails con "por qué lo recibís" + baja para comerciales; transaccionales marcadas no-comerciales. `MarketingUnsubscribeController` (`/comunicaciones/baja/{token}` firmado). Nunca comercial sin consentimiento `marketing`.

## 13. Tests (`tests/Feature/Privacy/`)

ConsentRegistrationTest, ReconsentMiddlewareTest, LegalDocumentVersioningTest, PrivacySettingsTest, DocumentEncryptionTest, AccountDeletionFlowTest, DataExportTest, MinorRegistrationTest, AuditLogTest. Objetivo: suite verde (hoy 510) + ~40–60 tests.

## 14. Orden de ejecución interno

1. Migraciones + modelos + morph map + seeder legal.
2. Documentos legales + rutas públicas + footer.
3. Cifrado document/phone + document_hash + comando + ajuste ProfileClaimService (más riesgoso, aislado).
4. Consentimiento en registro + birthdate/edad + privacy_settings defaults.
5. Middleware re-consentimiento + admin de versiones.
6. Centro de Privacidad UI (configuración, consentimientos, sesiones, actividad).
7. Derecho al olvido robusto + anonimización profunda + reemplazo PurgeDeletedAccounts.
8. Portabilidad/export + habeas data.
9. Audit logger + enganches.
10. Menores/consentimiento parental (flag).
11. Comunicaciones/baja + policies + API resources scaffold.
12. Docs (CLASIFICACION_DATOS.md, OPERACIONES.md, CLAUDE.md nuevo módulo, HISTORIAL_SESIONES.md).

## 15. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Cifrado rompe reclamo/dedupe | Blind index `document_hash` + tests antes de avanzar; backup pre-comando |
| Re-consent middleware bloquea toda la app | Whitelist (logout, pantalla, assets); feature flag |
| Fricción de consentimiento parental | Flag `PRIVACY_PARENTAL_CONSENT`; lanzar adulto primero |
| Anonimización afecta stats | Solo nombre/document denormalizados; ids intactos; test de integridad |
| Rotar `APP_KEY` vuelve ilegible document/phone | Documentar en OPERACIONES.md re-cifrado previo |

## Parámetros confirmados

- **Responsable del tratamiento / contacto de privacidad:** privacidad@futgo.com.co
- **Edad mínima:** 14 años
- **Cifrado:** document + phone (con blind index para document)
- **Menores:** con DOB + consentimiento parental (feature flag)
