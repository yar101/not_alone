<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import WaveSurfer from 'wavesurfer.js';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    voiceUrl: { type: String, default: null },
    isOwner:  { type: Boolean, default: false },
});

// ── WaveSurfer player ──────────────────────────────────────
const waveformEl  = ref(null);
const playing     = ref(false);
const currentSec  = ref(0);
const totalSec    = ref(0);
const wsReady     = ref(false);
let   ws          = null;

function fmt(s) {
    if (!s || !isFinite(s) || isNaN(s) || s === 0) return '0:00';
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
        waveColor:     'rgba(255,255,255,0.28)',
        progressColor: 'rgba(200,70,126,0.9)',
        cursorColor:   'transparent',
        barWidth:      3,
        barGap:        2,
        barRadius:     3,
        height:        28,
        normalize:     true,
        interact:      true,
        backend:       'WebAudio',
    });

    ws.on('ready', (dur) => {
        totalSec.value = dur;
        wsReady.value  = true;
    });

    ws.on('timeupdate', (sec) => {
        currentSec.value = sec;
    });

    ws.on('play',   () => { playing.value = true; });
    ws.on('pause',  () => { playing.value = false; });
    ws.on('finish', () => {
        playing.value    = false;
        currentSec.value = 0;
        ws?.seekTo(0);
    });
}

function togglePlay() {
    if (!ws || !wsReady.value) return;
    ws.playPause();
}

// Re-init when voiceUrl changes (after recording upload).
// nextTick ensures v-if="voiceUrl" has rendered the container before WaveSurfer mounts.
watch(() => props.voiceUrl, async (url) => {
    if (url) { await nextTick(); initWaveSurfer(); }
    else if (ws) { ws.destroy(); ws = null; wsReady.value = false; }
});

onMounted(() => {
    if (props.voiceUrl) initWaveSurfer();
});

onUnmounted(() => {
    ws?.destroy();
    ws = null;
    clearInterval(timer);
});

// ── Recording ─────────────────────────────────────────────
const MAX_SEC   = 27;
const recording = ref(false);
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
    try {
        // Stop WaveSurfer if playing
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
    } catch {
        alert('Не удалось получить доступ к микрофону');
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
    router.post(route('profile.update.voice'), fd, { preserveState: true, preserveScroll: true, forceFormData: true });
}

function deleteVoice() {
    if (!confirm('Удалить голосовое?')) return;
    if (ws) { ws.destroy(); ws = null; wsReady.value = false; }
    router.delete(route('profile.delete.voice'), { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <!-- ── PLAYER ───────────────────────────────────────────── -->
    <div v-if="voiceUrl" class="vp-wrap">
        <!-- Play / Pause -->
        <button
            class="vp-play"
            :class="{ playing, loading: !wsReady }"
            :disabled="!wsReady"
            @click="togglePlay"
            :title="playing ? 'Пауза' : 'Играть'"
        >
            <!-- Spinner while loading -->
            <svg v-if="!wsReady" class="vp-icon vp-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="9" stroke-opacity="0.25"/>
                <path d="M12 3a9 9 0 0 1 9 9" stroke-linecap="round"/>
            </svg>
            <!-- Play icon -->
            <svg v-else-if="!playing" class="vp-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5.14v14l11-7-11-7z"/>
            </svg>
            <!-- Pause icon -->
            <svg v-else class="vp-icon vp-icon--pause" viewBox="0 0 24 24" fill="currentColor">
                <rect x="6" y="5" width="4" height="14" rx="1"/>
                <rect x="14" y="5" width="4" height="14" rx="1"/>
            </svg>
        </button>

        <!-- WaveSurfer container + time -->
        <div class="vp-body">
            <div ref="waveformEl" class="vp-waveform" />
            <div class="vp-times">
                <span class="vp-time-cur">{{ fmt(currentSec) }}</span>
                <span class="vp-time-sep">·</span>
                <span class="vp-time-tot">{{ fmt(totalSec) }}</span>
            </div>
        </div>

        <!-- Owner controls -->
        <div v-if="isOwner" class="vp-owner">
            <button class="vp-delete" @click="deleteVoice" title="Удалить">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- ── RECORDING ACTIVE ──────────────────────────────────── -->
    <div v-else-if="recording" class="vr-wrap">
        <div class="vr-dot-wrap">
            <span class="vr-ring vr-ring--1"/>
            <span class="vr-ring vr-ring--2"/>
            <span class="vr-dot"/>
        </div>
        <div class="vr-bars">
            <span v-for="i in 7" :key="i" class="vr-bar" :style="{ animationDelay: (i * 80) + 'ms' }"/>
        </div>
        <span class="vr-time">0:{{ String(countdown).padStart(2, '0') }}</span>
        <button class="vr-stop" @click="stopRecording">Стоп</button>
    </div>

    <!-- ── RECORD TRIGGER ────────────────────────────────────── -->
    <button v-else-if="isOwner" class="vt-btn" @click="startRecording">
        <svg class="vt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="22"/>
        </svg>
        Записать голосовое
    </button>
</template>

<style scoped>
/* ── PLAYER ────────────────────────────────────────────────── */
.vp-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.45rem 0.65rem 0.45rem 0.45rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 28px;
    max-width: 340px;
    width: 100%;
}

/* Play button */
.vp-play {
    width: 36px; height: 36px; flex-shrink: 0;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, rgba(200,70,126,0.9) 0%, rgba(140,60,200,0.8) 100%);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 0 0 0 rgba(200,70,126,0);
    transition: box-shadow 0.25s ease, transform 0.15s ease, opacity 0.2s;
}
.vp-play:hover:not(:disabled) {
    transform: scale(1.07);
    box-shadow: 0 0 18px rgba(200,70,126,0.45);
}
.vp-play.playing {
    box-shadow: 0 0 0 3px rgba(200,70,126,0.2), 0 0 14px rgba(200,70,126,0.3);
}
.vp-play.loading { opacity: 0.65; cursor: default; }

.vp-icon { width: 16px; height: 16px; }
.vp-icon--pause { width: 14px; height: 14px; }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.vp-spinner { animation: spin 0.9s linear infinite; }

/* Body: waveform + time */
.vp-body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

/* WaveSurfer container */
.vp-waveform {
    width: 100%;
    cursor: pointer;
}

/* Time */
.vp-times {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-variant-numeric: tabular-nums;
    color: rgba(255,255,255,0.35);
    padding-left: 1px;
}
.vp-time-sep { opacity: 0.4; }
.vp-time-tot { color: rgba(255,255,255,0.25); }

/* Owner controls */
.vp-owner {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex-shrink: 0;
}
.vp-delete {
    width: 22px; height: 22px;
    background: none; border: none; padding: 0;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: color 0.2s, opacity 0.2s;
    opacity: 0.35;
    color: rgba(255,255,255,0.6);
}
.vp-delete svg { width: 14px; height: 14px; }
.vp-delete:hover { color: rgba(220,70,80,0.9); opacity: 1; }


/* ── RECORDING ACTIVE ──────────────────────────────────────── */
.vr-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.4rem 0.85rem 0.4rem 0.5rem;
    background: rgba(224,60,80,0.07);
    border: 1px solid rgba(224,60,80,0.2);
    border-radius: 28px;
}

