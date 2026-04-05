<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    profileUserId: { type: Number, required: true },
});

const reviews   = ref([]);
const total     = ref(0);
const avgRating = ref(null);
const loading   = ref(true);

onMounted(async () => {
    try {
        const res = await axios.get(route('users.reviews', props.profileUserId));
        reviews.value   = res.data.reviews;
        total.value     = res.data.total;
        avgRating.value = res.data.avg_rating;
    } finally {
        loading.value = false;
    }
});

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <div class="pr-wrap anim-block">

        <!-- Loading -->
        <div v-if="loading" class="pr-loading">Загрузка отзывов…</div>

        <!-- Empty -->
        <div v-else-if="!reviews.length" class="pr-empty">
            <svg class="pr-empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <p class="pr-empty__title">Отзывов пока нет</p>
            <p class="pr-empty__hint">Отзывы появятся после завершения заказов</p>
        </div>

        <template v-else>
            <!-- Summary -->
            <div class="pr-summary">
                <div class="pr-summary__hearts">
                    <svg
                        v-for="i in 5"
                        :key="i"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                        class="pr-summary__heart"
                        :class="{ 'pr-summary__heart--filled': avgRating && i <= Math.round(avgRating) }"
                    >
                        <defs>
                            <radialGradient :id="`sg-${i}`" cx="50%" cy="35%" r="65%">
                                <stop offset="0%" stop-color="rgba(255,190,210,0.95)" />
                                <stop offset="100%" stop-color="rgba(210,50,100,0.9)" />
                            </radialGradient>
                        </defs>
                        <path
                            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                            :fill="(avgRating && i <= Math.round(avgRating)) ? `url(#sg-${i})` : 'none'"
                            :stroke="(avgRating && i <= Math.round(avgRating)) ? 'rgba(210,60,100,0.5)' : 'rgba(255,160,180,0.3)'"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
                <span v-if="avgRating" class="pr-summary__score">{{ avgRating }}</span>
                <span class="pr-summary__count">{{ total }} {{ total === 1 ? 'отзыв' : total < 5 ? 'отзыва' : 'отзывов' }}</span>
            </div>

            <!-- List -->
            <div class="pr-list">
                <div v-for="r in reviews" :key="r.id" class="pr-card">
                    <!-- Reviewer + date -->
                    <div class="pr-card__head">
                        <div class="pr-card__avatar">
                            <img v-if="r.reviewer.avatar_url" :src="r.reviewer.avatar_url" alt="" />
                            <span v-else>{{ r.reviewer.name.charAt(0) }}</span>
                        </div>
                        <div class="pr-card__who">
                            <span class="pr-card__name">{{ r.reviewer.name }}</span>
                            <span class="pr-card__date">{{ formatDate(r.created_at) }}</span>
                        </div>
                        <!-- Hearts -->
                        <div class="pr-card__hearts">
                            <svg
                                v-for="i in 5"
                                :key="i"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                                class="pr-card__heart"
                                :class="{ 'pr-card__heart--filled': i <= r.rating }"
                            >
                                <defs>
                                    <radialGradient :id="`rg-${r.id}-${i}`" cx="50%" cy="35%" r="65%">
                                        <stop offset="0%" stop-color="rgba(255,190,210,0.95)" />
                                        <stop offset="100%" stop-color="rgba(210,50,100,0.9)" />
                                    </radialGradient>
                                </defs>
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                                    :fill="i <= r.rating ? `url(#rg-${r.id}-${i})` : 'none'"
                                    :stroke="i <= r.rating ? 'rgba(210,60,100,0.5)' : 'rgba(255,160,180,0.25)'"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- Services snapshot -->
                    <div v-if="r.services_snapshot?.length" class="pr-card__services">
                        <span
                            v-for="(svc, idx) in r.services_snapshot"
                            :key="idx"
                            class="pr-card__svc-tag"
                        >
                            <span v-if="svc.category_name" class="pr-card__svc-cat">{{ svc.category_name }}</span>
                            <span class="pr-card__svc-name">{{ svc.name }}</span>
                        </span>
                    </div>

                    <!-- Epithets -->
                    <div v-if="r.epithets?.length" class="pr-card__epithets">
                        <span v-for="ep in r.epithets" :key="ep.id" class="pr-card__epithet">
                            {{ ep.label }}
                        </span>
                    </div>

                    <!-- Text -->
                    <p v-if="r.text" class="pr-card__text">{{ r.text }}</p>
                </div>
            </div>
        </template>

    </div>
</template>

<style scoped>
.pr-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
    padding: 0.5rem 0;
}

.pr-loading, .pr-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255,255,255,0.3);
}
.pr-empty__icon {
    width: 40px;
    height: 40px;
    margin: 0 auto 0.8rem;
    display: block;
    opacity: 0.25;
}
.pr-empty__title {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255,255,255,0.45);
    margin: 0 0 0.3rem;
}
.pr-empty__hint {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.22);
    margin: 0;
}

/* ── Summary ─────────────────────────────── */
.pr-summary {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.9rem 1.2rem;
    background: rgba(255,120,160,0.05);
    border: 1px solid rgba(255,120,160,0.15);
    border-radius: 8px;
}
.pr-summary__hearts {
    display: flex;
    gap: 0.25rem;
}
.pr-summary__heart {
    width: 20px;
    height: 20px;
}
.pr-summary__score {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(255,190,210,0.9);
    font-family: 'Courier New', monospace;
    letter-spacing: 0.05em;
}
.pr-summary__count {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.35);
    margin-left: 0.2rem;
}

/* ── Card ────────────────────────────────── */
.pr-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
.pr-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pr-card__head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.pr-card__avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255,255,255,0.08);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255,255,255,0.6);
}
.pr-card__avatar img { width: 100%; height: 100%; object-fit: cover; }
.pr-card__who {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex: 1;
    min-width: 0;
}
.pr-card__name {
    font-size: 0.88rem;
    font-weight: 600;
    color: rgba(255,255,255,0.82);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pr-card__date {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.3);
}
.pr-card__hearts {
    display: flex;
    gap: 0.2rem;
    flex-shrink: 0;
}
.pr-card__heart {
    width: 16px;
    height: 16px;
}

/* Services */
.pr-card__services {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.pr-card__svc-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    font-size: 0.75rem;
}
.pr-card__svc-cat {
    color: rgba(255,255,255,0.35);
    font-size: 0.7rem;
}
.pr-card__svc-cat::after { content: ' /'; margin-right: 0.1rem; }
.pr-card__svc-name { color: rgba(255,255,255,0.65); }

/* Epithets */
.pr-card__epithets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.pr-card__epithet {
    padding: 0.22rem 0.6rem;
    background: rgba(255,120,160,0.1);
    border: 1px solid rgba(255,120,160,0.25);
    border-radius: 100px;
    font-size: 0.75rem;
    color: rgba(255,190,210,0.8);
}

/* Text */
.pr-card__text {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.62);
    line-height: 1.55;
    margin: 0;
    font-style: italic;
}
</style>
