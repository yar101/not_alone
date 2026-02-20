<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Edit, Setting } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    user: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
});

const editModal = ref(false);

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
</script>

<template>
    <div id="tour-header" class="profile-header">
        <div class="profile-avatar">
            {{ user.name.charAt(0).toUpperCase() }}
        </div>

        <div class="profile-info">
            <h1 class="profile-name">{{ user.name }}</h1>
            <div class="profile-meta">
                <span v-if="user.age">{{ user.age }} лет</span>
                <span v-if="user.gender" class="meta-sep">·</span>
                <span v-if="user.gender">{{ genderLabel }}</span>
                <span v-if="user.timezone" class="meta-sep">·</span>
                <span v-if="user.timezone" class="meta-tz">{{ user.timezone }}</span>
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
}

.profile-avatar {
    width: 72px; height: 72px; flex-shrink: 0;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(200,70,126,0.35), rgba(200,70,126,0.1));
    border: 1px solid rgba(200,70,126,0.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; font-weight: 500; color: rgba(255,255,255,0.9);
}

.profile-info { flex: 1; min-width: 0; }

.profile-name {
    font-size: 1.5rem; font-weight: 600;
    color: rgba(255,255,255,0.95); margin: 0 0 0.3rem;
}

.profile-meta {
    display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem;
    font-size: 0.9rem; color: rgba(255,255,255,0.5);
}
.meta-sep { opacity: 0.4; }

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
.save-btn {
    width: 100%; margin-top: 0.5rem; padding: 0.8rem; border-radius: 10px;
    border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
