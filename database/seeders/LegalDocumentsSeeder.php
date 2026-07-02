<?php

namespace Database\Seeders;

use App\Models\Privacy\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Centro de Privacidad · Versión 1.0 de los 5 documentos legales obligatorios.
 *
 * Marco: Ley 1581/2012 + Decreto 1377/2013 (Colombia). Responsable del
 * tratamiento y contacto: privacidad@futgo.com.co. Edad mínima: 14 años.
 *
 * Idempotente (updateOrCreate por type+version). Publica cada tipo como versión
 * vigente (is_current=true). Cambios futuros deben crear una versión nueva
 * (1.1, 2.0) — nunca editar el contenido de una versión ya publicada.
 */
class LegalDocumentsSeeder extends Seeder
{
    public const CONTACT_EMAIL = 'privacidad@futgo.com.co';
    public const RESPONSIBLE   = 'FutGO';
    public const MIN_AGE       = 14;

    public function run(): void
    {
        foreach ($this->documents() as $doc) {
            LegalDocument::updateOrCreate(
                ['type' => $doc['type'], 'version' => '1.0'],
                [
                    'title'        => $doc['title'],
                    'content'      => trim($doc['content']),
                    'published_at' => now(),
                    'is_current'   => true,
                ]
            );

            // Asegura una sola versión vigente por tipo.
            LegalDocument::ofType($doc['type'])
                ->where('version', '!=', '1.0')
                ->update(['is_current' => false]);
        }
    }

