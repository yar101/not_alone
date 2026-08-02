<script setup>
import { computed } from 'vue';
import { Link } from "@inertiajs/vue3";
import { useTranslations } from "@/composables/useTranslations";
import UserCardAvatar from "@/Components/UserCardAvatar.vue";

const props = defineProps({
    user: {
        type: Object,
        required: true
    },
    compact: {
        type: Boolean,
        default: false
    },
    showNewbieBadge: {
        type: Boolean,
        default: true
    }
});

const { __, transChoice } = useTranslations();

function calcAge(birthDate) {
    if (!birthDate) return null;
    const diff = Date.now() - new Date(birthDate).getTime();
    return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
}
</script>

<template>
    <Link
        :href="route('profile.show', { user: user.id }) + '#about'"
        class="user-card"
        :class="{ 'is-compact': compact }"
    >
        <UserCardAvatar :user="user" :compact="compact" :show-newbie-badge="showNewbieBadge" />
        <div class="card-body">
            <div class="card-name-row">
                <div class="card-name">{{ user.name }}</div>
            </div>
            <div class="card-badges">
                <span
                    v-if="user.gender"
                    class="card-badge"
                    :class="
                        user.gender === 'female'
                            ? 'card-badge--female'
                            : 'card-badge--male'
                    "
                    >{{
                        user.gender === "female"
                            ? "\u2640\uFE0F"
                            : "\u2642\uFE0F"
                    }}</span
                >
                <span
                    v-if="calcAge(user.birth_date)"
                    class="card-badge card-badge--age"
                    >{{ calcAge(user.birth_date) }}
                    {{
                        transChoice(
                            "search.age.years",
                            calcAge(user.birth_date),
                        )
                    }}</span
                >
            </div>
        </div>
    </Link>
</template>

<style scoped>
.user-card {
    position: relative;
    display: flex;
    flex-direction: column;
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.05) 0%,
        rgba(255, 255, 255, 0.02) 100%
    );
    border: 1px solid rgba(255, 178, 239, 0.08);
    border-radius: 12px;
    padding: 1rem 0.75rem;
    text-decoration: none;
    transition: all 0.3s ease;
    min-width: 0;
    overflow: hidden;
    align-items: center;
    text-align: center;
}

.user-card.is-compact {
    padding: 0.75rem 0.5rem;
}

.user-card:hover {
    border-color: rgba(255, 178, 239, 0.3);
    box-shadow:
        0 6px 16px -4px rgba(0, 0, 0, 0.4),
        0 0 10px rgba(255, 178, 239, 0.05);
}

.user-card:hover :deep(.card-avatar) {
    border-color: rgba(255, 178, 239, 0.5);
}

.user-card:hover :deep(.card-avatar.is-male) {
    border-color: rgba(100, 210, 255, 0.5);
}

.card-body {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
    align-items: center;
    text-align: center;
    width: 100%;
    padding-top: 0.4rem;
}

.is-compact .card-body {
    gap: 0.2rem;
    padding-top: 0.25rem;
}

.card-name-row {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    width: 100%;
}

.card-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    max-width: 100%;
    display: block;
    letter-spacing: 0.01em;
}

.is-compact .card-name {
    font-size: 0.88rem;
}

.card-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    justify-content: center;
}

.is-compact .card-badges {
    gap: 0.25rem;
}

.card-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.5);
    white-space: nowrap;
    transition: all 0.2s;
}

.is-compact .card-badge {
    padding: 0.15rem 0.4rem;
    font-size: 0.7rem;
}

.user-card:hover .card-badge {
    border-color: rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.7);
}

.card-badge--female {
    border-color: rgba(255, 178, 239, 0.25);
    background: rgba(255, 178, 239, 0.05);
    color: rgba(255, 178, 239, 0.8);
}

.card-badge--male {
    border-color: rgba(100, 210, 255, 0.25);
    background: rgba(100, 210, 255, 0.05);
    color: rgba(100, 210, 255, 0.8);
}

.card-badge--age {
    background: rgba(255, 255, 255, 0.02);
}
</style>
