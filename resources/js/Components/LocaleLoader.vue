<script setup>
import { ref, watch, onUnmounted } from 'vue';
import { localeLoading } from '@/composables/useTranslations';

const DURATION = 3000;

const vinylEl = ref(null);
const shineEl = ref(null);
let rafId = null;

function tick() {
    const angle = (Date.now() % DURATION) / DURATION * 360;
    if (vinylEl.value)  vinylEl.value.style.transform  = `rotate(${angle}deg)`;
    if (shineEl.value)  shineEl.value.style.transform  = `rotate(${-angle}deg)`;
    rafId = requestAnimationFrame(tick);
}

watch(localeLoading, (val) => {
    if (val) {
        rafId = requestAnimationFrame(tick);
    } else {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
}, { immediate: true });

onUnmounted(() => { if (rafId) cancelAnimationFrame(rafId); });
</script>

<template>
    <Transition name="locale-loader">
        <div v-if="localeLoading" class="locale-loader">
            <div ref="vinylEl" class="vinyl">
                <div class="vinyl__arcs" />
                <div ref="shineEl" class="vinyl__shine" />
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.locale-loader {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Record body ─────────────────────────────────────────────── */
.vinyl {
    width: 210px;
    height: 210px;
    border-radius: 50%;
    position: relative;
    flex-shrink: 0;
    will-change: transform;

    background:
        /* Pin hole */
        radial-gradient(circle at center,
            #070709 0%, #070709 4%,
            transparent 4.5%
        ),
        /* Label ring accent */
        radial-gradient(circle at center,
            transparent 17.5%,
            rgba(140, 120, 255, 0.3) 18%,
            transparent 19%
        ),
        /* Label */
        radial-gradient(circle at center,
            var(--color-base-1, #ffb2ef) 0%,
            #ffb2ef 8%,
            #4848a8 18%,
            #252560 25%,
            transparent 27%
        ),
        /* Grooves */
        repeating-radial-gradient(circle at center,
            rgba(255, 255, 255, 0.025) 0px,
            rgba(5, 5, 9, 0.97)        1.2px,
            rgba(20, 20, 30, 0.93)     2.4px,
            rgba(5, 5, 9, 0.97)        3.6px
        ),
        #080810;

    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.05),
        0 0 28px  rgba(255, 178, 239, 0.4),
        0 0 70px  rgba(255, 178, 239, 0.2),
        0 0 140px rgba(100, 210, 255, 0.12);
}

/* Inset bevel */
.vinyl::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    box-shadow:
        inset 0 0 0 1.5px rgba(255, 255, 255, 0.06),
        inset 0 0 20px rgba(0, 0, 0, 0.7);
    pointer-events: none;
}

/* ── Iridescent arcs — rotate WITH the record ────────────────── */
/* Three asymmetric arcs at 0°, ~130°, ~220° of unequal widths.  */
/* Masked to the groove annulus (28% – 95% radius).               */
.vinyl__arcs {
    position: absolute;
    inset: 0;
    border-radius: 50%;

    background: conic-gradient(
        from 0deg,
        transparent                          0deg,
        transparent                          12deg,
        rgba(255, 178, 239, 0.0)             18deg,
        rgba(255, 178, 239, 0.22)            38deg,   /* wide purple arc */
        rgba(100, 210, 255, 0.16)            56deg,
        rgba(255, 178, 239, 0.06)            70deg,
        transparent                          82deg,
        transparent                          130deg,
        rgba(100, 210, 255, 0.0)             136deg,
        rgba(100, 210, 255, 0.18)            150deg,  /* narrow cyan arc  */
        rgba(100, 210, 255, 0.0)             164deg,
        transparent                          170deg,
        transparent                          218deg,
        rgba(255, 178, 239, 0.0)             224deg,
        rgba(255, 178, 239, 0.26)            248deg,  /* medium arc       */
        rgba(100, 210, 255, 0.18)            270deg,
        rgba(255, 178, 239, 0.04)            288deg,
        transparent                          300deg,
        transparent                          360deg
    );

    /* Mask to groove annulus only */
    mask: radial-gradient(circle at center,
        transparent 0%, transparent 27%,
        black 28%,       black 95%,
        transparent 96%
    );
    -webkit-mask: radial-gradient(circle at center,
        transparent 0%, transparent 27%,
        black 28%,       black 95%,
        transparent 96%
    );
}

/* ── Specular sheen — counter-rotates → stays fixed in space ─── */
.vinyl__shine {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    will-change: transform;

    background: conic-gradient(
        from 10deg at 38% 32%,
        transparent                   0deg,
        rgba(255, 255, 255, 0.07)    22deg,
        rgba(255, 255, 255, 0.02)    46deg,
        transparent                  62deg,
        transparent                  360deg
    );
}


.locale-loader-enter-active { transition: opacity 0.2s ease; }
.locale-loader-leave-active { transition: opacity 0.35s ease; }
.locale-loader-enter-from,
.locale-loader-leave-to     { opacity: 0; }
</style>
