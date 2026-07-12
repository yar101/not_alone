<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const activeTab = ref('frames');

const loading = ref(true);
const frames = ref([]);
const unlockedIds = ref([]);
const activeFramePath = ref(null);

const fetchFrames = async () => {
    try {
        loading.value = true;
        const response = await axios.get(route('avatar-frames.index'));
        frames.value = response.data.frames;
        unlockedIds.value = response.data.unlocked_ids;
        activeFramePath.value = response.data.active_frame_path;
    } catch (e) {
        ElMessage.error('Не удалось загрузить список рамок');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchFrames();
});

const isUnlocked = (frame) => {
    if (frame.type === 'free') return true;
    return unlockedIds.value.includes(frame.id);
};

const equip = async (frame) => {
    try {
        const res = await axios.post(route('avatar-frames.equip', frame.id));
        activeFramePath.value = res.data.active_frame_path;
        usePage().props.auth.user.active_frame_path = res.data.active_frame_path;
        router.reload({ only: ['user', 'auth'], preserveScroll: true });
        ElMessage.success(res.data.message);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || 'Ошибка');
    }
};

const unequip = async () => {
    try {
        const res = await axios.post(route('avatar-frames.unequip'));
        activeFramePath.value = null;
        usePage().props.auth.user.active_frame_path = null;
        router.reload({ only: ['user', 'auth'], preserveScroll: true });
        ElMessage.success(res.data.message);
    } catch (e) {
        ElMessage.error('Ошибка');
    }
};
</script>

<template>
    <Head title="Кастомизация" />

    <div class="page-wrap">
        <div class="customization-container">
            <div class="page-header">
                <Link :href="route('profile.show', { user: usePage().props.auth.user.id })" class="back-link">← Мой профиль</Link>
                <h1 class="page-title">Кастомизация</h1>
            </div>

            <div class="split-layout">
                <!-- Левое меню -->
                <aside class="sidebar-menu">
                    <button class="menu-item" :class="{ 'is-active': activeTab === 'frames' }" @click="activeTab = 'frames'">
                        Рамки для аватарки
                    </button>
                    <!-- Место под будущие пункты (например, цвет профиля) -->
                </aside>

                <!-- Контент -->
                <main class="content-area">
                    <div v-if="activeTab === 'frames'" class="frames-section">
                        <h3 class="section-title">Рамки</h3>
                        <p class="section-desc">Украсьте свой аватар уникальной рамкой. Вы можете получить их за достижения или купить.</p>

                        <div v-if="loading" class="frames-loading">
                            <span class="spinner"></span> Загрузка...
                        </div>

                        <div v-else class="frames-container">
                            <div class="current-frame-section">
                                <h4 class="section-subtitle">Текущая рамка</h4>
                                <div class="current-preview">
                                    <img v-if="usePage().props.auth.user.avatar_url" :src="usePage().props.auth.user.avatar_url" class="mock-avatar avatar-img" />
                                    <div v-else class="mock-avatar"></div>
                                    <img v-if="activeFramePath" :src="'/storage/' + activeFramePath" alt="" class="frame-img" />
                                </div>
                                <div class="current-actions">
                                    <button v-if="activeFramePath" class="btn-unequip" @click="unequip">Снять рамку</button>
                                    <div v-else class="no-frame-text">Рамка не надета</div>
                                </div>
                            </div>

                            <div class="frames-grid-title">Коллекция рамок</div>
                            
                            <div class="frames-grid">
                                <div 
                                    v-for="frame in frames" 
                                    :key="frame.id" 
                                    class="frame-card"
                                    :class="{ 'is-locked': !isUnlocked(frame), 'is-active': activeFramePath === frame.image_path }"
                                >
                                    <div class="frame-preview-box">
                                        <div class="mock-avatar-small"></div>
                                        <img :src="frame.image_url" alt="" class="frame-img" />
                                    </div>
                                    
                                    <div class="frame-info">
                                        <div class="frame-name">{{ frame.name }}</div>
                                        <div v-if="frame.type === 'achievement'" class="frame-desc">
                                            {{ frame.condition_description || 'За достижение' }}
                                        </div>
                                        <div v-else-if="frame.type === 'paid'" class="frame-desc">
                                            {{ frame.price }} ₽
                                        </div>
                                        <div v-else class="frame-desc">
                                            Бесплатная
                                        </div>
                                    </div>

                                    <div class="frame-actions">
                                        <button 
                                            v-if="isUnlocked(frame) && activeFramePath !== frame.image_path" 
                                            class="btn-equip"
                                            @click="equip(frame)"
                                        >
                                            Надеть
                                        </button>
                                        <button 
                                            v-else-if="activeFramePath === frame.image_path" 
                                            class="btn-equip is-equipped" 
                                            disabled
                                        >
                                            Надета
                                        </button>
                                        <button 
                                            v-else 
                                            class="btn-equip is-disabled" 
                                            disabled
                                        >
                                            Заблокирована
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-wrap {
    padding: 2rem 1rem 4rem;
    flex: 1;
}

