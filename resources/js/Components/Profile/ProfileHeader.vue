<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Edit, Setting, Camera, VideoPlay, VideoPause, Microphone, Close } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    user: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
    voiceUrl: { type: String, default: null },
});

const editModal = ref(false);
const avatarInput = ref(null);

const GENDERS = { male: 'Мужской', female: 'Женский', other: 'Другой' };
const genderLabel = computed(() => GENDERS[props.user.gender] ?? '');

const form = useForm({
    gender: props.user.gender ?? '',
    birth_date: props.user.birth_date ?? '',
    timezone: props.user.timezone ?? '',
});

const TIMEZONES = [
    'Europe/Moscow', 'Europe/Kiev', 'Europe/Minsk', 'Europe/London',
    'Europe/Berlin', 'Europe/Paris', 'Europe/Amsterdam', 'Europe/Warsaw',
    'Asia/Almaty', 'Asia/Tashkent', 'Asia/Yekaterinburg', 'Asia/Novosibirsk',
    'Asia/Krasnoyarsk', 'Asia/Irkutsk', 'Asia/Yakutsk', 'Asia/Vladivostok',
    'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
    'Asia/Tokyo', 'Asia/Seoul', 'Asia/Shanghai', 'Asia/Dubai', 'Asia/Kolkata',
];

const currentYear = new Date().getFullYear();
const monthNames = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];

function parseBd(dateStr) {
    if (!dateStr) return { y: '', m: '', d: '' };
    const [y, m, d] = dateStr.split('-');
    return { y: parseInt(y), m: parseInt(m), d: parseInt(d) };
}

const bd = computed(() => parseBd(props.user.birth_date));
const bdDay   = ref(bd.value.d || '');
const bdMonth = ref(bd.value.m || '');
const bdYear  = ref(bd.value.y || '');

const yearOptions = computed(() => {
    const years = [];
    for (let y = currentYear - 18; y >= currentYear - 100; y--) years.push(y);
    return years;
});

const dayOptions = computed(() => {
    if (!bdMonth.value) return Array.from({ length: 31 }, (_, i) => i + 1);
    const days = new Date(bdYear.value || 2000, parseInt(bdMonth.value), 0).getDate();
    return Array.from({ length: days }, (_, i) => i + 1);
});

