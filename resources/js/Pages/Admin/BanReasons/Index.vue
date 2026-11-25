<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import {
    Warning, Plus, Delete, EditPen, Check, Close, Top, Bottom, Lock, ChatDotRound
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    chat_block_reasons: Array,
    user_ban_reasons:   Array,
});

// ─── Editing ──────────────────────────────────────────────────────────────────
const editingId      = ref(null);
const editingLabelRu = ref('');
const editingLabelEn = ref('');

function startEdit(item) {
    editingId.value      = item.id;
    editingLabelRu.value = item.label_ru;
    editingLabelEn.value = item.label_en ?? '';
}

function cancelEdit() {
    editingId.value      = null;
    editingLabelRu.value = '';
    editingLabelEn.value = '';
}

function saveEdit(item) {
    if (!editingLabelRu.value.trim()) return;
    router.patch(route('admin.ban-reasons.update', item.id), {
        label_ru: editingLabelRu.value.trim(),
        label_en: editingLabelEn.value.trim(),
    }, { preserveScroll: true, onSuccess: cancelEdit });
}

// ─── Delete ───────────────────────────────────────────────────────────────────
function deleteReason(item) {
    ElMessageBox.confirm(`Удалить «${item.label_ru}»?`, 'Удаление причины блокировки', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(route('admin.ban-reasons.destroy', item.id), { preserveScroll: true });
    }).catch(() => {});
}

// ─── Reorder ──────────────────────────────────────────────────────────────────
function move(list, index, direction) {
    const target = index + direction;
    if (target < 0 || target >= list.length) return;
    const items = list.map((r, i) => ({ id: r.id, sort_order: i }));
    [items[index].sort_order, items[target].sort_order] = [items[target].sort_order, items[index].sort_order];
    router.post(route('admin.ban-reasons.reorder'), { items }, { preserveScroll: true });
}

// ─── Add ──────────────────────────────────────────────────────────────────────
const newChatLabelRu = ref('');
const newChatLabelEn = ref('');
const newBanLabelRu  = ref('');
const newBanLabelEn  = ref('');

function addChatReason() {
    if (!newChatLabelRu.value.trim()) return;
    router.post(route('admin.ban-reasons.store'), {
        label_ru: newChatLabelRu.value.trim(),
        label_en: newChatLabelEn.value.trim(),
        type: 'chat_block',
    }, { preserveScroll: true, onSuccess: () => { newChatLabelRu.value = ''; newChatLabelEn.value = ''; } });
}

