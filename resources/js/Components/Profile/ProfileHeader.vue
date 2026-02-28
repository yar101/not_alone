<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Edit, Setting, Camera } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { Cropper, CircleStencil } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

// ── Временный рейтинг (убрать после внедрения рейтинга) ──
const devRating = ref(73); // 0–100

const props = defineProps({
    user: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
});

const editModal = ref(false);
const avatarInput = ref(null);
const lightboxOpen = ref(false);

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
        </div>

        <!-- Нижняя строка: 3-колоночный грид (рейтинг | имя | действия) -->
        <div class="header-bottom-row">
            <div class="header-left">
                <div class="rating-block">
                    <span class="rating-label">Рейтинг</span>
                    <div class="rating-inner">
                        <img src="/stars/10.png" class="star-img" alt="rating" />
                        <span class="rating-num">{{ devRating }}</span>
                    </div>
                    <div class="rating-bar-track">
                        <div class="rating-bar-fill"></div>
                    </div>
                </div>
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
                    <label class="edit-label">Пол</label>
                    <div class="gender-group">
                        <button type="button" class="gender-btn" :class="{ active: form.gender === 'male' }"   @click="form.gender = 'male'">Мужской</button>
                        <button type="button" class="gender-btn" :class="{ active: form.gender === 'female' }" @click="form.gender = 'female'">Женский</button>
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
    border: 1px solid rgba(255,255,255,0.18);
    border-bottom: none;
    border-radius: 3px 3px 0 0;
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
    width: 200px; height: 200px;
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
    align-items: flex-end;
}

.rating-block {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.55rem 0.85rem 0.5rem;
    border: 1px solid rgba(254,40,162,0.35);
    border-radius: 3px;
    background: rgba(254,40,162,0.04);
    box-shadow: inset 0 0 16px rgba(254,40,162,0.05);
    position: relative;
}

.rating-label {
    font-family: 'Figtree', sans-serif;
    font-size: 0.58rem;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(254,40,162,0.55);
}

.rating-inner {
    display: flex;
    align-items: flex-end;
    gap: 0.4rem;
    line-height: 1;
}

.star-img {
    width: 26px;
    height: 26px;
    object-fit: contain;
    display: block;
    margin-bottom: 0.2rem;
    opacity: 0.85;
}

.rating-num {
    font-family: 'Dosis', sans-serif;
    font-weight: 300;
    font-size: 2.5rem;
    line-height: 0.85;
    color: #fff;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
}

.rating-bar-track {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin-top: 0.1rem;
}

.rating-bar-fill {
    height: 100%;
    width: 73%;
    background: linear-gradient(90deg, rgba(254,40,162,0.9), rgba(254,40,162,0.4));
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
    border-radius: 3px;
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
    border-radius: 3px;
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
    border-radius: 3px;
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
.gender-btn.active { border-color: #FE28A2; color: #fff; }
.delete-avatar-btn {
    padding: 0.4rem 0.85rem; font-size: 0.85rem;
    border: 1px solid rgba(254,40,162,0.35); border-radius: 3px; background: transparent;
    color: rgba(254,40,162,0.8); cursor: pointer; font-family: inherit; transition: all 0.15s;
    align-self: flex-start;
}
.delete-avatar-btn:hover { border-color: #FE28A2; color: #FE28A2; }
.save-btn {
    width: 100%; margin-top: 0.5rem; padding: 0.8rem;
    border: 1px solid rgba(254,40,162,0.45);
    border-radius: 3px;
    background: rgba(254,40,162,0.08);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: rgba(254,40,162,0.16); }
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
    color: #FE28A2;
    border-color: rgba(254,40,162,0.4);
}

.crop-error {
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(254,40,162,0.4);
    border-radius: 3px;
    background: rgba(254,40,162,0.06);
    color: rgba(254,40,162,0.9);
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
    border: 1px solid rgba(254,40,162,0.4);
    box-shadow: 0 0 60px rgba(254,40,162,0.15);
    cursor: default;
}

.lb-enter-active, .lb-leave-active { transition: opacity 0.2s ease; }
.lb-enter-from, .lb-leave-to { opacity: 0; }
</style>
