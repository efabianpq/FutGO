<?php

namespace Database\Seeders;

use App\Models\Support\SupportArticle;
use Illuminate\Database\Seeder;

/**
 * Centro de Soporte · Artículos iniciales de la base de conocimiento.
 *
 * Cubre las dudas más frecuentes de Torneos, FutGO Social, Cuenta y temas
 * técnicos/políticas. Publicados (is_published=true) para que aparezcan en
 * /soporte/ayuda y alimenten al Asistente IA.
 *
 * Idempotente (updateOrCreate por slug). Editar el contenido acá y volver a
 * correr el seeder actualiza el artículo sin duplicarlo.
 */
class SupportArticlesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $article) {
            SupportArticle::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'title'        => $article['title'],
                    'content'      => trim($article['content']),
                    'category'     => $article['category'],
                    'source'       => 'manual',
                    'is_published' => true,
                ]
            );
        }
    }

    private function articles(): array
    {
        return [
            [
                'slug'     => 'como-creo-un-torneo',
                'title'    => '¿Cómo creo un torneo?',
                'category' => 'torneos',
                'content'  => <<<TXT
Cualquier usuario registrado puede crear y organizar un torneo, no hace falta un permiso especial.

1. Entra a "Competir → Mis Torneos" y toca "Crear torneo".
2. Completa nombre, formato (todos contra todos, grupos + eliminatoria, o solo eliminatoria), puntos por victoria/empate y los criterios de desempate.
3. Guarda: el torneo nace en estado "borrador" (draft) y quedas como organizador automáticamente.
4. Cuando esté listo para inscripciones, pásalo a "open" para que los equipos se enrolen.

Recuerda: el fixture recién se puede generar cuando el torneo está en "open" y hay suficientes equipos aprobados.
TXT,
            ],
            [
                'slug'     => 'por-que-no-aparece-el-fixture',
                'title'    => '¿Por qué no aparece el fixture de mi torneo?',
                'category' => 'torneos',
                'content'  => <<<TXT
El fixture (calendario de partidos) no se genera solo: lo genera el organizador y solo cuando se cumplen las condiciones.

Motivos habituales por los que todavía no lo ves:
- El torneo está en "borrador" (draft): aún no fue publicado.
- El torneo está en "open" (inscripciones) pero el organizador todavía no generó el fixture.
- No hay suficientes equipos aprobados. Para "solo eliminatoria" se necesita una potencia de 2 (4, 8, 16…).

Si eres el organizador: entra al panel del torneo → "Fixture" → "Generar". Al generarlo, el torneo pasa a "en juego" (in_progress).
Si eres jugador o capitán: espera a que el organizador lo genere; te va a aparecer en la agenda apenas exista.
TXT,
            ],
            [
                'slug'     => 'no-puedo-cargar-el-resultado-de-un-partido',
                'title'    => 'No puedo cargar el resultado de un partido',
                'category' => 'torneos',
                'content'  => <<<TXT
Los resultados de un torneo solo se pueden cargar cuando el torneo está "en juego" (in_progress) y desde el panel del organizador.

Verifica:
- Que el torneo esté "en juego". Si sigue en "open", primero hay que generar el fixture.
- Que tengas acceso de organización a ese torneo (eres el creador, estás en los administradores del torneo, o eres admin de la plataforma).
- Que el partido exista en el fixture y no esté ya cerrado.

Para cargar: panel del torneo → partido → "Cargar resultado". Ahí también puedes marcar el MVP (si el torneo tiene MVP habilitado), goles, asistencias y tarjetas.
TXT,
            ],
            [
                'slug'     => 'como-funciona-la-credencial-qr',
                'title'    => '¿Cómo funciona la credencial QR?',
                'category' => 'torneos',
                'content'  => <<<TXT
Cada jugador con cuenta tiene una credencial digital con un código QR.

- El QR codifica únicamente tu FutGO ID (FG-XXXXXX) más una firma de seguridad (HMAC). Nunca lleva tu nombre, documento, email ni teléfono.
- Un árbitro o admin valida la credencial escaneando el QR o escribiendo tu FG-XXXXXX a mano.
- Tu FutGO ID es único y permanente: no cambia nunca.
- Si el QR tiene una firma inválida, igual resuelve tu identidad pero muestra un aviso al validador (es intencional para no bloquear en cancha).

Solo los jugadores registrados (con cuenta) tienen credencial. Los jugadores "por verificar" la obtienen al reclamar y vincular su perfil.
TXT,
            ],
            [
                'slug'     => 'como-busco-rival-para-un-amistoso',
                'title'    => '¿Cómo consigo rival para un amistoso?',
                'category' => 'social',
                'content'  => <<<TXT
En FutGO Social publicas una oportunidad y otros clubes te responden.

1. Como capitán, entra a "Jugar → Oportunidades" y publica una de tipo BUSCAR_RIVAL (o usa el "Modo rápido ⚡" si necesitas rival para ya).
2. Elige ciudad, nivel y disponibilidad. El nivel es un filtro obligatorio del sistema de coincidencias.
3. Cuando otro club responde y aceptas, se crea automáticamente un amistoso confirmado y un chat entre los dos capitanes.

Después del partido, cada capitán reporta el marcador. Si ambos coinciden, queda registrado como "jugado"; si no coinciden, queda "en disputa" y lo resuelve un organizador o admin.
TXT,
            ],
            [
                'slug'     => 'que-es-el-score-de-confiabilidad',
                'title'    => '¿Qué es el score de confiabilidad?',
                'category' => 'social',
                'content'  => <<<TXT
La confiabilidad mide qué tan cumplido eres con lo que acuerdas en FutGO Social. Va de 0 a 100.

Baja cuando:
- No te presentas a un amistoso confirmado (no-show): −20.
- Cancelas con menos de 24 horas de anticipación: −10.

Sube cuando respondes rápido y recibes calificaciones positivas.

Importante: si acumulas 2 no-shows en 30 días, tu disponibilidad se pausa automáticamente y no vas a poder publicar oportunidades hasta reactivarla manualmente. El score público solo se muestra si es alto (a partir de 80).
TXT,
            ],
            [
                'slug'     => 'como-funciona-la-mensajeria',
                'title'    => '¿Cómo funcionan los mensajes?',
                'category' => 'social',
                'content'  => <<<TXT
Los chats en FutGO nacen de un acuerdo: no se puede iniciar una conversación con alguien sin que antes haya una oportunidad aceptada.

- Al aceptar una oportunidad se crea el chat automáticamente (para BUSCAR_RIVAL, entre los dos capitanes; para el resto, entre quien publicó y quien respondió).
- El número de WhatsApp solo se comparte si tú lo haces explícitamente desde el chat.
- Puedes reportar un mensaje inapropiado con el botón "Reportar".

Encuentras tus conversaciones en el ícono de "Mensajes" de la barra superior.
TXT,
            ],
            [
                'slug'     => 'como-reclamo-mi-perfil-de-jugador',
                'title'    => '¿Cómo reclamo mi perfil de jugador?',
                'category' => 'cuenta',
                'content'  => <<<TXT
Si un capitán te anotó en un equipo antes de que tuvieras cuenta (con tu nombre y documento), ese historial existe como un perfil "por verificar". Puedes reclamarlo para heredarlo.

1. Regístrate o carga tu documento en "Configurar perfil".
2. Si el documento coincide con un registro "por verificar", te aparece un aviso: "Reclamar mi perfil".
3. Inicias el reclamo desde "Reclamar mi perfil".
4. El capitán del club (o un admin) aprueba el reclamo. Recién ahí se vincula el historial a tu cuenta.

Nunca se vincula un perfil sin aprobación humana, y un mismo registro no admite dos reclamos vivos a la vez.
TXT,
            ],
            [
                'slug'     => 'como-cambio-mi-ciudad-o-nivel',
                'title'    => '¿Cómo cambio mi ciudad o mi nivel de juego?',
                'category' => 'cuenta',
                'content'  => <<<TXT
Tu ciudad y tu nivel declarado los editas tú desde "Configurar perfil".

- El nivel puede ser: recreativo, intermedio, competitivo o élite amateur.
- El nivel no es decorativo: es un filtro clave para que te aparezcan rivales, jugadores y oportunidades acordes.
- La ciudad se usa para mostrarte gente, clubes y canchas cerca tuyo, y para el Feed.

Ten el perfil completo para aprovechar mejor las recomendaciones automáticas.
TXT,
            ],
            [
                'slug'     => 'el-ranking-no-se-actualiza',
                'title'    => 'El ranking no refleja mis últimos partidos',
                'category' => 'tecnico',
                'content'  => <<<TXT
El ranking y el fair play no son en tiempo real: usan una caché que se reconstruye al finalizar torneos y una vez por día de forma automática.

- Si acabas de jugar, puede tardar en reflejarse hasta el próximo recálculo.
- En la página de Ranking vas a ver un "Actualizado hace…" que te indica cuándo se recalculó por última vez.
- Fórmula del ranking: goles×4 + asistencias×2 + MVP×6 + victorias×3 + vallas invictas×2 + partidos×1 + fair play×0.5.

Si pasaron más de 24–48 horas y sigues sin ver tus partidos reflejados, escríbenos y lo revisamos.
TXT,
            ],
            [
                'slug'     => 'como-elimino-mi-cuenta-y-mis-datos',
                'title'    => '¿Cómo elimino mi cuenta y mis datos?',
                'category' => 'politicas',
                'content'  => <<<TXT
Puedes ejercer tu derecho al olvido desde el menú de tu perfil → "Eliminar mi cuenta".

Cómo funciona:
1. Confirmas con tu contraseña y un código que te llega por email.
2. Se inicia un período de gracia de 30 días, durante el cual puedes cancelar la eliminación.
3. Cumplido el plazo, tu cuenta se anonimiza: tu nombre pasa a "Jugador eliminado" y tu documento se borra, pero se preservan los identificadores y estadísticas para no romper el historial de los torneos.

En el Centro de Privacidad también puedes exportar todos tus datos, revisar tus consentimientos y configurar la visibilidad de tu perfil. Marco: Ley 1581/2012 (Colombia).
TXT,
            ],
        ];
    }
}
