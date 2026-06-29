<script setup>
import { ref, computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import UserCard from "@/Components/UserCard.vue";
import { useTranslations } from "@/composables/useTranslations";
import { ArrowLeft, ArrowRight, Search, Star } from "@element-plus/icons-vue";

const { __, transChoice } = useTranslations();

const props = defineProps({
    idols: Object,
});

const searchQuery = ref("");
const filteredIdols = computed(() => {
    if (!searchQuery.value) return props.idols.data;
    const q = searchQuery.value.toLowerCase();
    return props.idols.data.filter((u) => u.name.toLowerCase().includes(q));
});
</script>

<template>
    <Head :title="__('nav.tracked')" />

    <AppLayout>
        <div class="tracked-container">
            <div class="tracked-header">
                <h1 class="tracked-title">{{ __("nav.tracked") }}</h1>
                <div
                    class="tracked-search"
                    v-if="idols.data.length > 0 || searchQuery"
                >
                    <el-icon class="search-icon"><Search /></el-icon>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="__('common.search') + '...'"
                        class="search-input"
                    />
                </div>
            </div>

            <div v-if="filteredIdols.length" class="idols-grid">
                <UserCard
                    v-for="user in filteredIdols"
                    :key="user.id"
                    :user="user"
                />
            </div>

            <div v-else class="empty-state">
                <el-icon class="empty-icon"><Star /></el-icon>
                <p class="empty-text">Вы пока никого не отслеживаете</p>
                <Link :href="route('users.search')" class="find-btn">
                    Найти айдолов
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="idols.links.length > 3" class="pagination">
                <Link
                    v-for="(link, i) in idols.links"
                    :key="i"
                    :href="link.url || '#'"
                    class="pag-btn"
                    :class="{ active: link.active, disabled: !link.url }"
                >
                    <template v-if="link.label.includes('Previous')">
                        <el-icon><ArrowLeft /></el-icon>
                    </template>
                    <template v-else-if="link.label.includes('Next')">
                        <el-icon><ArrowRight /></el-icon>
                    </template>
                    <span v-else v-html="link.label"></span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.tracked-container {
    padding: 1rem;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    box-sizing: border-box;
}

.tracked-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #fff;
    font-family: "Rubik", sans-serif;
    letter-spacing: -0.02em;
}

.tracked-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    gap: 1.5rem;
}

.tracked-search {
    position: relative;
    flex: 1;
    max-width: 300px;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.3);
    font-size: 1.1rem;
}

.search-input {
    width: 100%;
    height: 42px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 0 1rem 0 2.8rem;
    color: #fff;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.3s ease;
}

.search-input:focus {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 178, 239, 0.3);
    box-shadow: 0 0 15px rgba(255, 178, 239, 0.1);
}

.idols-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 0.75rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 6rem 2rem;
    text-align: center;
    background: rgba(20, 15, 30, 0.4);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 20px;
    border: 1px dashed rgba(255, 178, 239, 0.3);
    box-shadow: inset 0 0 40px rgba(255, 178, 239, 0.05);
}

.empty-icon {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    color: var(--color-base-1);
    filter: drop-shadow(0 0 20px rgba(255, 178, 239, 0.6));
    opacity: 0.9;
}

.empty-text {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 2rem;
}

.find-btn {
    padding: 0.8rem 2rem;
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-1), transparent 80%),
        color-mix(in srgb, var(--color-base-1), transparent 90%)
    );
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 40%);
    color: var(--color-base-1);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.find-btn:hover {
    background: var(--color-base-1);
    color: #000;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px
        color-mix(in srgb, var(--color-base-1), transparent 80%);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.6rem;
    margin-top: 3rem;
}

.pag-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.2s;
}

.pag-btn:hover:not(.disabled) {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--color-base-1);
    color: var(--color-base-1);
}

.pag-btn.active {
    background: var(--color-base-1);
    border-color: var(--color-base-1);
    color: #000;
}

.pag-btn.disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

@media (max-width: 600px) {
    .idols-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .tracked-title {
        font-size: 1.5rem;
    }

    .tracked-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .tracked-search {
        max-width: 100%;
        width: 100%;
    }
}
</style>