    private function documents(): array
    {
        $email = self::CONTACT_EMAIL;
        $resp  = self::RESPONSIBLE;
        $age   = self::MIN_AGE;

        return [
            [
                'type'  => 'privacy',
                'title' => 'Política de privacidad',
                'content' => <<<MD
## Responsable del tratamiento

{$resp} es responsable del tratamiento de tus datos personales. Para cualquier consulta escríbenos a **{$email}**.

## Qué información recopilamos

- **De registro:** nombre, correo electrónico, teléfono/WhatsApp y fecha de nacimiento.
- **Opcional:** número de documento (para verificar identidad deportiva y evitar jugadores duplicados) y foto de perfil.
- **De actividad deportiva:** ciudad, nivel de juego declarado, estadísticas (goles, asistencias, partidos, tarjetas) e historial de torneos y amistosos.
- **Técnica:** dirección IP, tipo de dispositivo y navegador, para seguridad y auditoría.

## Por qué la recopilamos (finalidades)

- Gestión de torneos, fixtures, resultados y estadísticas.
- Identificación del jugador mediante credencial con código QR.
- Conexión con otros equipos y jugadores dentro de FutGO Social.
- Recordatorios de partidos y notificaciones del servicio.
- Seguridad de la cuenta y cumplimiento legal.

## Cuánto tiempo la almacenamos

Conservamos tus datos mientras tu cuenta esté activa. Si solicitas la eliminación, tus datos personales se anonimizan de inmediato y se completa el borrado a los 30 días. Las estadísticas deportivas se conservan de forma anonimizada para no afectar la integridad de los resultados de otros participantes.

## Con quién la compartimos

- Tu nombre, foto, ciudad, nivel y estadísticas son públicos en tu ficha, según tu configuración de privacidad.
- Tu documento, correo y teléfono **nunca se muestran públicamente** ni se venden a terceros.
- Solo usamos proveedores de infraestructura (hosting y almacenamiento de imágenes), que no pueden usar tus datos con otro fin.

## Cookies

Usamos únicamente cookies estrictamente necesarias para el funcionamiento (sesión, autenticación, seguridad). Ver la [Política de cookies](/cookies).

## Seguridad

Aplicamos HTTPS, cifrado de datos sensibles (como el documento), control de acceso por roles y registro de auditoría de las acciones importantes.

## Transferencia internacional

Algunos proveedores de infraestructura pueden almacenar datos fuera de Colombia. En esos casos exigimos garantías de seguridad equivalentes a las de la Ley 1581 de 2012.

## Tus derechos (habeas data)

Puedes **conocer, actualizar, rectificar y suprimir** tus datos, y **revocar** el consentimiento en cualquier momento desde tu Centro de Privacidad o escribiendo a {$email}.

## Eliminación de datos

Desde tu Centro de Privacidad puedes solicitar la eliminación de tu cuenta y descargar una copia de tus datos.

## Cambios en esta política

Si cambiamos esta política publicaremos una versión nueva y te pediremos que la aceptes nuevamente antes de seguir usando FutGO.
MD,
            ],
            [
                'type'  => 'terms',
                'title' => 'Términos y condiciones',
                'content' => <<<MD
## Aceptación

Al crear una cuenta en FutGO aceptas estos Términos y Condiciones y la [Política de privacidad](/privacidad).

## Reglas de uso

FutGO es una plataforma para organizar y jugar torneos de fútbol amateur y conectar con la comunidad deportiva. Te comprometes a usarla de buena fe y a brindar información veraz.

## Responsabilidades del usuario

- Eres responsable de la actividad de tu cuenta y de mantener segura tu contraseña.
- Eres responsable del contenido que publicas (nombres de equipos, mensajes, fotos).

## Prohibiciones

No está permitido usar FutGO para actividades ilegales, suplantar a otras personas, acosar a otros usuarios, ni vulnerar la [Política de contenido](/contenido).

## Propiedad intelectual

La marca, el diseño y el software de FutGO son de su titular. El contenido que subes sigue siendo tuyo, pero nos otorgas una licencia para mostrarlo dentro de la plataforma con el fin de prestar el servicio.

## Contenido generado por usuarios

Eres el único responsable del contenido que publicas. Podemos ocultar o eliminar contenido que infrinja estos términos, sin previo aviso cuando sea necesario.

## Suspensión de cuentas

Podemos suspender o cerrar cuentas que incumplan estos términos, la Política de contenido o la ley aplicable.

## Limitación de responsabilidad

FutGO se ofrece "tal cual". No garantizamos disponibilidad ininterrumpida ni nos responsabilizamos por acuerdos entre usuarios (amistosos, alquiler de canchas, etc.), que se coordinan bajo su propio riesgo.

## Contacto

Para consultas sobre estos términos: {$email}.
MD,
            ],
            [
                'type'  => 'cookies',
                'title' => 'Política de cookies',
                'content' => <<<MD
## Qué son las cookies

Son pequeños archivos que el sitio guarda en tu dispositivo para funcionar correctamente.

## Cookies que usamos hoy (estrictamente necesarias)

- **Sesión** (`futgo_session`): mantiene tu sesión iniciada.
- **Autenticación:** recuerda tu inicio de sesión ("recordarme").
- **CSRF** (`XSRF-TOKEN`): protege los formularios contra ataques.
- **Preferencias:** recuerda tu tema (claro/oscuro).

Estas cookies son imprescindibles y no requieren consentimiento adicional.

## Cookies que NO usamos hoy

Actualmente **no** utilizamos cookies de analítica, publicidad ni seguimiento (por ejemplo Google Analytics, Facebook Pixel o Hotjar).

## A futuro

Si en el futuro incorporamos herramientas de analítica o marketing, actualizaremos esta política y te pediremos consentimiento antes de activarlas.

## Contacto

Consultas sobre cookies: {$email}.
MD,
            ],
            [
                'type'  => 'content',
                'title' => 'Política de contenido',
                'content' => <<<MD
## Objetivo

FutGO es una comunidad deportiva sana. Esta política define qué contenido no se permite.

## Contenido prohibido

No se permite publicar ni difundir:

- Insultos, acoso o lenguaje ofensivo hacia otras personas.
- Racismo, xenofobia, discriminación o incitación al odio.
- Violencia o amenazas.
- Apuestas ilegales.
- Pornografía o contenido sexual.
- Información falsa o engañosa.
- Suplantación de identidad de personas, clubes u organizaciones.

## Cómo lo aplicamos

Aplicamos filtros automáticos de lenguaje en descripciones y mensajes, y cualquier usuario puede **reportar** contenido con el botón correspondiente. El contenido reportado se revisa y puede ocultarse.

## Consecuencias

El incumplimiento puede llevar a ocultar el contenido, suspender o cerrar la cuenta, según la gravedad.

## Contacto

Para reportes o consultas: {$email}.
MD,
            ],
            [
                'type'  => 'minors',
                'title' => 'Política para menores',
                'content' => <<<MD
## Edad mínima

Para crear una cuenta en FutGO debes tener al menos **{$age} años**.

## Menores de edad

En el fútbol amateur participan niñas y niños. Si eres menor de 18 años, para registrarte necesitas el **consentimiento de tu padre, madre o representante legal**, que confirmará tu registro mediante un enlace enviado a su correo.

## Tratamiento de datos de menores

Tratamos los datos de menores con especial cuidado y solo para las finalidades deportivas descritas en la [Política de privacidad](/privacidad). No mostramos públicamente datos de contacto de menores.

## Jugadores registrados por un capitán

Un capitán puede anotar a un jugador informal (sin cuenta) usando solo su nombre, apellido y posición. No se solicitan datos de contacto de menores hasta que la persona (o su representante) crea una cuenta y reclama el perfil.

## Derechos del representante legal

El padre, madre o representante legal puede solicitar en cualquier momento el acceso, la corrección o la eliminación de los datos del menor escribiendo a {$email}.
MD,
            ],
        ];
    }
}
