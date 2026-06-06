# Sesión G — Guía de prueba en el navegador

**Recordatorios de partidos próximos** (email), **patrocinadores del torneo** (placeholder de monetización) y cierre con la guía integral.

App local: **http://futgo.test:8080**. Email en local = driver `log` → se escribe en `storage/logs/laravel.log`.

> PATH de Laragon antes de `php artisan`:
> ```powershell
> $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
> ```

---

## 0. Datos previos

```php
// php artisan tinker — partido próximo con un jugador convocado
use App\Models\User; use App\Models\Torneos\{Tournament,TournamentPhase,Team,TeamPlayer,TournamentMatch,MatchCallUp};
$admin = User::where('email','admin.torneo@demo.futgo.com')->first();
$t = Tournament::create(['name'=>'Liga G','slug'=>'liga-g','sport'=>'futbol','status'=>'in_progress','format'=>'round_robin','visibility'=>'public','groups_count'=>1,'teams_per_group'=>2,'classifies_per_group'=>1,'max_teams'=>2,'points_win'=>3,'points_draw'=>1,'points_loss'=>0,'created_by_user_id'=>$admin->id]);
$t->tournamentAdmins()->create(['user_id'=>$admin->id]);
$ph = TournamentPhase::create(['tournament_id'=>$t->id,'name'=>'Grupos','type'=>'groups','order'=>1,'is_active'=>true,'status'=>'active']);
$h = Team::create(['tournament_id'=>$t->id,'captain_user_id'=>$admin->id,'name'=>'Local','status'=>'approved']);
$a = Team::create(['tournament_id'=>$t->id,'captain_user_id'=>$admin->id,'name'=>'Visita','status'=>'approved']);
$jug = User::factory()->create(['is_active'=>true,'modules'=>'torneos','notifications_enabled'=>true,'name'=>'Convocado Uno']);
$tp = TeamPlayer::create(['team_id'=>$h->id,'user_id'=>$jug->id,'status'=>'active']);
$m = TournamentMatch::create(['phase_id'=>$ph->id,'home_team_id'=>$h->id,'away_team_id'=>$a->id,'status'=>'scheduled','scheduled_at'=>now()->addHours(5),'match_number'=>1,'venue'=>'Cancha Central']);
MatchCallUp::create(['match_id'=>$m->id,'team_player_id'=>$tp->id,'team_id'=>$h->id,'status'=>'convocado']);
echo $t->slug;
```

---

## Escenario 1 — Ejecutar el comando y ver el email en el log

```powershell
php artisan torneos:match-reminders
```
- Salida: "Recordatorios enviados: 1".
- Abrí `storage/logs/laravel.log` → al final hay un mail con asunto **"⚽ FutGO — Tenés un partido próximo"** dirigido al convocado, con el partido, fecha y cancha.

---

## Escenario 2 — Re-ejecutar: no se duplica (idempotencia)

```powershell
php artisan torneos:match-reminders
```
- Salida: "Recordatorios enviados: 0" + "Duplicados saltados: 1".
- No se agrega otro email al log. La fila de control en `tournament_match_notifications` evita el reenvío.

---

## Escenario 3 — Usuario con notificaciones desactivadas

```php
// php artisan tinker
App\Models\User::where('name','Convocado Uno')->update(['notifications_enabled'=>false]);
// borrar el control para reintentar el envío:
App\Models\Torneos\TournamentMatchNotification::truncate();
```
```powershell
php artisan torneos:match-reminders
```
- Salida: "Recordatorios enviados: 0". El jugador con `notifications_enabled=false` no recibe nada.

---

## Escenario 4 — Asociar un patrocinador y verlo en el portal público

1. Login como **admin del torneo** (`admin.torneo@demo.futgo.com`).
2. Gestión Torneos → el torneo → tarjeta **Exportar y compartir** → **🤝 Patrocinadores**
   (o `/admin/torneos/{id}/patrocinadores`).
3. Agregá un patrocinador: **Nombre** (obligatorio), **Logo (URL)** y **Enlace (URL)** opcionales → **Agregar**.
4. Abrí el **portal público** del torneo (`/t/liga-g`) → al pie aparece **"Patrocinan este torneo"** con el logo/nombre enlazado.

---

## Configuración del cron en Hostinger

Un **único** cron job (cada minuto) dispara el scheduler de Laravel; Laravel decide qué
comando corre en cada minuto (polla + torneos conviven en `routes/console.php`):

```
* * * * * /bin/sh /home/USUARIO/domains/futgo.com/public_html/scheduler.sh
```

`scheduler.sh` (en la raíz del proyecto) ejecuta `php artisan schedule:run`. Los recordatorios
de torneos corren `hourly` y escriben en `storage/logs/torneos-reminders.log`.

---

## Queries SQL de verificación

```sql
-- Control de recordatorios enviados (idempotencia)
SELECT user_id, match_id, type, sent_at FROM tournament_match_notifications ORDER BY id DESC;

-- Patrocinadores de un torneo
SELECT t.name torneo, s.name patrocinador, s.link_url, s.is_active, s.sort_order
FROM tournament_sponsors s JOIN tournaments t ON t.id = s.tournament_id
ORDER BY s.sort_order;
```

---

## Errores conocidos / limitaciones

1. La ventana del recordatorio es configurable (`--minutes`, default 1440 = 24h). En el scheduler corre `hourly`; con la idempotencia, cada jugador recibe un único recordatorio por partido.
2. Solo se notifica a jugadores **convocados/confirmados** y **registrados** (con cuenta); los "por verificar" no reciben email.
3. Patrocinadores: solo el **espacio** y gestión básica (nombre/logo/enlace). Sin lógica de cobro ni facturación (por diseño).
4. En local el email va al **log** (driver `log`); en producción usa SMTP de Hostinger.
