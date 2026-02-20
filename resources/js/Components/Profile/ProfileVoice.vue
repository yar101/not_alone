<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    voiceUrl: { type: String, default: null },
    isOwner: { type: Boolean, default: false },
});

const emit = defineEmits(['updated']);

const MAX_SECONDS = 27;

// ── Playback ──────────────────────────────────────────────
const audioEl = ref(null);
const playing = ref(false);
const progress = ref(0);

function togglePlay() {
    if (!audioEl.value) return;
    if (playing.value) {
        audioEl.value.pause();
    } else {
        audioEl.value.play();
    }
}

function onTimeUpdate() {
    const el = audioEl.value;
    if (!el || !el.duration) return;
    progress.value = (el.currentTime / el.duration) * 100;
}

function onEnded() {
    playing.value = false;
    progress.value = 0;
}

// ── Recording ─────────────────────────────────────────────
const recording = ref(false);
const countdown = ref(MAX_SECONDS);
const chunks = ref([]);
let mediaRecorder = null;
let countdownTimer = null;

const mimeType = computed(() => {
    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) return 'audio/webm;codecs=opus';
    if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) return 'audio/ogg;codecs=opus';
    return 'audio/mp4';
});

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks.value = [];
        mediaRecorder = new MediaRecorder(stream, { mimeType: mimeType.value });
        mediaRecorder.ondataavailable = e => { if (e.data.size > 0) chunks.value.push(e.data); };
        mediaRecorder.onstop = uploadRecording;
        mediaRecorder.start();
        recording.value = true;
        countdown.value = MAX_SECONDS;

        countdownTimer = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) stopRecording();
        }, 1000);
    } catch (e) {
        alert('Не удалось получить доступ к микрофону');
    }
}

function stopRecording() {
    if (!mediaRecorder) return;
    clearInterval(countdownTimer);
    mediaRecorder.stop();
    mediaRecorder.stream.getTracks().forEach(t => t.stop());
    recording.value = false;
}

function uploadRecording() {
    const ext = mimeType.value.includes('mp4') ? 'mp4' : mimeType.value.includes('ogg') ? 'ogg' : 'webm';
    const blob = new Blob(chunks.value, { type: mimeType.value });
    const file = new File([blob], `voice.${ext}`, { type: mimeType.value });
    const formData = new FormData();
    formData.append('voice', file);

    router.post(route('profile.update.voice'), formData, {
        preserveState: true,
        preserveScroll: true,
        forceFormData: true,
    });
}

function deleteVoice() {
    if (!confirm('Удалить голосовое?')) return;
    router.delete(route('profile.delete.voice'), {
        preserveState: true,
        preserveScroll: true,
    });
}

onUnmounted(() => {
    clearInterval(countdownTimer);
});
</script>

<template>
    <div id="tour-voice" class="block-card">
        <div class="block-header">
            <h2 class="block-title">Голосовое приветствие</h2>
        </div>

        <!-- Existing voice -->
        <div v-if="voiceUrl" class="player">
            <audio
                ref="audioEl"
                :src="voiceUrl"
                @timeupdate="onTimeUpdate"
                @play="playing = true"
                @pause="playing = false"
                @ended="onEnded"
            />
            <button class="play-btn" @click="togglePlay">
                {{ playing ? '⏸' : '▶' }}
            </button>
            <div class="progress-bar">
                <div class="progress-fill" :style="{ width: progress + '%' }" />
            </div>
            <button v-if="isOwner" class="delete-btn" @click="deleteVoice" title="Удалить">✕</button>
        </div>

        <!-- Owner recording UI -->
        <div v-if="isOwner && !voiceUrl" class="record-area">
            <p class="record-hint">
                Запишите голосовое приветствие до {{ MAX_SECONDS }} секунд
            </p>
            <button v-if="!recording" class="record-btn" @click="startRecording">
                🎙 Начать запись
            </button>
            <div v-else class="recording-active">
                <div class="rec-dot" />
                <span class="rec-time">{{ countdown }}с</span>
                <button class="stop-btn" @click="stopRecording">Стоп</button>
            </div>
        </div>

        <!-- Owner re-record when voice exists -->
        <div v-if="isOwner && voiceUrl" class="rerecord-row">
            <button v-if="!recording" class="rerecord-btn" @click="startRecording">
                🎙 Перезаписать
            </button>
            <div v-else class="recording-active">
                <div class="rec-dot" />
                <span class="rec-time">{{ countdown }}с</span>
                <button class="stop-btn" @click="stopRecording">Стоп</button>
            </div>
        </div>

        <p v-if="!isOwner && !voiceUrl" class="empty">Голосовое не записано</p>
    </div>
</template>

<style scoped>
.block-card {
    padding: 1.25rem 1.5rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
}
.block-header { margin-bottom: 0.75rem; }
.block-title { font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase; color: rgba(200,70,126,0.6); margin: 0; }
.empty { color: rgba(255,255,255,0.25); font-size: 0.9rem; font-style: italic; margin: 0; }

/* Player */
.player { display: flex; align-items: center; gap: 0.75rem; }
.play-btn {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: rgba(200,70,126,0.2); border: 1px solid rgba(200,70,126,0.4);
    color: #fff; font-size: 1rem; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.play-btn:hover { background: rgba(200,70,126,0.35); }
.progress-bar {
    flex: 1; height: 4px; background: rgba(255,255,255,0.1);
    border-radius: 2px; overflow: hidden;
}
.progress-fill { height: 100%; background: rgba(200,70,126,0.7); transition: width 0.1s; }
.delete-btn {
    background: none; border: none; color: rgba(255,255,255,0.3);
    font-size: 0.85rem; cursor: pointer; transition: color 0.2s; padding: 0.25rem;
}
.delete-btn:hover { color: rgba(220,80,100,0.8); }

/* Recording */
.record-area { display: flex; flex-direction: column; align-items: flex-start; gap: 0.75rem; }
.record-hint { font-size: 0.88rem; color: rgba(255,255,255,0.4); margin: 0; }
.record-btn, .rerecord-btn {
    padding: 0.6rem 1.25rem; border-radius: 24px;
    border: 1px solid rgba(200,70,126,0.35);
    background: rgba(200,70,126,0.1);
    color: rgba(255,255,255,0.8); font-size: 0.9rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.record-btn:hover, .rerecord-btn:hover { background: rgba(200,70,126,0.2); }
.rerecord-row { margin-top: 0.75rem; }
.recording-active { display: flex; align-items: center; gap: 0.75rem; }
.rec-dot {
    width: 10px; height: 10px; border-radius: 50%;
    background: #e04060;
    animation: blink 1s ease-in-out infinite;
}
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }
.rec-time { font-size: 1rem; font-variant-numeric: tabular-nums; color: rgba(255,255,255,0.7); }
.stop-btn {
    padding: 0.4rem 1rem; border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.7); font-size: 0.85rem; cursor: pointer; font-family: inherit;
}
</style>
