<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Edit, Setting, Camera } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import ProfileVoice from '@/Components/Profile/ProfileVoice.vue';

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

</script>

<template>
    <div id="tour-header" class="profile-header">
        <!-- Decorative blobs -->
        <div class="header-orb header-orb--pink" aria-hidden="true"></div>
        <div class="header-orb header-orb--purple" aria-hidden="true"></div>

        <!-- Avatar with animated ring -->
        <div class="avatar-ring" :class="{ 'avatar-clickable': isOwner }" @click="onAvatarClick">
            <div class="profile-avatar">
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

            <!-- Voice player / recorder -->
            <div id="tour-voice" class="voice-row">
                <ProfileVoice :voice-url="voiceUrl" :is-owner="isOwner" />
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
    padding: 2rem 1.75rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
    flex-wrap: wrap;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    transition: border-color 0.25s ease, box-shadow 0.25s ease;
}
.profile-header::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(200,70,126,0.55) 35%, rgba(140,80,200,0.45) 65%, transparent 100%);
}
.profile-header:hover {
    border-color: rgba(200,70,126,0.2);
    box-shadow: 0 16px 48px rgba(0,0,0,0.5), 0 0 60px rgba(200,70,126,0.07), 0 0 0 1px rgba(200,70,126,0.1);
}

/* Orbs */
.header-orb {
    position: absolute; border-radius: 50%; pointer-events: none;
    filter: blur(42px); z-index: 0;
}
.header-orb--pink  { width: 220px; height: 220px; top: -80px; right: 80px; background: rgba(200,70,126,0.13); }
.header-orb--purple { width: 180px; height: 180px; bottom: -70px; right: 10px; background: rgba(100,60,180,0.11); }

/* All children above orbs */
.profile-header > *:not(.header-orb) { position: relative; z-index: 1; }

/* Animated ring around avatar */
.avatar-ring {
    width: 100px; height: 100px;
    border-radius: 50%; padding: 2.5px; flex-shrink: 0;
    background: linear-gradient(135deg, rgba(200,70,126,0.9) 0%, rgba(140,80,200,0.7) 100%);
    box-shadow: 0 0 12px rgba(200,70,126,0.5), 0 0 28px rgba(200,70,126,0.2);
}
.avatar-clickable { cursor: pointer; }

.profile-avatar {
    width: 100%; height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(200,70,126,0.35), rgba(200,70,126,0.1));
    border: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 500; color: rgba(255,255,255,0.9);
    position: relative;
    overflow: hidden;
}

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
    font-size: 1.7rem; font-weight: 700; margin: 0 0 0.3rem;
    background: linear-gradient(135deg, #fff 0%, rgba(230,170,200,0.9) 55%, rgba(180,130,220,0.85) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: rgba(255,255,255,0.95); /* fallback */
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