/* Red dot with rings */
.vr-dot-wrap {
    position: relative;
    width: 14px; height: 14px;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.vr-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #e04060;
    position: relative; z-index: 1;
}
.vr-ring {
    position: absolute;
    border-radius: 50%;
    border: 1.5px solid rgba(224,60,80,0.5);
    animation: ringExpand 1.4s ease-out infinite;
}
.vr-ring--1 { width: 14px; height: 14px; animation-delay: 0s; }
.vr-ring--2 { width: 14px; height: 14px; animation-delay: 0.5s; }
@keyframes ringExpand {
    0%   { transform: scale(0.8); opacity: 0.8; }
    100% { transform: scale(2.2); opacity: 0; }
}

/* Mini animated bars during recording */
.vr-bars {
    display: flex;
    align-items: center;
    gap: 2px;
    height: 20px;
}
@keyframes vrBar {
    0%, 100% { height: 4px; }
    50%       { height: 16px; }
}
.vr-bar {
    width: 3px; height: 8px;
    border-radius: 2px;
    background: rgba(224,60,80,0.7);
    animation: vrBar 0.65s ease-in-out infinite;
}

.vr-time {
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
    color: rgba(255,255,255,0.6);
    min-width: 2.2ch;
}

.vr-stop {
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.15);
    background: transparent;
    color: rgba(255,255,255,0.55);
    font-size: 0.78rem;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.2s, color 0.2s;
}
.vr-stop:hover {
    border-color: rgba(255,255,255,0.3);
    color: rgba(255,255,255,0.85);
}


/* ── RECORD TRIGGER BUTTON ─────────────────────────────────── */
.vt-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0;
    background: none;
    border: none;
    color: rgba(200,70,126,0.55);
    font-size: 0.83rem;
    font-family: inherit;
    cursor: pointer;
    transition: color 0.2s ease, text-shadow 0.2s ease;
    letter-spacing: 0.01em;
}
.vt-btn:hover {
    color: rgba(200,70,126,1);
    text-shadow: 0 0 12px rgba(200,70,126,0.3);
}
.vt-icon {
    width: 14px; height: 14px;
    flex-shrink: 0;
    transition: filter 0.2s;
}
.vt-btn:hover .vt-icon {
    filter: drop-shadow(0 0 4px rgba(200,70,126,0.5));
}
</style>
