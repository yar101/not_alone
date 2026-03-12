<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Edit, Setting, Camera } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import ProfileChecklist from '@/Components/Profile/ProfileChecklist.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { Cropper, CircleStencil } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

// ── Pluralization ──────────────────────────────────────────────
const agePR = new Intl.PluralRules('ru');
const ageForms = { one: 'год', few: 'года', many: 'лет', other: 'лет' };
function ageLabel(n) { return `${n} ${ageForms[agePR.select(n)]}`; }

const props = defineProps({
    user:        { type: Object, required: true },
    isOwner:     { type: Boolean, default: false },
    isIdol:      { type: Boolean, default: false },
    rating:      { default: null },
    traits:      { default: null },
    interests:   { default: null },
    languages:   { default: null },
});

const editModal = ref(false);
const avatarInput = ref(null);
const lightboxOpen = ref(false);

// ── Marquee для имени ──────────────────────────────────────
const nameWrapRef = ref(null);
const nameRef = ref(null);
const hasNameOverflow = ref(false);
const nameScrollOffset = ref('0px');

function measureNameScroll() {
    if (!nameWrapRef.value || !nameRef.value) return;
    const overflow = nameRef.value.scrollWidth - nameWrapRef.value.clientWidth;
    hasNameOverflow.value = overflow > 4;
    nameScrollOffset.value = hasNameOverflow.value ? `-${overflow}px` : '0px';
}

onMounted(() => {
    measureNameScroll();
    window.addEventListener('resize', measureNameScroll);
});
onUnmounted(() => window.removeEventListener('resize', measureNameScroll));

// ── Кроп ──────────────────────────────────────────────────────
const cropModal = ref(false);
const cropSrc = ref('');
const cropperRef = ref(null);
const cropError = ref('');
const cropUploading = ref(false);
const cropWrapHeight = ref(380);

function onEsc(e) {
    if (e.key === 'Escape') {
        lightboxOpen.value = false;
        if (cropModal.value) cancelCrop();
    }
}
onMounted(() => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));

const form = useForm({
    name: props.user.name ?? '',
    gender: props.user.gender ?? '',
    birth_date: props.user.birth_date ?? '',
    timezone: props.user.timezone ?? '',
});

const NAME_RE = /^\p{L}+(\s\p{L}+)?$/u;
const nameError = ref('');

function validateName(value) {
    if (!value.trim()) return 'Имя обязательно.';
    if (value.trim().length < 2) return 'Имя слишком короткое.';
    if (value.trim().length > 100) return 'Имя слишком длинное.';
    if (!NAME_RE.test(value.trim())) return 'Одно или два слова, только буквы.';
    return '';
}

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

const monthOptions = computed(() => monthNames.map((n, i) => ({ value: i + 1, label: n })));
const timezoneOptions = computed(() => [
    { value: '', label: 'Не указан' },
    ...TIMEZONES.map(tz => ({ value: tz, label: tz })),
]);

function submitEdit() {
    nameError.value = validateName(form.name);
    if (nameError.value) return;

    form.name = form.name.trim();

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
    else if (props.user.avatar_url) lightboxOpen.value = true;
}

function onAvatarChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    cropError.value = '';
    if (file.size > MAX_FILE_SIZE) {
        cropError.value = 'Файл слишком большой. Максимум 5 МБ.';
        cropModal.value = true;
        return;
    }

    const url = URL.createObjectURL(file);
    cropSrc.value = url;

    const img = new Image();
    img.onload = () => {
        const ratio = img.naturalHeight / img.naturalWidth;
        cropWrapHeight.value = Math.min(380, Math.max(200, Math.round(420 * ratio)));
    };
    img.src = url;

    cropModal.value = true;
}

function cancelCrop() {
    cropModal.value = false;
    cropSrc.value = '';
    cropError.value = '';
    cropWrapHeight.value = 380;
}