function submitEdit() {
    if (bdDay.value && bdMonth.value && bdYear.value) {
        form.birth_date = `${bdYear.value}-${String(bdMonth.value).padStart(2,'0')}-${String(bdDay.value).padStart(2,'0')}`;
    } else {
        form.birth_date = '';
    }
    form.patch(route('profile.update.header'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}

function onAvatarClick() {
    if (props.isOwner) avatarInput.value?.click();
}

function onAvatarChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('avatar', file);
    router.post(route('profile.update.avatar'), fd, {
        preserveState: true,
        preserveScroll: true,
        forceFormData: true,
    });
    e.target.value = '';
}

function deleteAvatar() {
    router.delete(route('profile.delete.avatar'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}

// ── Voice ──────────────────────────────────────────────────
const MAX_SECONDS = 27;

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

const recording = ref(false);
const countdown = ref(MAX_SECONDS);
const chunks = ref([]);
let mediaRecorder = null;
let countdownTimer = null;

const mimeType = computed(() => {
    if (typeof MediaRecorder === 'undefined') return 'audio/webm';
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
    <div id="tour-header" class="profile-header">
        <div
            class="profile-avatar"
            :class="{ 'avatar-clickable': isOwner }"
            @click="onAvatarClick"
        >
            <img
                v-if="user.avatar_url"
                :src="user.avatar_url"
                alt="Avatar"
                class="avatar-img"
            />
            <span v-else class="avatar-letter">{{ user.name.charAt(0).toUpperCase() }}</span>
            <div v-if="isOwner" class="avatar-overlay">
                <el-icon class="avatar-overlay-icon"><Camera /></el-icon>
            </div>
        </div>

        <input
            v-if="isOwner"
            ref="avatarInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="hidden-input"
            @change="onAvatarChange"
        />

        <div class="profile-info">
            <h1 class="profile-name">{{ user.name }}</h1>
            <div class="profile-meta">
                <span v-if="user.age">{{ user.age }} лет</span>
                <span v-if="user.gender" class="meta-sep">·</span>
                <span v-if="user.gender">{{ genderLabel }}</span>
                <span v-if="user.timezone" class="meta-sep">·</span>
                <span v-if="user.timezone" class="meta-tz">{{ user.timezone }}</span>
            </div>

            <!-- Compact voice section inside header -->
            <div id="tour-voice" class="voice-row">
                <!-- Playback -->
                <template v-if="voiceUrl">
                    <audio
                        ref="audioEl"
                        :src="voiceUrl"
                        @timeupdate="onTimeUpdate"
                        @play="playing = true"
                        @pause="playing = false"
                        @ended="onEnded"
                    />
                    <button class="voice-play-btn" @click="togglePlay" :title="playing ? 'Пауза' : 'Воспроизвести'">
                        <el-icon><component :is="playing ? VideoPause : VideoPlay" /></el-icon>
                    </button>
                    <div class="voice-progress-bar">
                        <div class="voice-progress-fill" :style="{ width: progress + '%' }" />
                    </div>
                    <button v-if="isOwner" class="voice-delete-btn" @click="deleteVoice" title="Удалить голосовое"><el-icon><Close /></el-icon></button>
                </template>

                <!-- Owner record UI when no voice -->
                <template v-else-if="isOwner">
                    <template v-if="!recording">
                        <button class="voice-record-btn" @click="startRecording"><el-icon><Microphone /></el-icon> Записать приветствие</button>
                    </template>
                    <div v-else class="voice-recording-active">
                        <div class="voice-rec-dot" />
                        <span class="voice-rec-time">{{ countdown }}с</span>
                        <button class="voice-stop-btn" @click="stopRecording">Стоп</button>
                    </div>
                </template>

                <!-- Re-record row when voice exists -->
                <template v-if="isOwner && voiceUrl">
                    <template v-if="!recording">
                        <button class="voice-rerecord-btn" @click="startRecording"><el-icon><Microphone /></el-icon></button>
                    </template>
                    <div v-else class="voice-recording-active">
                        <div class="voice-rec-dot" />
                        <span class="voice-rec-time">{{ countdown }}с</span>
                        <button class="voice-stop-btn" @click="stopRecording">Стоп</button>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="isOwner" class="header-actions">
            <button class="action-btn edit-btn" @click="editModal = true" title="Редактировать">
                <el-icon><Edit /></el-icon>
            </button>
            <a :href="route('settings.edit')" class="action-btn settings-btn" title="Настройки">
                <el-icon><Setting /></el-icon>
            </a>
        </div>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">Редактировать профиль</h3>

                <div class="edit-field">
                    <label class="edit-label">Пол</label>
                    <div class="gender-group">
                        <button type="button" class="gender-btn" :class="{ active: form.gender === 'male' }"   @click="form.gender = 'male'">Мужской</button>
                        <button type="button" class="gender-btn" :class="{ active: form.gender === 'female' }" @click="form.gender = 'female'">Женский</button>
                        <button type="button" class="gender-btn" :class="{ active: form.gender === 'other' }"  @click="form.gender = 'other'">Другой</button>
                    </div>
                </div>

                <div class="edit-field">
                    <label class="edit-label">Дата рождения</label>
                    <div class="dob-row">
                        <select v-model="bdDay"   class="edit-select"><option value="" disabled>День</option><option v-for="d in dayOptions"  :key="d"   :value="d">{{ d }}</option></select>
                        <select v-model="bdMonth" class="edit-select"><option value="" disabled>Месяц</option><option v-for="(n,i) in monthNames" :key="i+1" :value="i+1">{{ n }}</option></select>
                        <select v-model="bdYear"  class="edit-select"><option value="" disabled>Год</option><option v-for="y in yearOptions"  :key="y"   :value="y">{{ y }}</option></select>
                    </div>
                </div>

                <div class="edit-field">
                    <label class="edit-label">Часовой пояс</label>
                    <select v-model="form.timezone" class="edit-select edit-select--full">
                        <option value="">Не указан</option>
                        <option v-for="tz in TIMEZONES" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                </div>

                <div v-if="user.avatar_url" class="edit-field">
                    <label class="edit-label">Аватар</label>
                    <button class="delete-avatar-btn" type="button" @click="deleteAvatar">Удалить фото</button>
                </div>

                <button class="save-btn" :disabled="form.processing" @click="submitEdit">Сохранить</button>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.profile-header {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.5rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
    position: relative;
    flex-wrap: wrap;
}

.profile-avatar {
    width: 88px; height: 88px; flex-shrink: 0;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(200,70,126,0.35), rgba(200,70,126,0.1));
    border: 1px solid rgba(200,70,126,0.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 500; color: rgba(255,255,255,0.9);
    position: relative;
    overflow: hidden;
}

.avatar-clickable { cursor: pointer; }

.avatar-img {
    width: 100%; height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.avatar-letter { line-height: 1; }

.avatar-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.2s ease;
}
.profile-avatar:hover .avatar-overlay { opacity: 1; }
.avatar-overlay-icon { font-size: 1.4rem; color: #fff; }

.hidden-input { display: none; }

.profile-info { flex: 1; min-width: 0; }

.profile-name {
    font-size: 1.5rem; font-weight: 600;
    color: rgba(255,255,255,0.95); margin: 0 0 0.3rem;
}

.profile-meta {
    display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem;
    font-size: 0.9rem; color: rgba(255,255,255,0.5);
    margin-bottom: 0.65rem;
}
.meta-sep { opacity: 0.4; }

/* Voice row inside header */
.voice-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.voice-play-btn {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: rgba(200,70,126,0.15); border: 1px solid rgba(200,70,126,0.35);
    color: #fff; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.voice-play-btn:hover { background: rgba(200,70,126,0.3); }

.voice-progress-bar {
    flex: 1; min-width: 60px; max-width: 180px;
    height: 3px; background: rgba(255,255,255,0.1);
    border-radius: 2px; overflow: hidden;
}
.voice-progress-fill { height: 100%; background: rgba(200,70,126,0.7); transition: width 0.1s; }

.voice-delete-btn {
    background: none; border: none; color: rgba(255,255,255,0.25);
    font-size: 0.8rem; cursor: pointer; transition: color 0.2s; padding: 0.2rem;
}
.voice-delete-btn:hover { color: rgba(220,80,100,0.8); }

.voice-record-btn {
    padding: 0.35rem 0.85rem; border-radius: 20px;
    border: 1px solid rgba(200,70,126,0.3);
    background: rgba(200,70,126,0.08);
    color: rgba(255,255,255,0.65); font-size: 0.82rem; cursor: pointer;
    font-family: inherit; transition: all 0.2s;
}
.voice-record-btn:hover { background: rgba(200,70,126,0.18); color: #fff; }

.voice-rerecord-btn {
    background: none; border: none; cursor: pointer;
    font-size: 1rem; opacity: 0.45; transition: opacity 0.2s;
    padding: 0.1rem;
}
.voice-rerecord-btn:hover { opacity: 0.9; }

.voice-recording-active { display: flex; align-items: center; gap: 0.5rem; }
.voice-rec-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #e04060;
    animation: blink 1s ease-in-out infinite;
}
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }
.voice-rec-time { font-size: 0.88rem; font-variant-numeric: tabular-nums; color: rgba(255,255,255,0.7); }
.voice-stop-btn {
    padding: 0.25rem 0.7rem; border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.7); font-size: 0.8rem; cursor: pointer; font-family: inherit;
}

/* Action buttons — always in DOM when isOwner, no layout shift */
.header-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.action-btn {
    display: flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px;
    border: none; background: transparent; cursor: pointer;
    color: rgba(255,255,255,0.25);
    font-size: 1rem;
    transition: color 0.2s ease, background 0.2s ease;
    text-decoration: none;
    padding: 0;
}
.edit-btn {
    opacity: 0;
    transition: opacity 0.2s ease, color 0.2s ease, background 0.2s ease;
}
.profile-header:hover .edit-btn {
    opacity: 1;
}
.action-btn:hover {
    color: rgba(200,70,126,0.9);
    background: rgba(200,70,126,0.1);
}

/* Edit form */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 1.25rem; }
.edit-field { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem; }
.edit-label { font-size: 0.68rem; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(200,70,126,0.55); }
.edit-select {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; padding: 0.6rem 0.75rem;
    color: rgba(255,255,255,0.85); font-size: 0.9rem; font-family: inherit; outline: none;
}
.edit-select--full { width: 100%; }
.dob-row { display: flex; gap: 0.5rem; }
.dob-row .edit-select { flex: 1; }
.gender-group { display: flex; gap: 0.5rem; }
.gender-btn {
    flex: 1; padding: 0.45rem; border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.1); background: transparent;
    color: rgba(255,255,255,0.45); font-size: 0.85rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.gender-btn.active { border-color: rgba(200,70,126,0.5); background: rgba(200,70,126,0.12); color: #fff; }
.delete-avatar-btn {
    padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.85rem;
    border: 1px solid rgba(200,70,126,0.3); background: rgba(200,70,126,0.08);
    color: rgba(200,70,126,0.8); cursor: pointer; font-family: inherit; transition: all 0.2s;
    align-self: flex-start;
}
.delete-avatar-btn:hover { background: rgba(200,70,126,0.18); border-color: rgba(200,70,126,0.5); }
.save-btn {
    width: 100%; margin-top: 0.5rem; padding: 0.8rem; border-radius: 10px;
    border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
