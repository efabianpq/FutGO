# Clasificación de datos personales — FutGO

> Fuente de verdad: `App\Support\Privacy\DataClassification`. Marco: Ley 1581/2012 + Decreto 1377/2013 (Colombia).

Cada dato personal se clasifica en uno de tres niveles, y cada nivel tiene reglas de acceso distintas.

## Público (sujeto a `privacy_settings`)

Visible en la ficha pública del jugador **según lo que el usuario habilite** en su Centro de Privacidad.

| Campo | Control del usuario |
|---|---|
| `name` (nombre deportivo) | — (siempre visible si el perfil es público) |
| `futgo_id` | — |
| `avatar_url` (foto) | `show_photo` |
| `city` | `show_city` |
| `play_level` | — |
| estadísticas (goles, asistencias, PJ…) | `show_stats` |
| clubes / equipos | `show_teams` |
| historial de temporadas | `show_history` |
| logros, ranking | — |

Toggles globales de descubrimiento: `public_profile` (si es false, la ficha `/j/{futgo_id}` da 404 salvo dueño/admin), `searchable` (excluye del buscador global), `indexable_by_search_engines`.

## Privado (solo el propio usuario o un admin)

Nunca aparece en fichas públicas, buscador ni resultados de API pública.

- `email`
- `phone_whatsapp` (**cifrado** en BD)
- `birthdate`
- `ip`, sesiones, `remember_token`

## Muy sensible (cifrado, jamás público ni en logs)

- `document` / `document_hash` — **cifrado** con cast `encrypted`; búsquedas por `document_hash` (blind index HMAC).
- validaciones de credencial.

## Reglas transversales

- **Nunca en logs ni auditoría:** password, tokens, document, secretos (`DataClassification::NEVER_LOG`). `AuditLogger` descarta esas claves y enmascara emails.
- **API (futuro):** todo endpoint debe devolver `PlayerResource` (público) o `UserResource` (privado, solo dueño/admin) — nunca el modelo `User` crudo.
- **Buscador global y ficha pública:** `select()` explícito de columnas + respeto de `privacy_settings`.
