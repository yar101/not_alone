<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Edit, Setting, Camera } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

// ── Временный рейтинг (убрать после внедрения рейтинга) ──
const devRating = ref(420); // 0–1000

const props = defineProps({
    user: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
});

const editModal = ref(false);
const avatarInput = ref(null);

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
        preserveScroll: true,
        forceFormData: true,
    });
    e.target.value = '';
}

function deleteAvatar() {
    router.delete(route('profile.delete.avatar'), {
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}
</script>

<template>
    <div id="tour-header" class="profile-header">

        <!-- DEV: переключалка рейтинга (fixed в углу) — убрать после внедрения рейтинга -->
        <div class="star-switcher">
            <span class="star-sw-label">DEV рейтинг</span>
            <input
                v-model.number="devRating"
                type="number"
                min="0"
                max="1000"
                class="star-sw-input"
            />
        </div>

        <!-- Аватар по центру -->
        <div class="header-avatar-area">
            <div class="avatar-ring" :class="{ 'avatar-clickable': isOwner }" @click="onAvatarClick">
                <div class="profile-avatar">
                    <img
                        v-if="user.avatar_url"
                        :src="user.avatar_url"
                        class="avatar-img"
                        alt="Avatar"
                    />
                    <span v-else class="avatar-letter">{{ user.name.charAt(0).toUpperCase() }}</span>
                    <div v-if="isOwner" class="avatar-overlay">
                        <el-icon class="avatar-overlay-icon"><Camera /></el-icon>
                    </div>
                </div>
            </div>
        </div>

        <!-- Нижняя строка: 3-колоночный грид (рейтинг | имя | действия) -->
        <div class="header-bottom-row">
            <div class="header-left">
                <img src="/stars/10.png" class="star-img" alt="rating" />
                <span class="rating-num">{{ devRating }}</span>
            </div>
            <h1 class="header-name">{{ user.name }}</h1>
            <div class="header-actions">
                <template v-if="isOwner">
                    <button class="action-pill" @click="editModal = true" title="Редактировать">
                        <el-icon><Edit /></el-icon>
                    </button>
                    <a :href="route('settings.edit')" class="action-pill" title="Настройки">
                        <el-icon><Setting /></el-icon>
                    </a>
                </template>
                <button v-else class="subscribe-btn">Подписаться</button>
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

        <!-- Edit modal -->
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
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.08);
    border-bottom: none;
    padding-top: 1.75rem;
    font-family: 'Figtree', sans-serif;
}

/* Avatar */
.header-avatar-area {
    display: flex;
    justify-content: center;
    padding-bottom: 1rem;
}

.avatar-ring {
    width: 120px; height: 120px;
    border-radius: 50%;
    padding: 2px;
    flex-shrink: 0;
    border: 1px solid rgba(254,40,162,0.6);
    box-shadow: 0 0 0 1px rgba(254,40,162,0.15), 0 0 24px rgba(254,40,162,0.12);
}
.avatar-clickable { cursor: pointer; }

.profile-avatar {
    width: 100%; height: 100%;
    border-radius: 50%;
    background: rgba(254,40,162,0.08);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; font-weight: 500; color: rgba(255,255,255,0.9);
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
    background: rgba(0,0,0,0.55);
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.2s ease;
}
.profile-avatar:hover .avatar-overlay { opacity: 1; }
.avatar-overlay-icon { font-size: 1.4rem; color: #fff; }
.hidden-input { display: none; }

/* Нижняя строка: 3-колоночный грид */
.header-bottom-row {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: end;
    padding: 0 1rem 1rem;
    gap: 1rem;
}

.header-left {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 0.5rem;
}

.star-img {
    width: 50px;
    height: 50px;
    object-fit: contain;
    display: block;
}

.rating-num {
    font-family: "Imbue", serif;
    font-optical-sizing: auto;
    font-weight: 400;
    font-size: 4rem;
    line-height: 0.85;
    color: rgba(255, 70, 200, 0.75);
    letter-spacing: -0.03em;
    font-variant-numeric: tabular-nums;
}

/* DEV: переключалка звёзд (fixed в углу экрана) */
.star-switcher {
    position: fixed;
    bottom: 1.25rem;
    left: 1.25rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(10,10,15,0.92);
    border: 1px solid rgba(255,255,255,0.12);
    padding: 0.35rem 0.55rem;
    backdrop-filter: blur(8px);
}
.star-sw-label {
    font-size: 0.68rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.2);
    margin-right: 0.2rem;
}
.star-sw-input {
    width: 64px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.85);
    font-size: 0.88rem;
    font-family: inherit;
    padding: 0.2rem 0.4rem;
    text-align: center;
    outline: none;
}
.star-sw-input:focus { border-color: #FE28A2; }

.header-name {
    font-size: 2.6rem;
    font-weight: 700;
    margin: 0;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
    letter-spacing: -0.01em;
}

.header-actions {
    justify-self: end;
    align-self: end;
    display: flex;
    gap: 0.4rem;
    align-items: center;
}

.action-pill {
    width: 30px; height: 30px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    color: rgba(255,255,255,0.35);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.15s, border-color 0.15s;
}
.action-pill:hover {
    color: #FE28A2;
    border-color: rgba(254,40,162,0.5);
}

.subscribe-btn {
    padding: 0.4rem 1.25rem;
    font-size: 0.9rem;
    font-weight: 500;
    background: transparent;
    border: 1px solid #FE28A2;
    color: #fff;
    cursor: pointer;
    font-family: inherit;
    letter-spacing: 0.03em;
    transition: background 0.15s;
}
.subscribe-btn:hover {
    background: rgba(254,40,162,0.1);
}

/* Edit form */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: #fff; margin: 0 0 1.25rem; font-family: 'Figtree', sans-serif; }
.edit-field { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem; }
.edit-label { font-size: 0.68rem; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(254,40,162,0.6); }
.edit-select {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
    padding: 0.6rem 0.75rem;
    color: rgba(255,255,255,0.85); font-size: 0.9rem; font-family: inherit; outline: none;
}
.edit-select--full { width: 100%; }
.dob-row { display: flex; gap: 0.5rem; }
.dob-row .edit-select { flex: 1; }
.gender-group { display: flex; gap: 0.5rem; }
.gender-btn {
    flex: 1; padding: 0.45rem;
    border: 1px solid rgba(255,255,255,0.1); background: transparent;
    color: rgba(255,255,255,0.4); font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.gender-btn.active { border-color: #FE28A2; color: #fff; }
.delete-avatar-btn {
    padding: 0.4rem 0.85rem; font-size: 0.85rem;
    border: 1px solid rgba(254,40,162,0.35); background: transparent;
    color: rgba(254,40,162,0.8); cursor: pointer; font-family: inherit; transition: all 0.15s;
    align-self: flex-start;
}
.delete-avatar-btn:hover { border-color: #FE28A2; color: #FE28A2; }
.save-btn {
    width: 100%; margin-top: 0.5rem; padding: 0.8rem;
    border: 1px solid rgba(254,40,162,0.45);
    background: rgba(254,40,162,0.08);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: rgba(254,40,162,0.16); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
