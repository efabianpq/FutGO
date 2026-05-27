# Deploy en Hostinger Premium — soypachonmundial.online

Guía paso a paso para publicar **Soy Pachón Mundial** en producción.
Asume Hostinger Premium Web Hosting con plan que incluye SSH y Cron Jobs.

---

## 0. Checklist previo (local)

Antes de tocar el servidor, en local:

```powershell
cd C:\laragon\www\soyPachonMundial
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;$env:Path"

# Tests verdes
php artisan test

# Assets de producción compilados
npm run build

# Confirmar que está todo pusheado
git status   # debe decir "nothing to commit, working tree clean"
git log --oneline -3
```

Confirmá que la última línea del log tiene el tag `v1.0.0` o el commit
`release: MVP v1.0`.

---

## 1. Configuración en hPanel de Hostinger

### 1.1 Verificar PHP 8.3

1. Entrá a [hpanel.hostinger.com](https://hpanel.hostinger.com) → tu sitio
   **soypachonmundial.online**.
2. Sidebar izquierdo → **Avanzado → Configuración PHP**.
3. En la pestaña **Versión PHP**, seleccioná **PHP 8.3** y guardá.
4. En la pestaña **Opciones PHP**, asegurate de tener habilitadas estas
   extensiones (todas vienen por defecto en Hostinger pero verificá):
   - `bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`,
     `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

### 1.2 Crear base de datos MySQL

1. Sidebar → **Bases de datos → Bases de datos MySQL**.
2. Click **Crear nueva base de datos MySQL**.
3. Completá:
   - **Nombre BD**: `u123XXXXXX_pachon` (Hostinger prefija con tu user ID).
   - **Usuario**: `u123XXXXXX_pachon_user`.
   - **Contraseña**: generá una fuerte y **guardala en un gestor de contraseñas**.
4. **Anotá los tres valores exactos** — los vas a poner en `.env`:
   ```
   DB_DATABASE=u123XXXXXX_pachon
   DB_USERNAME=u123XXXXXX_pachon_user
   DB_PASSWORD=la-contraseña-fuerte
   ```
5. **Host** queda en `localhost` (en Hostinger la BD corre en el mismo nodo).

### 1.3 Crear cuenta de correo `notificaciones@soypachonmundial.online`

1. Sidebar → **Correos → Cuentas de correo electrónico**.
2. Click **Crear cuenta de correo electrónico**.
3. Completá:
   - **Nombre**: `notificaciones`
   - **Dominio**: `soypachonmundial.online`
   - **Contraseña**: generá una fuerte.
4. Una vez creado, click en **Información de configuración** del email.
5. **Anotá los datos SMTP** (típicamente):
   ```
   MAIL_HOST=smtp.hostinger.com
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USERNAME=notificaciones@soypachonmundial.online
   MAIL_PASSWORD=la-contraseña-del-email
   ```

### 1.4 Activar SSL Let's Encrypt

1. Sidebar → **Avanzado → SSL**.
2. Buscá `soypachonmundial.online` en la lista de dominios.
3. Click **Instalar SSL** (Hostinger configura Let's Encrypt automáticamente).
4. Esperá ~5 minutos. Cuando aparezca **Activo** en verde, listo.
5. En la misma pantalla, habilitá **Forzar HTTPS** (redirige todo HTTP → HTTPS automáticamente).

### 1.5 Habilitar acceso SSH

1. Sidebar → **Avanzado → Acceso SSH**.
2. Click **Habilitar SSH** (algunos planes vienen activo por defecto).
3. Anotá los datos que aparecen:
   ```
   Host SSH: <ip-o-hostname>
   Puerto:   65002      (Hostinger usa 65002, no el 22 estándar)
   Usuario:  u123XXXXXX
   Contraseña: la-misma-del-hpanel (o configurá clave SSH)
   ```

**Recomendado**: añadir tu clave SSH pública para no usar contraseña:
- En local, si no tenés clave: `ssh-keygen -t ed25519 -C "soypachon-deploy"`
- Copiá `~/.ssh/id_ed25519.pub` y pegala en hPanel → SSH → Claves SSH.

---

## 2. Deploy del proyecto vía SSH

### 2.1 Conectarse al servidor

Desde PowerShell:

```powershell
ssh -p 65002 u123XXXXXX@<host-ssh>
```

Reemplazá `u123XXXXXX` con tu usuario y `<host-ssh>` con el host que te dio
Hostinger (puede ser una IP o `<algo>.main-hosting.eu`).

Vas a aterrizar en `/home/u123XXXXXX/`.

### 2.2 Clonar el repositorio

Hostinger normalmente sirve `public_html` para el dominio principal. Vamos
a clonar el proyecto **arriba** de `public_html` (en `/home/u123XXXXXX/`),
y después apuntar el dominio al `/public` de Laravel — esto es la práctica
correcta porque el resto del proyecto (vendor, .env, storage) **no debe ser
servido por web**.

```bash
cd ~
# Si public_html tiene contenido vacío de Hostinger, bórralo:
rm -rf public_html

git clone https://github.com/efabianpq/soypachonmundial.git
cd soypachonmundial

# Crear symlink: public_html → public del proyecto
ln -s /home/u123XXXXXX/soypachonmundial/public /home/u123XXXXXX/public_html
```

Verificá: `ls -la ~/` debería mostrar `public_html -> /home/u123XXXXXX/soypachonmundial/public`.

> **Alternativa si Hostinger no permite borrar `public_html`**: editá en
> hPanel → **Avanzado → Dominios** → `soypachonmundial.online` y cambiá
> **Document Root** a `/home/u123XXXXXX/soypachonmundial/public`.

### 2.3 Instalar dependencias PHP

```bash
cd ~/soypachonmundial

# Asegurate que composer apunta a PHP 8.3 (Hostinger ofrece selector):
which php   # debería ser /usr/bin/php
php -v      # debería decir 8.3.x

composer install --no-dev --optimize-autoloader
```

### 2.4 Configurar `.env` de producción

```bash
cp .env.production.example .env
nano .env
```

Editá los valores marcados como vacíos:

```
APP_KEY=base64:HvdRdztg9DKy0GZ5NIV7hejmz3NRqGB0f5ka35+VxjA=
DB_DATABASE=u123XXXXXX_pachon
DB_USERNAME=u123XXXXXX_pachon_user
DB_PASSWORD=la-contraseña-de-la-BD
MAIL_PASSWORD=la-contraseña-del-email
```

(El `APP_KEY` ya está generado, podés usar ese o correr `php artisan key:generate` para uno nuevo.)

Guardá con **Ctrl+O, Enter, Ctrl+X**.

Permisos:

```bash
chmod 600 .env
chmod -R 775 storage bootstrap/cache
```

### 2.5 Build de assets

Si Hostinger tiene Node.js disponible:

```bash
node --version    # si no responde, leer alternativa abajo
npm ci
npm run build
```

**Si Node NO está disponible** en el servidor, subí los assets ya
compilados desde tu local:

```powershell
# En tu PowerShell local:
scp -P 65002 -r public/build u123XXXXXX@<host-ssh>:/home/u123XXXXXX/soypachonmundial/public/
```

### 2.6 Migraciones + seed inicial

```bash
cd ~/soypachonmundial
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

Verificá que las 7 tablas se crearon:

```bash
php artisan tinker
>>> \DB::select("SHOW TABLES")
>>> exit
```

Deberías ver: `users`, `matches`, `predictions`, `invitation_codes`,
`rankings`, `settings`, `match_notifications` (más las internas de Laravel).

### 2.7 Optimizar caches de producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **IMPORTANTE**: cada vez que cambies el `.env` o las rutas en
> producción, corré `php artisan optimize:clear` y volvé a cachear.

---

## 3. Configurar el Cron Job (scheduler)

En hPanel:

1. Sidebar → **Avanzado → Cron Jobs**.
2. Click **Crear nuevo Cron Job**.
3. **Frecuencia**: elegí "Cada minuto" (o cron expression `* * * * *`).
4. **Comando**:
   ```
   cd /home/u123XXXXXX/soypachonmundial && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   - Reemplazá `u123XXXXXX` con tu usuario real.
   - Si Hostinger usa otra ruta para PHP 8.3, podés verificarla con
     `which php` por SSH y reemplazar `/usr/bin/php`.
5. Click **Crear**.

Esto dispara cada minuto:
- `predictions:lock` (bloqueo de pronósticos 5 min antes del partido)
- `notifications:reminders` (recordatorios 15 min antes del cierre)

Verificá que corre — esperá 2 minutos y mirá:
```bash
tail -50 ~/soypachonmundial/storage/logs/laravel.log
```

---

## 4. Verificar el dominio

1. Abrí `https://soypachonmundial.online` en el navegador.
2. Debería cargar la portada con el video placeholder o el banner verde.
3. Verificá **candado verde** en la barra de direcciones (SSL OK).
4. Probá `http://soypachonmundial.online` — debería **redirigir
   automáticamente** a `https://` (gracias a "Forzar HTTPS" del paso 1.4).

---

## 5. Smoke test en producción

Ejecutá este flujo en `https://soypachonmundial.online`:

1. **Home** → carga con candado verde ✓
2. **Registro** → crea cuenta con tu email real + teléfono real
3. **Activación** → ingresá `SPM-0001` (o cualquier código generado
   en `/admin/codigos` por el admin)
4. **Dashboard** → ves los 104 partidos agrupados por fase ✓
5. **Pronóstico** → ingresá `2-1` en cualquier partido abierto. Esperá
   un segundo, debe aparecer "Guardado ✓" ✓
6. En **otra pestaña / modo incógnito**: login admin
   (`admin@soypachonmundial.com / Admin2026!`) → cambiá la contraseña
   inmediatamente desde Mi Perfil
7. `/admin/resultados` → ingresá el resultado de un partido pasado
8. Volvé a la pestaña del usuario → `/ranking` → debería verse el cálculo
9. **Email** → revisá la bandeja del email que registraste. Deberías
   recibir el recordatorio cuando un partido esté a 15 min del cierre
   (forzá esto cambiando un `match_datetime` en BD a `NOW() + 12 min`)

---

## 6. Checklist final ANTES de anunciar

Imprimí esta lista y marcá uno a uno:

- [ ] **SSL activo y redireccionando HTTP → HTTPS**
      `curl -sI http://soypachonmundial.online | grep -i location` debería
      decir `Location: https://...`
- [ ] **Página de inicio carga correctamente** con video o placeholder
- [ ] **Registro de usuario** funciona end-to-end (email + teléfono)
- [ ] **Códigos de invitación** activan la cuenta y redirigen al dashboard
- [ ] **Vista de pronósticos** muestra los 104 partidos en 7 fases
- [ ] **Bloqueo automático activo**: cron corriendo
      (`tail -20 storage/logs/laravel.log` muestra logs cada minuto)
- [ ] **Panel admin** accesible con `admin@soypachonmundial.com`,
      contraseña cambiada
- [ ] **Ingreso de resultados** calcula puntos correctamente
      (verificá en `/ranking`)
- [ ] **Ranking** redirige a login si no autenticado, a activate si
      no tiene código
- [ ] **Email de notificación** llega al inbox real
      (forzá con `php artisan notifications:reminders` por SSH)
- [ ] **Dominio carga en móvil** correctamente
      (probá en Chrome Android e iPhone Safari)

---

## 7. Comandos útiles post-deploy

```bash
# Re-deploy de código nuevo
cd ~/soypachonmundial
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ver logs en vivo
tail -f ~/soypachonmundial/storage/logs/laravel.log

# Generar más códigos de invitación desde CLI
php artisan tinker
>>> DB::table('invitation_codes')->insert(['code'=>'SPM-NEW1','is_used'=>false,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);

# Backup manual de BD
mysqldump -u u123XXXXXX_pachon_user -p u123XXXXXX_pachon > backup-$(date +%F).sql

# Modo mantenimiento (mientras hacés cambios mayores)
php artisan down --refresh=15 --secret="acceso-secreto"
# salir del modo mantenimiento:
php artisan up
```

---

## 8. Troubleshooting común

**500 Server Error al abrir el dominio**
- Verificá permisos: `chmod -R 775 storage bootstrap/cache`
- Verificá `.env`: `cat .env | head -10` que tenga APP_KEY no vacío
- Mirá log: `tail -100 storage/logs/laravel.log`

**Estilos rotos / página sin CSS**
- Faltan los assets compilados: `ls public/build/` debería tener
  `manifest.json` + `assets/`
- Volvé al paso 2.5

**Emails no llegan**
- Probá envío manual: `php artisan tinker; Mail::raw('test',function($m){$m->to('tu@email.com')->subject('Prueba');});`
- Si tira error de auth: revisá `MAIL_USERNAME` y `MAIL_PASSWORD`
- Si tira timeout: verificá `MAIL_HOST` (algunos hostings usan `smtp.titan.email`)

**Cron no dispara**
- En hPanel → Cron Jobs → ver **Última ejecución**
- Confirmá la ruta absoluta de PHP: `which php` por SSH
- Asegurate que el comando en el cron sí tiene `cd /home/...` correcto

**El sitio sigue mostrando el contenido viejo después de un deploy**
- `php artisan optimize:clear`
- Hard refresh en navegador (Ctrl+Shift+R)
- Si seguís viendo viejo: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