.customization-container {
    max-width: 1440px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.page-header {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}

.back-link {
    font-size: 0.85rem;
    color: rgba(255, 178, 239, 0.7);
    text-decoration: none;
    transition: color 0.2s;
}
.back-link:hover { color: rgba(255, 178, 239, 1); }

.page-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}

.split-layout {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}

@media (max-width: 768px) {
    .split-layout {
        flex-direction: column;
    }
}

/* ── Sidebar Menu ────────────────────────────────────────────── */
.sidebar-menu {
    flex: 0 0 280px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    padding: 0.75rem;
}
@media (max-width: 768px) {
    .sidebar-menu {
        flex: auto;
        width: 100%;
    }
}

.menu-item {
    padding: 0.75rem 1rem;
    border-radius: 6px;
    background: transparent;
    border: 1px solid transparent;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s;
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.9);
}

.menu-item.is-active {
    background: linear-gradient(
        160deg,
        color-mix(in srgb, var(--color-base-1), transparent 85%) 0%,
        color-mix(in srgb, var(--color-base-1), transparent 92%) 100%
    );
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 70%);
    color: var(--color-base-1);
}

/* ── Content Area ────────────────────────────────────────────── */
.content-area {
    flex: 1;
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    padding: 2rem;
    min-width: 0;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: #fff;
}

.section-desc {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 2rem 0;
    line-height: 1.4;
}

/* ── Loading Spinner ─────────────────────────────────────────── */
.frames-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: rgba(255, 255, 255, 0.6);
    gap: 10px;
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ── Frames Logic ────────────────────────────────────────────── */
.frames-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.current-frame-section {
    text-align: center;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 3px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.section-subtitle {
    margin: 0 0 1.25rem 0;
    font-size: 1.05rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
}

.current-preview {
    width: 130px;
    height: 130px;
    margin: 0 auto;
    position: relative;
}
.mock-avatar {
    position: absolute;
    top: 6px;
    left: 6px;
    right: 6px;
    bottom: 6px;
    background: linear-gradient(135deg, #2a2536, #15131a);
    border-radius: 50%;
    z-index: 1;
}
.mock-avatar.avatar-img {
    width: calc(100% - 12px);
    height: calc(100% - 12px);
    object-fit: cover;
}
.mock-avatar-small {
    position: absolute;
    top: 5px;
    left: 5px;
    right: 5px;
    bottom: 5px;
    background: linear-gradient(135deg, #2a2536, #15131a);
    border-radius: 50%;
    z-index: 1;
}
.frame-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    z-index: 2;
    pointer-events: none;
}

.current-actions {
    margin-top: 1.25rem;
}

.btn-unequip {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
    padding: 0.6rem 1.25rem;
    border-radius: 3px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-unequip:hover {
    background: rgba(239, 68, 68, 0.2);
}

.no-frame-text {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.4);
}

.frames-grid-title {
    font-size: 1.15rem;
    font-weight: 600;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
}

.frames-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
}

.frame-card {
    background: rgba(255, 255, 255, 0.02);
    border-radius: 3px;
    padding: 1.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: 0.2s ease;
}
.frame-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.18);
}
.frame-card.is-active {
    border-color: rgba(255, 178, 239, 0.5);
    background: rgba(255, 178, 239, 0.05);
}
.frame-card.is-locked {
    opacity: 0.5;
    filter: grayscale(100%);
}

.frame-preview-box {
    width: 90px;
    height: 90px;
    position: relative;
    margin-bottom: 1.25rem;
}

.frame-name {
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 0.35rem;
    color: rgba(255, 255, 255, 0.9);
}
.frame-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1.25rem;
    line-height: 1.4;
}
.frame-actions {
    margin-top: auto;
    width: 100%;
}

.btn-equip {
    width: 100%;
    background: rgba(255, 178, 239, 0.15);
    color: rgb(255, 178, 239);
    border: 1px solid rgba(255, 178, 239, 0.3);
    padding: 0.75rem 0;
    border-radius: 3px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-equip:hover {
    background: rgba(255, 178, 239, 0.25);
}

.btn-equip.is-equipped {
    background: rgba(16, 185, 129, 0.15);
    color: rgb(16, 185, 129);
    border-color: rgba(16, 185, 129, 0.3);
    cursor: default;
}

.btn-equip.is-disabled {
    background: rgba(255, 255, 255, 0.02);
    color: rgba(255, 255, 255, 0.3);
    border-color: transparent;
    cursor: default;
}
</style>
