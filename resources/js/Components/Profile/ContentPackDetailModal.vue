<script setup>
import { inject, ref, computed, onMounted, onUnmounted } from 'vue';
import { Picture } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    show:      { type: Boolean, default: false },
    pack:      { type: Object, required: true },
    purchased: { type: Boolean, default: false },
    isOwner:   { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const addToContentCart = inject('addToContentCart', null);
const openAuth         = inject('openAuth', null);

const photos = computed(() => props.pack.photos ?? []);
const canViewPhotos = computed(() => (props.isOwner || props.purchased) && photos.value.length > 0);

// Lightbox
const lightboxIndex = ref(null);

function openLightbox(index) {
    lightboxIndex.value = index;
}
function closeLightbox() {
    lightboxIndex.value = null;
}
function prevPhoto() {
    lightboxIndex.value = (lightboxIndex.value - 1 + photos.value.length) % photos.value.length;
}
function nextPhoto() {
    lightboxIndex.value = (lightboxIndex.value + 1) % photos.value.length;
}

function handleKeydown(e) {
    if (lightboxIndex.value === null) return;
    if (e.key === 'ArrowLeft') prevPhoto();
    else if (e.key === 'ArrowRight') nextPhoto();
    else if (e.key === 'Escape') closeLightbox();
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

function handleAddToCart() {
    if (!addToContentCart) {
        openAuth?.('register');
        emit('close');
        return;
    }
    addToContentCart(props.pack);
    emit('close');
}
</script>

<template>
    <SiteModal :show="show" variant="blue" :max-width="canViewPhotos ? '760px' : '520px'" compact no-padding @close="emit('close')">
        <div class="cdm-wrap">
            <!-- Photo grid for owner / purchased -->
            <template v-if="canViewPhotos">
                <div class="cdm-gallery">
                    <div
                        v-for="(photo, idx) in photos"
                        :key="photo.id"
                        class="cdm-thumb"
                        @click="openLightbox(idx)"
                    >
                        <img :src="photo.url" :alt="pack.title + ' ' + (idx + 1)" />
                        <div class="cdm-thumb__hover">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                <line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Cover for non-purchased visitors -->
            <template v-else>
                <div class="cdm-cover">
                    <img v-if="pack.cover_url" :src="pack.cover_url" :alt="pack.title" />
                    <div v-else class="cdm-cover__placeholder">
                        <el-icon :size="48"><Picture /></el-icon>
                    </div>
                    <div class="cdm-cover__badge">
                        <el-icon :size="14"><Picture /></el-icon>
                        {{ __('pack.photos', { count: pack.photos_count }) }}
                    </div>
                </div>
            </template>

            <!-- Info bar -->
            <div class="cdm-info">
                <div class="cdm-info__top">
                    <div>
                        <h2 class="cdm-title">{{ pack.title }}</h2>
                        <p v-if="pack.description" class="cdm-desc">{{ pack.description }}</p>
                    </div>
                    <div class="cdm-meta">
                        <span class="cdm-price">{{ pack.price }} ₽</span>
                        <span class="cdm-count">{{ __('pack.photos', { count: pack.photos_count }) }}</span>
                    </div>
                </div>

                <div class="cdm-actions">
                    <template v-if="!isOwner">
                        <button v-if="purchased" class="cdm-btn cdm-btn--purchased" disabled>{{ __('pack.already_purchased') }}</button>
                        <button v-else class="cdm-btn cdm-btn--cart" @click="handleAddToCart">{{ __('pack.add_to_cart') }}</button>
                    </template>
                    <button class="cdm-btn cdm-btn--close" @click="emit('close')">{{ __('common.close') }}</button>
                </div>
            </div>
        </div>
    </SiteModal>

    <!-- Lightbox -->
    <Teleport to="body">
        <Transition name="lb">
            <div v-if="lightboxIndex !== null" class="lb-overlay" @click.self="closeLightbox">
                <button class="lb-close" @click="closeLightbox">✕</button>

                <button class="lb-arrow lb-arrow--prev" @click="prevPhoto">&#8249;</button>

                <div class="lb-img-wrap">
                    <img :src="photos[lightboxIndex]?.url" :alt="pack.title" class="lb-img" />
                </div>

                <button class="lb-arrow lb-arrow--next" @click="nextPhoto">&#8250;</button>

                <div class="lb-counter">{{ lightboxIndex + 1 }} / {{ photos.length }}</div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cdm-wrap {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    max-height: 85vh;
}

/* ── Photo gallery ── */
.cdm-gallery {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 3px;
    overflow-y: auto;
    max-height: 60vh;
    flex-shrink: 0;
}

.cdm-thumb {
    position: relative;
    aspect-ratio: 3/4;
    overflow: hidden;
    cursor: pointer;
    background: rgba(255,255,255,0.04);
}
.cdm-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.2s;
}
.cdm-thumb:hover img {
    transform: scale(1.04);
}
.cdm-thumb__hover {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0);
    color: rgba(255,255,255,0);
    transition: background 0.18s, color 0.18s;
}
.cdm-thumb:hover .cdm-thumb__hover {
    background: rgba(0,0,0,0.35);
    color: rgba(255,255,255,0.9);
}