function addBanReason() {
    if (!newBanLabelRu.value.trim()) return;
    router.post(route('admin.ban-reasons.store'), {
        label_ru: newBanLabelRu.value.trim(),
        label_en: newBanLabelEn.value.trim(),
        type: 'user_ban',
    }, { preserveScroll: true, onSuccess: () => { newBanLabelRu.value = ''; newBanLabelEn.value = ''; } });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-rose-400"><Lock /></el-icon>
                    <span>Причины блокировок</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Управление справочниками причин для пользовательских жалоб в чате и блокировок аккаунтов администратором
                </p>
            </div>
        </div>

        <!-- 2 Columns Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Col 1: Chat block reasons -->
            <div class="p-5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <el-icon class="text-pink-400"><ChatDotRound /></el-icon>
                        <h2 class="text-sm font-semibold text-zinc-100">Блокировки в чате</h2>
                    </div>
                    <span class="text-xs text-zinc-500 font-mono">{{ chat_block_reasons?.length || 0 }} поз.</span>
                </div>

                <!-- Add Form -->
                <form @submit.prevent="addChatReason" class="space-y-2 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            v-model="newChatLabelRu"
                            type="text"
                            placeholder="Причина (RU) *"
                            required
                            class="h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        />
                        <input
                            v-model="newChatLabelEn"
                            type="text"
                            placeholder="Причина (EN)"
                            class="h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        />
                    </div>
                    <AdminButton variant="outline" size="sm" type="submit" class="w-full">
                        <el-icon class="mr-1"><Plus /></el-icon>
                        Добавить причину блокировки в чате
                    </AdminButton>
                </form>

                <!-- List -->
                <div class="space-y-2 pt-2">
                    <div
                        v-for="(item, index) in chat_block_reasons"
                        :key="item.id"
                        class="p-2.5 rounded-lg border border-zinc-800 bg-zinc-950/60 flex items-center justify-between gap-2 text-xs"
                    >
                        <!-- Order buttons -->
                        <div class="flex items-center gap-0.5">
                            <button
                                type="button"
                                class="p-1 rounded text-zinc-500 hover:text-zinc-200 hover:bg-zinc-800 disabled:opacity-30 disabled:pointer-events-none"
                                :disabled="index === 0"
                                @click="move(chat_block_reasons, index, -1)"
                            >
                                <el-icon :size="12"><Top /></el-icon>
                            </button>
                            <button
                                type="button"
                                class="p-1 rounded text-zinc-500 hover:text-zinc-200 hover:bg-zinc-800 disabled:opacity-30 disabled:pointer-events-none"
                                :disabled="index === chat_block_reasons.length - 1"
                                @click="move(chat_block_reasons, index, 1)"
                            >
                                <el-icon :size="12"><Bottom /></el-icon>
                            </button>
                        </div>

                        <!-- Content or Inline Edit -->
                        <div v-if="editingId === item.id" class="flex-1 flex items-center gap-1.5">
                            <input
                                v-model="editingLabelRu"
                                type="text"
                                class="flex-1 h-7 px-2 rounded border border-zinc-700 bg-zinc-900 text-xs text-white"
                            />
                            <input
                                v-model="editingLabelEn"
                                type="text"
                                placeholder="EN"
                                class="w-24 h-7 px-2 rounded border border-zinc-700 bg-zinc-900 text-xs text-white"
                            />
                            <button type="button" class="text-emerald-400 hover:text-emerald-300 p-1" @click="saveEdit(item)">
                                <el-icon><Check /></el-icon>
                            </button>
                            <button type="button" class="text-zinc-500 hover:text-zinc-300 p-1" @click="cancelEdit">
                                <el-icon><Close /></el-icon>
                            </button>
                        </div>
                        <div v-else class="flex-1 min-w-0">
                            <div class="font-medium text-zinc-200 truncate">{{ item.label_ru }}</div>
                            <div v-if="item.label_en" class="text-[11px] text-zinc-500 truncate">{{ item.label_en }}</div>
                        </div>

                        <!-- Actions -->
                        <div v-if="editingId !== item.id" class="flex items-center gap-1">
                            <button
                                type="button"
                                class="p-1 text-zinc-500 hover:text-zinc-300 transition"
                                @click="startEdit(item)"
                            >
                                <el-icon><EditPen /></el-icon>
                            </button>
                            <button
                                type="button"
                                class="p-1 text-zinc-500 hover:text-rose-400 transition"
                                @click="deleteReason(item)"
                            >
                                <el-icon><Delete /></el-icon>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Col 2: User ban reasons -->
            <div class="p-5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <el-icon class="text-rose-400"><Lock /></el-icon>
                        <h2 class="text-sm font-semibold text-zinc-100">Блокировки аккаунта (Баны)</h2>
                    </div>
                    <span class="text-xs text-zinc-500 font-mono">{{ user_ban_reasons?.length || 0 }} поз.</span>
                </div>

                <!-- Add Form -->
                <form @submit.prevent="addBanReason" class="space-y-2 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            v-model="newBanLabelRu"
                            type="text"
                            placeholder="Причина бана (RU) *"
                            required
                            class="h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        />
                        <input
                            v-model="newBanLabelEn"
                            type="text"
                            placeholder="Причина (EN)"
                            class="h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        />
                    </div>
                    <AdminButton variant="outline" size="sm" type="submit" class="w-full">
                        <el-icon class="mr-1"><Plus /></el-icon>
                        Добавить причину бана аккаунта
                    </AdminButton>
                </form>

                <!-- List -->
                <div class="space-y-2 pt-2">
                    <div
                        v-for="(item, index) in user_ban_reasons"
                        :key="item.id"
                        class="p-2.5 rounded-lg border border-zinc-800 bg-zinc-950/60 flex items-center justify-between gap-2 text-xs"
                    >
                        <!-- Order buttons -->
                        <div class="flex items-center gap-0.5">
                            <button
                                type="button"
                                class="p-1 rounded text-zinc-500 hover:text-zinc-200 hover:bg-zinc-800 disabled:opacity-30 disabled:pointer-events-none"
                                :disabled="index === 0"
                                @click="move(user_ban_reasons, index, -1)"
                            >
                                <el-icon :size="12"><Top /></el-icon>
                            </button>
                            <button
                                type="button"
                                class="p-1 rounded text-zinc-500 hover:text-zinc-200 hover:bg-zinc-800 disabled:opacity-30 disabled:pointer-events-none"
                                :disabled="index === user_ban_reasons.length - 1"
                                @click="move(user_ban_reasons, index, 1)"
                            >
                                <el-icon :size="12"><Bottom /></el-icon>
                            </button>
                        </div>

                        <!-- Content or Inline Edit -->
                        <div v-if="editingId === item.id" class="flex-1 flex items-center gap-1.5">
                            <input
                                v-model="editingLabelRu"
                                type="text"
                                class="flex-1 h-7 px-2 rounded border border-zinc-700 bg-zinc-900 text-xs text-white"
                            />
                            <input
                                v-model="editingLabelEn"
                                type="text"
                                placeholder="EN"
                                class="w-24 h-7 px-2 rounded border border-zinc-700 bg-zinc-900 text-xs text-white"
                            />
                            <button type="button" class="text-emerald-400 hover:text-emerald-300 p-1" @click="saveEdit(item)">
                                <el-icon><Check /></el-icon>
                            </button>
                            <button type="button" class="text-zinc-500 hover:text-zinc-300 p-1" @click="cancelEdit">
                                <el-icon><Close /></el-icon>
                            </button>
                        </div>
                        <div v-else class="flex-1 min-w-0">
                            <div class="font-medium text-zinc-200 truncate">{{ item.label_ru }}</div>
                            <div v-if="item.label_en" class="text-[11px] text-zinc-500 truncate">{{ item.label_en }}</div>
                        </div>

                        <!-- Actions -->
                        <div v-if="editingId !== item.id" class="flex items-center gap-1">
                            <button
                                type="button"
                                class="p-1 text-zinc-500 hover:text-zinc-300 transition"
                                @click="startEdit(item)"
                            >
                                <el-icon><EditPen /></el-icon>
                            </button>
                            <button
                                type="button"
                                class="p-1 text-zinc-500 hover:text-rose-400 transition"
                                @click="deleteReason(item)"
                            >
                                <el-icon><Delete /></el-icon>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