function applyCrop() {
    if (cropError.value || !cropperRef.value) return;
    const { canvas } = cropperRef.value.getResult();
    if (!canvas) return;

    cropUploading.value = true;
    canvas.toBlob(blob => {
        const fd = new FormData();
        fd.append('avatar', blob, 'avatar.jpg');
        router.post(route('profile.update.avatar'), fd, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                cropUploading.value = false;
                cancelCrop();
            },
        });
    }, 'image/jpeg', 0.92);
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

        <!-- Кнопки сверху справа -->
        <div v-if="isOwner" class="header-actions">
            <button class="action-pill" @click="editModal = true" title="Редактировать">
                <el-icon><Edit /></el-icon>
            </button>
            <a :href="route('settings.edit')" class="action-pill" title="Настройки">
                <el-icon><Setting /></el-icon>
            </a>
        </div>

        <!-- Аватар по центру -->
        <div class="header-avatar-area">
            <div class="avatar-wrapper">
                <div class="avatar-ring" :class="{ 'avatar-clickable': isOwner || user.avatar_url }" @click="onAvatarClick">
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
                <ProfileChecklist v-if="isOwner" :user="user" :traits="traits" :interests="interests" :languages="languages" />
            </div>
        </div>

        <!-- Имя + мета -->
        <div class="header-name-wrap">
            <div
                ref="nameWrapRef"
                class="header-name-scroller"
                :class="{ 'name-overflows': hasNameOverflow }"
                :style="hasNameOverflow ? { '--name-offset': nameScrollOffset } : {}"
            >
                <h1 ref="nameRef" class="header-name">{{ user.name }}</h1>
            </div>
            <div v-if="user.gender || user.age" class="header-meta">
                <span v-if="user.gender" class="meta-badge" :class="'meta-badge--' + user.gender">
                    <svg v-if="user.gender === 'female'" class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="9" r="6"/><line x1="12" y1="15" x2="12" y2="21"/><line x1="9" y1="19" x2="15" y2="19"/>
                    </svg>
                    <svg v-else class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="10" cy="14" r="6"/><line x1="14.5" y1="9.5" x2="21" y2="3"/><polyline points="16 3 21 3 21 8"/>
                    </svg>
                    {{ user.gender === 'female' ? 'Женский' : 'Мужской' }}
                </span>
                <span v-if="user.age" class="meta-badge meta-badge--age">{{ ageLabel(user.age) }}</span>
            </div>
        </div>

        <!-- Рейтинг -->
        <div v-if="rating !== null" class="header-rating">
            <div class="rating-block">
                <span class="rating-label">Рейтинг</span>
                <div class="rating-inner">
                    <img src="/stars/10.png" class="star-img" alt="rating" />
                    <span class="rating-num">{{ rating }}</span>
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

        <!-- Lightbox -->
        <Teleport to="body">
            <Transition name="lb">
                <div v-if="lightboxOpen" class="lightbox" @click="lightboxOpen = false">
                    <img :src="user.avatar_url" class="lightbox-img" alt="Avatar" @click.stop />
                </div>
            </Transition>
        </Teleport>

        <!-- Crop modal -->
        <SiteModal :show="cropModal" variant="pink" :compact="true" @close="cancelCrop">
            <div class="crop-form">
                <h3 class="edit-title">Обрезка фото</h3>

                <div v-if="cropError" class="crop-error">{{ cropError }}</div>

                <template v-else>
                    <div class="crop-wrap" :style="{ height: cropWrapHeight + 'px' }">
                        <Cropper
                            ref="cropperRef"
                            :src="cropSrc"
                            :stencil-component="CircleStencil"
                            :stencil-props="{ movable: true, resizable: true }"
                            :default-size="{ width: 300, height: 300 }"
                            background-class="cropper-bg"
                            class="cropper"
                        />
                    </div>

                    <div class="crop-rotate-row">
                        <button class="crop-rotate-btn" type="button" @click="cropperRef.rotate(-90)" title="Повернуть влево">↺</button>
                        <button class="crop-rotate-btn" type="button" @click="cropperRef.rotate(90)" title="Повернуть вправо">↻</button>
                    </div>
                </template>

                <div class="crop-actions">
                    <button class="crop-cancel-btn" type="button" @click="cancelCrop">Отмена</button>
                    <button
                        class="save-btn"
                        type="button"
                        :disabled="!!cropError || cropUploading"
                        @click="applyCrop"
                    >
                        {{ cropUploading ? 'Загрузка...' : 'Сохранить' }}
                    </button>
                </div>
            </div>
        </SiteModal>

        <!-- Edit modal -->
        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">Редактировать профиль</h3>

                <div class="edit-field">
                    <label class="edit-label">Имя</label>
                    <input
                        v-model="form.name"
                        class="edit-input"
                        type="text"
                        placeholder="Имя или Имя Фамилия"
                        @input="nameError = ''"
                    />
                    <span v-if="nameError || form.errors.name" class="edit-field-error">
                        {{ nameError || form.errors.name }}
                    </span>
                </div>

                <div class="edit-field">
                    <label class="edit-label">Пол</label>
                    <div class="gender-group">
                        <button type="button" class="gender-btn" :class="{ active: form.gender === 'male' }"   @click="form.gender = 'male'">Мужской</button>
                        <button type="button" class="gender-btn" :class="{ active: form.gender === 'female' }" @click="form.gender = 'female'">Женский</button>
                    </div>
                </div>

                <div class="edit-field">
                    <label class="edit-label">Дата рождения</label>
                    <div class="dob-row">
                        <AppSelect v-model="bdDay"   :options="dayOptions"    placeholder="День"  style="flex:1;min-width:0" />
                        <AppSelect v-model="bdMonth" :options="monthOptions"  placeholder="Месяц" style="flex:1;min-width:0" />
                        <AppSelect v-model="bdYear"  :options="yearOptions"   placeholder="Год"   style="flex:1;min-width:0" />
                    </div>
                </div>

                <div class="edit-field">
                    <label class="edit-label">Часовой пояс</label>
                    <AppSelect v-model="form.timezone" :options="timezoneOptions" placeholder="Не указан" />
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
    overflow: visible;
    background: #06060e;
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 3px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem 1rem 1.5rem;
    font-family: 'Figtree', sans-serif;
}

