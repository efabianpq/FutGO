{{--
    CTA final · "Únete gratis" + campo ciudad OPCIONAL.
    El form hace GET a la ruta de registro pasando ?ciudad= (prefill/segmentación
    futura); no requiere endpoint de lead nuevo. CTA único, sin segundo CTA que compita.
--}}
<section class="py-[88px] text-center relative" id="crear"
         style="background:radial-gradient(60% 80% at 50% -10%, var(--color-green-tint), transparent 60%)">
    <div class="max-w-[1120px] mx-auto px-6">
        <span class="font-mono text-[12px] uppercase inline-flex items-center justify-center gap-2 mb-3.5 text-green"
              style="letter-spacing:.14em">Únete gratis</span>

        <h2 class="font-x font-extrabold text-text m-0 mb-3.5"
            style="font-stretch:112%; font-size:clamp(34px,5vw,56px); letter-spacing:-.025em; line-height:1.02">
            Tu próxima temporada empieza hoy
        </h2>
        <p class="text-[18px] text-muted mx-auto mb-[30px]" style="max-width:44ch">
            Creá tu cuenta y armá tu primer torneo, tu perfil o tu próximo amistoso. Gratis, en minutos.
        </p>

        <form action="{{ route('register') }}" method="GET"
              class="flex gap-2.5 justify-center flex-wrap max-w-[520px] mx-auto"
              x-data="{ ciudad: '' }">
            <label for="cta-ciudad" class="sr-only">Tu ciudad (opcional)</label>
            <input id="cta-ciudad" name="ciudad" type="text" x-model="ciudad" maxlength="80"
                   class="input" style="height:56px; max-width:260px"
                   placeholder="Tu ciudad (opcional)" autocomplete="address-level2">
            <button type="submit" class="btn btn-primary" style="height:56px; padding:0 30px; font-size:17px; border-radius:12px; box-shadow:var(--glow)">
                Crear cuenta gratis
            </button>
        </form>

        <div class="font-mono text-[12.5px] text-subtle mt-4">Sin tarjeta · Sin aprobaciones · Empezás a jugar hoy</div>
    </div>
</section>
