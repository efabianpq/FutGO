# Operaciones — Centro de Privacidad

Procedimientos operativos del módulo de privacidad (Ley 1581/2012).

## Cifrado de datos sensibles

`document` (users/club_players/team_players) y `phone_whatsapp` (users) se cifran con el cast `encrypted` de Laravel (usa `APP_KEY`). Los registros nuevos se cifran solos.

### Cifrar datos preexistentes (una sola vez, tras desplegar)

```bash
# 1. Backup obligatorio antes de cifrar
php artisan backup:run --only-db

# 2. Cifrar en su lugar (idempotente: salta lo ya cifrado)
php artisan futgo:encrypt-sensitive
# en CI/no interactivo:
php artisan futgo:encrypt-sensitive --force
```

El comando también rellena `document_hash` (blind index HMAC) para dedupe/reclamo.

### ⚠️ Rotación de APP_KEY

`APP_KEY` cifra `document`/`phone_whatsapp`, cookies y sesiones. **Rotarla vuelve ilegibles esos campos.** Si hay que rotar:

1. Descifrar a texto plano con la key vieja (script ad-hoc que lea con `Crypt` viejo).
2. `php artisan key:generate`.
3. Re-cifrar con `futgo:encrypt-sensitive`.

Guardar la `APP_KEY` de producción en un gestor de secretos, nunca en el repo.

## Eliminación de cuentas (derecho al olvido)

- El usuario solicita la baja desde `/privacidad/centro/eliminar` (contraseña → código por email → periodo de gracia de `PRIVACY_DELETION_GRACE_DAYS` días, cancelable).
- El comando programado ejecuta las vencidas:

```bash
php artisan futgo:purge-deleted-accounts   # diario 04:30 (routes/console.php)
```

- La ejecución **anonimiza** (no borra la fila): `users` + `club_players` + `team_players` (nombre → "Jugador eliminado", document → null), preservando ids y estadísticas históricas.

## Versionado de políticas

- Editar una política = publicar una versión nueva desde `/admin/legal` (nunca reescribir una publicada).
- Al cambiar la versión vigente de Términos o Privacidad, `EnsureConsentUpToDate` obliga a los usuarios a re-aceptar.
- Seeder inicial: `php artisan db:seed --class=LegalDocumentsSeeder` (v1.0 de los 5 documentos).

## Config relevante (`config/privacy.php`)

| Env | Default | Rol |
|---|---|---|
| `PRIVACY_MIN_AGE` | 14 | Edad mínima de registro |
| `PRIVACY_PARENTAL_CONSENT` | true | Exigir consentimiento del tutor a menores de 18 |
| `PRIVACY_DELETION_GRACE_DAYS` | 30 | Periodo de gracia de eliminación |
| `PRIVACY_CONTACT_EMAIL` | privacidad@futgo.com.co | Responsable del tratamiento |