/* Кнопки — абсолютно в правом верхнем углу */
.header-actions {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    display: flex;
    gap: 0.4rem;
    align-items: center;
}

.action-pill {
    width: 32px; height: 32px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.35);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.action-pill:hover {
    color: #BE91FF;
    border-color: rgba(190,145,255,0.5);
    background: rgba(190,145,255,0.05);
}

/* Avatar */
.header-avatar-area {
    display: flex;
    justify-content: center;
    padding: 1.5rem 0 1.25rem;
}

.avatar-wrapper {
    position: relative;
    display: inline-flex;
}

.avatar-ring {
    width: 190px; height: 190px;
    border-radius: 50%;
    padding: 2px;
    flex-shrink: 0;
    border: 1px solid rgba(190,145,255,0.6);
    box-shadow: 0 0 0 1px rgba(190,145,255,0.15), 0 0 24px rgba(190,145,255,0.12);
}
.avatar-clickable { cursor: pointer; }

.profile-avatar {
    width: 100%; height: 100%;
    border-radius: 50%;
    background: rgba(190,145,255,0.08);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem; font-weight: 500; color: rgba(255,255,255,0.9);
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

/* Имя */
.header-name-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    margin-bottom: 1.25rem;
}

.header-name-scroller {
    width: 100%;
    overflow: hidden;
    display: flex;
    justify-content: center;
}

.header-name-scroller.name-overflows {
    justify-content: flex-start;
}

.header-name-scroller.name-overflows:hover .header-name {
    animation: header-name-scroll 3s ease-in-out infinite alternate;
    animation-delay: 0.3s;
}

@keyframes header-name-scroll {
    0%,  20% { transform: translateX(0); }
    80%, 100% { transform: translateX(var(--name-offset, 0px)); }
}

.header-name {
    font-size: 1.7rem;
    font-weight: 700;
    margin: 0;
    white-space: nowrap;
    color: #fff;
    letter-spacing: -0.01em;
    flex-shrink: 0;
}

.header-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    justify-content: center;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.18rem 0.6rem;
    border-radius: 3px;
    font-size: 0.78rem;
    letter-spacing: 0.04em;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.55);
}

.meta-badge--female {
    border-color: rgba(190, 145, 255, 0.3);
    background: rgba(190, 145, 255, 0.06);
    color: rgba(190, 145, 255, 0.85);
}

.meta-badge--male {
    border-color: rgba(167, 139, 250, 0.3);
    background: rgba(167, 139, 250, 0.06);
    color: rgba(167, 139, 250, 0.85);
}

.meta-badge--age {
    color: rgba(255, 255, 255, 0.45);
}

.meta-icon {
    width: 0.85em;
    height: 0.85em;
    flex-shrink: 0;
}

/* Рейтинг */
.header-rating {
    width: 100%;
    display: flex;
    justify-content: center;
}

.rating-block {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.6rem 1rem 0.55rem;
    border: 1px solid rgba(190,145,255,0.35);
    border-radius: 3px;
    background: rgba(190,145,255,0.04);
    box-shadow: inset 0 0 16px rgba(190,145,255,0.05);
    min-width: 120px;
}

