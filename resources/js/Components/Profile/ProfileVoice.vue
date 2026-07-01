<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import WaveSurfer from 'wavesurfer.js';
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    voiceUrl: { type: String, default: null },
    isOwner:  { type: Boolean, default: false },
});

// ── WaveSurfer ──────────────────────────────────────────────
const waveformEl  = ref(null);
const playing     = ref(false);
const currentSec  = ref(0);
const totalSec    = ref(0);
const wsReady     = ref(false);
let   ws          = null;

function fmt(s) {
    if (!s || !isFinite(s) || isNaN(s)) return '0:00';
    return `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
}

function initWaveSurfer() {
    if (!waveformEl.value || !props.voiceUrl) return;
    if (ws) { ws.destroy(); ws = null; }

    wsReady.value    = false;
    playing.value    = false;
    currentSec.value = 0;
    totalSec.value   = 0;

    ws = WaveSurfer.create({
        container:     waveformEl.value,
        url:           props.voiceUrl,
        waveColor:     'rgba(255,255,255,0.22)',
        progressColor: getComputedStyle(document.documentElement).getPropertyValue('--color-base-1').trim() || '#ffb2ef',
        cursorColor:   'transparent',
        barWidth:      2,
        barGap:        2,
        barRadius:     1,
        height:        28,
        normalize:     true,
        interact:      true,
        backend:       'WebAudio',
    });

    ws.on('ready',      (dur) => { totalSec.value = dur; wsReady.value = true; });
    ws.on('timeupdate', (sec) => { currentSec.value = sec; });
    ws.on('play',       ()    => { playing.value = true; });
    ws.on('pause',      ()    => { playing.value = false; });
    ws.on('finish',     ()    => {
        playing.value    = false;
        currentSec.value = 0;
        ws?.seekTo(0);
    });
}

function togglePlay() {
    if (!ws || !wsReady.value) return;
    ws.playPause();
}

// Главный фикс: следим за template ref напрямую.
// flush:'post' — элемент полностью в DOM когда callback выполняется.
watch(waveformEl, (el) => {
    if (el && props.voiceUrl) {
        initWaveSurfer();
    } else if (!el && ws) {
        ws.destroy();
        ws = null;
        wsReady.value = false;
    }
}, { flush: 'post' });

// Перезапуск при смене URL (перезапись голосового)
watch(() => props.voiceUrl, (url, oldUrl) => {
    if (!url) {
        if (ws) { ws.destroy(); ws = null; wsReady.value = false; playing.value = false; }
    } else if (url !== oldUrl && waveformEl.value) {
        initWaveSurfer();
    }
});

onMounted(() => {
    if (props.voiceUrl && waveformEl.value) initWaveSurfer();
});

onUnmounted(() => {
    ws?.destroy();
    ws = null;
    clearInterval(timer);
});

// ── Recording state (объявлено до isDiskSpinning) ───────────
const recording = ref(false);
const uploading = ref(false);

// ── Диск: вращение + плавный возврат ───────────────────────
const diskEl        = ref(null);
const returnStyle   = ref({});
let   returnTimeout = null;

// Диск крутится при воспроизведении, записи и загрузке
const isDiskSpinning = computed(() => playing.value || recording.value || uploading.value);

// Читаем текущий угол поворота из CSS-матрицы трансформации
function getCurrentAngle() {
    if (!diskEl.value) return 0;
    const t = window.getComputedStyle(diskEl.value).transform;
    if (!t || t === 'none') return 0;
    const m = t.split('(')[1]?.split(')')[0]?.split(',');
    if (!m) return 0;
    const angle = Math.atan2(parseFloat(m[1]), parseFloat(m[0])) * (180 / Math.PI);
    return angle < 0 ? angle + 360 : angle;
}

watch(isDiskSpinning, (spinning) => {
    clearTimeout(returnTimeout);

    if (spinning) {
        // Запуск — убираем стили возврата, отдаём управление CSS-анимации
        returnStyle.value = {};
    } else {
        // Остановка — читаем текущий угол ДО обновления DOM (default flush:'pre')
        const angle = getCurrentAngle();

        // Выбираем кратчайший путь: вперёд до 360 или назад до 0
        const toForward = 360 - angle;
        const toBack    = angle;
        const targetAngle  = toForward <= toBack ? 360 : 0;
        const distanceDeg  = Math.min(toForward, toBack);
        // Длительность пропорциональна расстоянию, минимум 0.2s, максимум 0.75s
        const duration = (0.2 + (distanceDeg / 180) * 0.55).toFixed(2);

        // Шаг 1: фиксируем диск на текущем угле (убираем CSS-анимацию)
        returnStyle.value = {
            animation:  'none',
            transform:  `rotate(${angle}deg)`,
            transition: 'none',
        };

        // Шаг 2: два rAF гарантируют что браузер закоммитил шаг 1 перед стартом transition
        requestAnimationFrame(() => requestAnimationFrame(() => {
            returnStyle.value = {
                animation:  'none',
                transform:  `rotate(${targetAngle}deg)`,
                transition: `transform ${duration}s cubic-bezier(0.4, 0, 0.2, 1)`,
            };
            returnTimeout = setTimeout(() => {
                returnStyle.value = {};
            }, parseFloat(duration) * 1000 + 50);
        }));
    }
});

// ── Recording ───────────────────────────────────────────────
const MAX_SEC   = 27;
const countdown = ref(MAX_SEC);
const chunks    = ref([]);
let   mr        = null;
let   timer     = null;

const mime = computed(() => {
    if (typeof MediaRecorder === 'undefined') return 'audio/webm';
    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) return 'audio/webm;codecs=opus';
    if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus'))  return 'audio/ogg;codecs=opus';
    return 'audio/mp4';
});

async function startRecording() {
    if (!navigator.mediaDevices?.getUserMedia) {
        alert(__('profile.voice.err.mic_unavailable'));
        return;
    }

    // Check if permission is already permanently denied
    if (navigator.permissions) {
        try {
            const status = await navigator.permissions.query({ name: 'microphone' });
            if (status.state === 'denied') {
                alert(__('profile.voice.err.mic_denied'));
                return;
            }
        } catch {
            // permissions API not supported — proceed anyway
        }
    }

    try {
        if (ws && playing.value) ws.pause();
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks.value = [];
        mr = new MediaRecorder(stream, { mimeType: mime.value });
        mr.ondataavailable = e => { if (e.data.size > 0) chunks.value.push(e.data); };
        mr.onstop = upload;
        mr.start();
        recording.value = true;
        countdown.value  = MAX_SEC;
        timer = setInterval(() => { if (--countdown.value <= 0) stopRecording(); }, 1000);
    } catch (err) {
        console.error('[Voice] getUserMedia error:', err?.name, err?.message);
        if (err?.name === 'NotAllowedError' || err?.name === 'PermissionDeniedError') {
            alert(__('profile.voice.err.mic_denied'));
        } else if (err?.name === 'NotFoundError' || err?.name === 'DevicesNotFoundError') {
            alert(__('profile.voice.err.mic_unavailable'));
        } else {
            alert(__('profile.voice.err.mic'));
        }
    }
}

function stopRecording() {
    if (!mr) return;
    clearInterval(timer);
    mr.stop();
    mr.stream.getTracks().forEach(t => t.stop());
    recording.value = false;
}

function upload() {
    const ext  = mime.value.includes('mp4') ? 'mp4' : mime.value.includes('ogg') ? 'ogg' : 'webm';
    const blob = new Blob(chunks.value, { type: mime.value });
    const fd   = new FormData();
    fd.append('voice', new File([blob], `voice.${ext}`, { type: mime.value }));
    uploading.value = true;
    router.post(route('profile.update.voice'), fd, {
        preserveState: true, preserveScroll: true, forceFormData: true,
        onFinish: () => { uploading.value = false; },
    });
}

function deleteVoice() {
    if (!confirm(__('profile.voice.confirm.del'))) return;
    if (ws) { ws.destroy(); ws = null; wsReady.value = false; playing.value = false; }
    router.delete(route('profile.delete.voice'), { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <div class="voice-wrap">
        <!-- Диск — вращается при воспроизведении и при записи, плавно возвращается в 0 -->
        <div class="disk-wrap">
            <img
                ref="diskEl"
                :src="'/profile_disk.png'"
                class="disk"
                :class="{ spinning: isDiskSpinning && !returnStyle.transform }"
                :style="returnStyle"
                alt=""
            />
        </div>

        <!-- Плеер -->
        <div v-if="voiceUrl" class="player">
            <button
                class="play-btn"
                :disabled="!wsReady"
                @click="togglePlay"
                :title="playing ? __('profile.voice.pause') : __('profile.voice.play')"
            >
                <svg v-if="!wsReady" class="icon-loading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="9" stroke-opacity="0.2"/>
                    <path d="M12 3a9 9 0 0 1 9 9" stroke-linecap="round"/>
                </svg>
                <svg v-else-if="!playing" class="icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 5.14v14l11-7-11-7z"/>
                </svg>
                <svg v-else class="icon" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="6" y="5" width="4" height="14" rx="1"/>
                    <rect x="14" y="5" width="4" height="14" rx="1"/>
                </svg>
            </button>

            <div class="player-track">
                <div ref="waveformEl" class="waveform" />
                <div class="times">{{ fmt(currentSec) }} / {{ fmt(totalSec) }}</div>
            </div>

            <button v-if="isOwner" class="del-btn" @click="deleteVoice" :title="__('common.delete')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <!-- Запись идёт -->
        <div v-else-if="recording" class="recording">
            <span class="rec-dot" />
            <span class="rec-timer">{{ countdown }}{{ __('common.sec') }}</span>
            <button class="stop-btn" @click="stopRecording">{{ __('profile.voice.stop') }}</button>
        </div>

        <!-- Загрузка после записи -->
        <div v-else-if="uploading" class="uploading">
            <svg class="uploading__spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="9" stroke-width="2" stroke-opacity="0.18" />
                <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span class="uploading__label">{{ __('profile.voice.uploading') }}</span>
        </div>

        <!-- Гость: аудио нет -->
        <div v-else-if="!isOwner" class="no-audio">{{ __('profile.voice.empty') }}</div>

        <!-- Кнопка записи -->
        <button v-else-if="isOwner" class="rec-btn" @click="startRecording">
            <svg class="rec-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="2" width="6" height="12" rx="3"/>
                <path d="M5 10a7 7 0 0 0 14 0"/>
                <line x1="12" y1="19" x2="12" y2="22"/>
                <line x1="8" y1="22" x2="16" y2="22"/>
            </svg>
            <span>{{ __('profile.voice.record') }}</span>
        </button>
    </div>
</template>

<style scoped>
.voice-wrap {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
    width: 100%;
}

/* ── Диск ─────────────────────────────────────────────────── */
.disk-wrap {
    display: flex;
    justify-content: center;
}
.disk {
    width: 190px;
    height: 190px;
    display: block;
    will-change: transform;
}
@keyframes diskSpin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
.disk.spinning {
    animation: diskSpin 5s linear infinite;
}

/* ── Плеер ────────────────────────────────────────────────── */
.player {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.6rem;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: var(--profile-border-radius, 8px);
    background: rgba(255,255,255,0.03);
    box-sizing: border-box;
}

.play-btn {
    width: 28px; height: 28px;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: var(--profile-border-radius, 8px);
    background: transparent;
    color: rgba(255,255,255,0.75);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}
.play-btn:hover:not(:disabled) {
    border-color: var(--color-base-1);
    color: var(--color-base-1);
}
.play-btn:disabled { opacity: 0.4; cursor: default; }

.icon { width: 12px; height: 12px; }
@keyframes spin360 { to { transform: rotate(360deg); } }
.icon-loading { width: 12px; height: 12px; animation: spin360 0.8s linear infinite; }

.player-track {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.waveform { width: 100%; cursor: pointer; }
.times {
    font-size: 0.7rem;
    font-variant-numeric: tabular-nums;
    color: rgba(255,255,255,0.28);
    line-height: 1;
}

.del-btn {
    width: 26px; height: 26px;
    flex-shrink: 0;
    background: none; border: none; padding: 0;
    color: rgba(255,255,255,0.25);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: color 0.15s;
}
.del-btn svg { width: 16px; height: 16px; stroke-width: 2.5; }
.del-btn:hover { color: var(--color-base-1); }

/* ── Запись ───────────────────────────────────────────────── */
.recording {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.6rem;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 65%);
    border-radius: var(--profile-border-radius, 8px);
    box-sizing: border-box;
}
@keyframes recBlink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.2; }
}
.rec-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--color-base-1);
    flex-shrink: 0;
    animation: recBlink 1s ease-in-out infinite;
}
.rec-timer {
    flex: 1;
    font-size: 0.88rem;
    font-variant-numeric: tabular-nums;
    color: rgba(255,255,255,0.6);
}
.stop-btn {
    padding: 0.18rem 0.6rem;
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: var(--profile-border-radius, 8px);
    background: transparent;
    color: rgba(255,255,255,0.5);
    font-size: 0.82rem;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s;
}
.stop-btn:hover { border-color: rgba(255,255,255,0.4); color: #fff; }

/* ── Гость без аудио ─────────────────────────────────────── */
.no-audio {
    text-align: center;
    font-size: 0.82rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.45);
}

/* ── Кнопка записи ────────────────────────────────────────── */
.rec-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 70%);
    border-radius: var(--profile-border-radius, 8px);
    background: color-mix(in srgb, var(--color-base-1), transparent 95%);
    color: rgba(255,255,255,0.55);
    font-size: 0.88rem;
    font-family: inherit;
    cursor: pointer;
    text-align: center;
    transition: border-color 0.2s, color 0.2s, background 0.2s;
}
.rec-btn:hover {
    border-color: color-mix(in srgb, var(--color-base-1), transparent 35%);
    background: color-mix(in srgb, var(--color-base-1), transparent 90%);
    color: #fff;
}
.rec-btn-icon {
    width: 15px; height: 15px;
    flex-shrink: 0;
    color: color-mix(in srgb, var(--color-base-1), transparent 30%);
    transition: color 0.2s;
}
.rec-btn:hover .rec-btn-icon { color: var(--color-base-1); }

/* ── Uploading ────────────────────────────────────────────── */
.uploading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 80%);
    border-radius: var(--profile-border-radius, 8px);
    background: color-mix(in srgb, var(--color-base-1), transparent 96%);
    box-sizing: border-box;
}
.uploading__spinner {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    color: color-mix(in srgb, var(--color-base-1), transparent 20%);
    animation: spin360 0.8s linear infinite;
}
.uploading__label {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.45);
}
</style>