/* ── Cover (non-purchased) ── */
.cdm-cover {
    position: relative;
    aspect-ratio: 16/9;
    background: rgba(255,255,255,0.04);
    overflow: hidden;
    flex-shrink: 0;
}
.cdm-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.cdm-cover__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.2);
}
.cdm-cover__badge {
    position: absolute;
    bottom: 10px;
    left: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    color: rgba(255,255,255,0.85);
    font-size: 0.8rem;
    padding: 4px 10px;
    border-radius: 20px;
}

/* ── Info ── */
.cdm-info {
    padding: 1rem 1.25rem 1.25rem;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.cdm-info__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.cdm-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    margin: 0 0 0.3rem;
    line-height: 1.3;
}
.cdm-desc {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.45);
    margin: 0;
    line-height: 1.5;
    white-space: pre-wrap;
    max-height: 3.5em;
    overflow: hidden;
}
.cdm-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.15rem;
    white-space: nowrap;
    flex-shrink: 0;
}
.cdm-price {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(255, 178, 239,0.9);
}
.cdm-count {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.3);
}

.cdm-actions {
    display: flex;
    gap: 0.65rem;
}

.cdm-btn {
    padding: 0.5rem 1.1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    border: 1px solid;
    transition: background 0.15s;
}
.cdm-btn--cart {
    background: rgba(100,210,255,0.12);
    border-color: rgba(100,210,255,0.4);
    color: #64d2ff;
    flex: 1;
}
.cdm-btn--cart:hover { background: rgba(100,210,255,0.22); }
.cdm-btn--purchased {
    background: rgba(100,210,160,0.08);
    border-color: rgba(100,210,160,0.25);
    color: rgba(100,210,160,0.6);
    cursor: default;
    flex: 1;
}
.cdm-btn--close {
    background: transparent;
    border-color: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.4);
}
.cdm-btn--close:hover { background: rgba(255,255,255,0.05); }

/* ── Lightbox ── */
.lb-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0,0,0,0.92);
    display: flex;
    align-items: center;
    justify-content: center;
}

.lb-img-wrap {
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lb-img {
    max-width: 90vw;
    max-height: 90vh;
    object-fit: contain;
    display: block;
    border-radius: 4px;
    box-shadow: 0 8px 60px rgba(0,0,0,0.8);
}

.lb-close {
    position: absolute;
    top: 18px;
    right: 22px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.7);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    z-index: 1;
}
.lb-close:hover { background: rgba(255,255,255,0.15); color: #fff; }

.lb-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.7);
    width: 46px;
    height: 46px;
    border-radius: 50%;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    z-index: 1;
    padding-bottom: 2px;
}
.lb-arrow:hover { background: rgba(255,255,255,0.15); color: #fff; }
.lb-arrow--prev { left: 18px; }
.lb-arrow--next { right: 18px; }

.lb-counter {
    position: absolute;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(6px);
    color: rgba(255,255,255,0.7);
    font-size: 0.85rem;
    padding: 4px 14px;
    border-radius: 20px;
}

.lb-enter-active, .lb-leave-active { transition: opacity 0.18s; }
.lb-enter-from, .lb-leave-to { opacity: 0; }
</style>