.rating-label {
    font-family: 'Figtree', sans-serif;
    font-size: 0.58rem;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(190,145,255,0.55);
}

.rating-inner {
    display: flex;
    align-items: flex-end;
    gap: 0.4rem;
    line-height: 1;
}

.star-img {
    width: 22px;
    height: 22px;
    object-fit: contain;
    display: block;
    margin-bottom: 0.15rem;
    opacity: 0.85;
}

.rating-num {
    font-family: 'Dosis', sans-serif;
    font-weight: 300;
    font-size: 2.2rem;
    line-height: 0.85;
    color: #fff;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
}


/* Edit form */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: #fff; margin: 0 0 1.25rem; font-family: 'Figtree', sans-serif; }
.edit-field { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem; }
.edit-label { font-size: 0.68rem; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(190,145,255,0.6); }
.edit-input {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    padding: 0.6rem 0.75rem;
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.edit-input:focus {
    border-color: rgba(190,145,255,0.4);
}

.edit-field-error {
    font-size: 0.75rem;
    color: rgba(190,145,255,0.85);
    margin-top: -0.1rem;
}

.edit-select {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    padding: 0.6rem 0.75rem;
    color: rgba(255,255,255,0.85); font-size: 0.9rem; font-family: inherit; outline: none;
}
.edit-select--full { width: 100%; }
.dob-row { display: flex; gap: 0.5rem; }
.dob-row .edit-select { flex: 1; }
.gender-group { display: flex; gap: 0.5rem; }
.gender-btn {
    flex: 1; padding: 0.45rem;
    border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; background: transparent;
    color: rgba(255,255,255,0.4); font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.gender-btn.active { border-color: #BE91FF; color: #fff; }
.delete-avatar-btn {
    padding: 0.4rem 0.85rem; font-size: 0.85rem;
    border: 1px solid rgba(190,145,255,0.35); border-radius: 3px; background: transparent;
    color: rgba(190,145,255,0.8); cursor: pointer; font-family: inherit; transition: all 0.15s;
    align-self: flex-start;
}
.delete-avatar-btn:hover { border-color: #BE91FF; color: #BE91FF; }
.save-btn {
    width: 100%; margin-top: 0.5rem; padding: 0.8rem;
    border: 1px solid rgba(190,145,255,0.45);
    border-radius: 3px;
    background: rgba(190,145,255,0.08);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: rgba(190,145,255,0.16); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }

/* Crop modal */
.crop-form { padding: 0.5rem 0.25rem; }

/* Затемнение фона за пределами круга */
:deep(.vue-advanced-cropper__background),
:deep(.vue-advanced-cropper__image-wrapper) {
    background: #000;
}

.cropper-bg { background: #111; }

.crop-wrap {
    width: 100%;
    background: #000;
    margin-bottom: 0.75rem;
    overflow: hidden;
}

.cropper {
    width: 100%;
    height: 100%;
}

.crop-rotate-row {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.crop-rotate-btn {
    width: 38px; height: 38px;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.55);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: color 0.15s, border-color 0.15s;
}
.crop-rotate-btn:hover {
    color: #BE91FF;
    border-color: rgba(190,145,255,0.4);
}

.crop-error {
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(190,145,255,0.4);
    border-radius: 3px;
    background: rgba(190,145,255,0.06);
    color: rgba(190,145,255,0.9);
    font-size: 0.9rem;
}

.crop-actions {
    display: flex;
    gap: 0.5rem;
}

.crop-cancel-btn {
    flex: 0 0 auto;
    padding: 0.8rem 1.25rem;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.4);
    font-size: 0.95rem;
    cursor: pointer;
    font-family: inherit;
    transition: color 0.15s, border-color 0.15s;
}
.crop-cancel-btn:hover { color: rgba(255,255,255,0.7); border-color: rgba(255,255,255,0.2); }

/* Lightbox */
.lightbox {
    position: fixed;
    inset: 0;
    z-index: 9998;
    background: rgba(0,0,0,0.88);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
    backdrop-filter: blur(6px);
}

.lightbox-img {
    max-width: min(80vw, 640px);
    max-height: 80vh;
    object-fit: contain;
    border-radius: 50%;
    border: 1px solid rgba(190,145,255,0.4);
    box-shadow: 0 0 60px rgba(190,145,255,0.15);
    cursor: default;
}

.lb-enter-active, .lb-leave-active { transition: opacity 0.2s ease; }
.lb-enter-from, .lb-leave-to { opacity: 0; }
</style>
